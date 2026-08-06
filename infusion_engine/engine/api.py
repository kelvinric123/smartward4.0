"""REST API for the infusion engine (stdlib http.server, JSON responses).

Endpoints:
  /                                    management UI (single-page, engine/static/index.html)
  /health                              liveness + row counts
  /api/stats                           same as /health
  /api/patients/{mrn}                  patient infusion info by MRN
  POST /api/demo/start|stop            demo pump simulator control
  GET  /api/demo/status
  POST /api/demo/pumps                 add a demo pump (body: label/ward/mrn/patient/drug/rate/vtbi)
  POST /api/demo/pumps/{label}/update  change drug/rate/vtbi/patient/mrn/ward
  POST /api/demo/pumps/{label}/remove  delete the demo pump
  POST /api/demo/pumps/{label}/{action}   start|stop|complete|alarm|clear_alarm
                                          (alarm accepts body {"alarm_text": "..."})
  /api/pumps                           latest state of every pump
  /api/pumps/{device_id}               one pump + recent readings + recent alarms
  /api/pumps/{device_id}/readings      readings for one pump
  /api/pumps/{device_id}/alarms        alarms for one pump
  /api/readings                        readings across all pumps
  /api/alarms                          alarms across all pumps
  /api/messages                        raw message log (metadata; raw=1 to include HL7)
  /api/messages/{id}                   one message including raw HL7
  POST /api/admin/clear                wipe stored data
                                       (body: {"scopes": ["pumps"|"messages"|"demo", ...],
                                               "passphrase": "..."})
  POST /api/hl7                        inject a raw HL7 message through the MLLP
                                       pipeline (body: {"raw": "MSH|..."}) - debug tool

Query params: limit (default 100, max 1000), offset, since (ISO timestamp),
device_id, state (alarms), type (messages trigger event e.g. R01/R40).

Auth: if INFUSION_API_KEY is set, requests must send X-API-Key header or
?api_key= query param. /health is always open.
"""

import json
import logging
import os
import re
import socket
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs, unquote

from . import __version__, config

logger = logging.getLogger('infusion.api')

STATIC_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'static')


def fetch_smartward_pumps():
    """Registered pump list from SmartWard (device metadata only)."""
    if not config.SMARTWARD_URL:
        return None
    url = config.SMARTWARD_URL + '/api/infusion/pumps'
    request = urllib.request.Request(url, headers={'Accept': 'application/json'})
    with urllib.request.urlopen(request, timeout=5) as response:
        return json.loads(response.read().decode('utf-8'))


def make_handler(database, api_key='', demo=None):
    db = database

    class ApiHandler(BaseHTTPRequestHandler):
        server_version = f'InfusionEngine/{__version__}'
        protocol_version = 'HTTP/1.1'

        def log_message(self, fmt, *args):
            logger.debug('%s - %s', self.address_string(), fmt % args)

        # ------------------------------------------------------------ helpers

        def _send_json(self, payload, status=200):
            body = json.dumps(payload, default=str).encode('utf-8')
            self.send_response(status)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(body)))
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(body)

        def _error(self, status, message):
            self._send_json({'error': message}, status)

        def _authorized(self, query):
            if not api_key:
                return True
            sent = self.headers.get('X-API-Key') or (query.get('api_key') or [''])[0]
            return sent == api_key

        @staticmethod
        def _int(query, name, default, maximum=None):
            try:
                value = int((query.get(name) or [default])[0])
            except (ValueError, TypeError):
                value = default
            if maximum is not None:
                value = min(value, maximum)
            return max(0, value)

        @staticmethod
        def _str(query, name):
            value = (query.get(name) or [None])[0]
            return value or None

        def _send_html(self, filename):
            filepath = os.path.join(STATIC_DIR, filename)
            try:
                with open(filepath, 'rb') as f:
                    body = f.read()
            except OSError:
                return self._error(404, 'UI not found')
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def _read_body(self):
            length = int(self.headers.get('Content-Length') or 0)
            if not length:
                return {}
            try:
                return json.loads(self.rfile.read(length).decode('utf-8'))
            except (ValueError, UnicodeDecodeError):
                return {}

        def _admin_clear(self, body):
            """Wipe stored data. Requires the clear passphrase; scopes pick what goes."""
            if (body.get('passphrase') or '').strip() != config.CLEAR_PASSPHRASE:
                return self._error(403, 'Invalid passphrase')
            scopes = body.get('scopes') or []
            valid = ('pumps', 'messages', 'demo')
            if not scopes or any(s not in valid for s in scopes):
                return self._error(
                    400, f'scopes must be a non-empty list from: {", ".join(valid)}')

            cleared = {'pumps': 0, 'readings': 0, 'alarms': 0,
                       'messages': 0, 'demo_pumps': 0}
            # Demo first so its rows are counted before a full pumps/messages wipe
            if 'demo' in scopes:
                demo_ids = demo.clear_fleet() if demo else []
                cleared['demo_pumps'] = len(demo_ids)
                for table, n in db.clear_demo_data(demo_ids).items():
                    cleared[table] += n
            if 'pumps' in scopes:
                for table, n in db.clear_pumps().items():
                    cleared[table] += n
            if 'messages' in scopes:
                cleared['messages'] += db.clear_messages()

            parts = [f'{n} {name.replace("_", " ")}'
                     for name, n in cleared.items() if n]
            message = ('Cleared: ' + ', '.join(parts)) if parts else 'Nothing to clear'
            logger.warning('Data cleared via /api/admin/clear (scopes=%s): %s',
                           ','.join(scopes), message)
            return self._send_json({'cleared': cleared, 'message': message})

        def _inject_hl7(self, body):
            """Send a pasted HL7 message into the engine's own MLLP port, so it
            takes the exact same receive -> store -> parse path as a real pump."""
            raw = (body.get('raw') or '').lstrip('\ufeff').strip()
            if not raw:
                return self._error(400, 'Body must include "raw" with the HL7 text')
            if not raw.startswith('MSH|'):
                return self._error(400, 'HL7 must start with an MSH segment (MSH|...)')

            # Browser pastes arrive with \n; HL7 segments are \r-separated
            raw = raw.replace('\r\n', '\r').replace('\n', '\r')
            frame = b'\x0b' + raw.encode('utf-8') + b'\x1c\x0d'
            try:
                with socket.create_connection(('127.0.0.1', config.MLLP_PORT),
                                              timeout=5) as sock:
                    sock.sendall(frame)
                    sock.settimeout(5)
                    ack_bytes = sock.recv(65536)
            except OSError as e:
                return self._error(502, f'Could not reach the MLLP port: {e}')

            ack = ack_bytes.decode('utf-8', errors='replace').strip('\x0b\x1c\r\n')
            ack_code = ''
            for seg in ack.split('\r'):
                if seg.startswith('MSA|'):
                    ack_code = (seg.split('|') + [''])[1]
                    break

            # Find the stored row by the control id from the pasted MSH-10;
            # parse failures never get a control id, so fall back to the latest
            msh = raw.split('\r', 1)[0].split('|')
            control_id = msh[9] if len(msh) > 9 else ''
            stored = (db.latest_message_by_control_id(control_id)
                      or db.latest_message())

            return self._send_json({
                'ok': ack_code == 'AA',
                'ack_code': ack_code,
                'ack': ack,
                'stored': stored,
            })

        # ------------------------------------------------------------- routes

        def do_GET(self):
            parsed = urlparse(self.path)
            path = parsed.path.rstrip('/') or '/'
            query = parse_qs(parsed.query)

            try:
                if path == '/':
                    return self._send_html('index.html')

                if path == '/favicon.ico':
                    self.send_response(204)
                    self.send_header('Content-Length', '0')
                    self.end_headers()
                    return

                if path == '/health':
                    stats = db.stats()
                    return self._send_json({'status': 'ok', 'version': __version__, **stats})

                if not self._authorized(query):
                    return self._error(401, 'Invalid or missing API key')

                if path == '/api/stats':
                    return self._send_json(db.stats())

                if path == '/api/pumps':
                    return self._send_json({'pumps': db.list_pumps()})

                m = re.match(r'^/api/pumps/([^/]+)$', path)
                if m:
                    pump = db.get_pump(unquote(m.group(1)))
                    if not pump:
                        return self._error(404, f'Unknown pump: {unquote(m.group(1))}')
                    limit = self._int(query, 'limit', 20, 1000)
                    pump['readings'] = db.list_readings(device_id=pump['device_id'], limit=limit)
                    pump['alarms'] = db.list_alarms(device_id=pump['device_id'], limit=limit)
                    return self._send_json(pump)

                m = re.match(r'^/api/pumps/([^/]+)/readings$', path)
                if m:
                    return self._send_json({'readings': db.list_readings(
                        device_id=unquote(m.group(1)),
                        since=self._str(query, 'since'),
                        limit=self._int(query, 'limit', 100, 1000),
                        offset=self._int(query, 'offset', 0),
                    )})

                m = re.match(r'^/api/pumps/([^/]+)/alarms$', path)
                if m:
                    return self._send_json({'alarms': db.list_alarms(
                        device_id=unquote(m.group(1)),
                        state=self._str(query, 'state'),
                        limit=self._int(query, 'limit', 100, 1000),
                        offset=self._int(query, 'offset', 0),
                    )})

                if path == '/api/readings':
                    return self._send_json({'readings': db.list_readings(
                        device_id=self._str(query, 'device_id'),
                        since=self._str(query, 'since'),
                        limit=self._int(query, 'limit', 100, 1000),
                        offset=self._int(query, 'offset', 0),
                    )})

                if path == '/api/alarms':
                    return self._send_json({'alarms': db.list_alarms(
                        device_id=self._str(query, 'device_id'),
                        state=self._str(query, 'state'),
                        limit=self._int(query, 'limit', 100, 1000),
                        offset=self._int(query, 'offset', 0),
                    )})

                m = re.match(r'^/api/patients/([^/]+)$', path)
                if m:
                    mrn = unquote(m.group(1))
                    patient = db.get_patient(mrn, limit=self._int(query, 'limit', 50, 1000))
                    if not patient:
                        return self._error(404, f'No infusion data for MRN: {mrn}')
                    return self._send_json(patient)

                if path == '/api/demo/status':
                    if not demo:
                        return self._error(404, 'Demo simulator disabled')
                    return self._send_json(demo.status())

                if path == '/api/smartward/pumps':
                    # Proxy SmartWard's registered pump list for the demo tab
                    if not config.SMARTWARD_URL:
                        return self._send_json({'configured': False, 'pumps': []})
                    try:
                        data = fetch_smartward_pumps() or {}
                        return self._send_json({
                            'configured': True,
                            'smartward_url': config.SMARTWARD_URL,
                            'pumps': data.get('pumps', []),
                        })
                    except Exception as e:
                        return self._send_json({
                            'configured': True,
                            'smartward_url': config.SMARTWARD_URL,
                            'pumps': [],
                            'error': f'SmartWard unreachable: {e}',
                        })

                if path == '/api/messages':
                    return self._send_json({'messages': db.list_messages(
                        device_id=self._str(query, 'device_id'),
                        trigger_event=self._str(query, 'type'),
                        since=self._str(query, 'since'),
                        limit=self._int(query, 'limit', 100, 1000),
                        offset=self._int(query, 'offset', 0),
                        include_raw=self._str(query, 'raw') == '1',
                    )})

                m = re.match(r'^/api/messages/(\d+)$', path)
                if m:
                    message = db.get_message(int(m.group(1)))
                    if not message:
                        return self._error(404, f'Unknown message: {m.group(1)}')
                    return self._send_json(message)

                return self._error(404, f'Unknown endpoint: {path}')
            except Exception as e:
                logger.exception('API error on %s', self.path)
                return self._error(500, str(e))

        def do_POST(self):
            parsed = urlparse(self.path)
            path = parsed.path.rstrip('/')
            query = parse_qs(parsed.query)

            try:
                if not self._authorized(query):
                    return self._error(401, 'Invalid or missing API key')

                if path == '/api/admin/clear':
                    return self._admin_clear(self._read_body())

                if path == '/api/hl7':
                    return self._inject_hl7(self._read_body())

                if not demo:
                    return self._error(404, 'Demo simulator disabled')

                if path == '/api/demo/start':
                    body = self._read_body()
                    return self._send_json(demo.start(
                        fleet=body.get('pumps'),
                        interval_sec=body.get('interval_sec'),
                    ))

                if path == '/api/demo/stop':
                    return self._send_json(demo.stop())

                if path == '/api/demo/pumps':
                    try:
                        return self._send_json(demo.add_pump(self._read_body()))
                    except ValueError as e:
                        return self._error(400, str(e))

                m = re.match(r'^/api/demo/pumps/([^/]+)/([a-z_]+)$', path)
                if m:
                    label, action = unquote(m.group(1)), m.group(2)
                    body = self._read_body()
                    try:
                        if action == 'remove':
                            return self._send_json(demo.remove_pump(label))
                        if action == 'update':
                            return self._send_json(demo.update_pump(label, body))
                        if action == 'scenario':
                            return self._send_json(
                                demo.run_scenario(label, body.get('scenario', '')))
                        return self._send_json(demo.pump_action(label, action, body))
                    except KeyError as e:
                        # str(KeyError) wraps the message in quotes - unwrap it
                        return self._error(404, e.args[0] if e.args else str(e))
                    except (ValueError, RuntimeError) as e:
                        return self._error(400, str(e))

                return self._error(404, f'Unknown endpoint: {path}')
            except Exception as e:
                logger.exception('API error on %s', self.path)
                return self._error(500, str(e))

    return ApiHandler


def create_server(host, port, database, api_key='', demo=None):
    handler = make_handler(database, api_key, demo)
    server = ThreadingHTTPServer((host, port), handler)
    server.daemon_threads = True
    return server
