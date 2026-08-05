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

Query params: limit (default 100, max 1000), offset, since (ISO timestamp),
device_id, state (alarms), type (messages trigger event e.g. R01/R40).

Auth: if INFUSION_API_KEY is set, requests must send X-API-Key header or
?api_key= query param. /health is always open.
"""

import json
import logging
import os
import re
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
