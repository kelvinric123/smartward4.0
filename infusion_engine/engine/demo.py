"""Demo data generator: virtual pumps that send real HL7 through the MLLP pipeline.

The simulator builds PCD-01 / PCD-04 messages in the same shape a B.Braun
SpacePlus pump sends and delivers them to the engine's own MLLP port, so demo
data exercises exactly the same receive -> store -> parse path as real pumps.
"""

import logging
import socket
import threading
import uuid
from datetime import datetime, timezone

logger = logging.getLogger('infusion.demo')

MLLP_START_BLOCK = b'\x0b'
MLLP_END_BLOCK = b'\x1c'
MLLP_CARRIAGE_RETURN = b'\x0d'

# Like real B.Braun pumps, demo pumps carry NO patient identity - the
# pump -> patient binding is done in the SmartWard ward dashboard.
DEFAULT_FLEET = [
    {'label': 'DEMO-P1', 'device_id': '00DEMO0000000001', 'ward': 'WARD D5',
     'facility': 'PHKL', 'drug': 'Dobutamine', 'rate': 25.0, 'vtbi': 50.0},
    {'label': 'DEMO-P2', 'device_id': '00DEMO0000000002', 'ward': 'WARD D5',
     'facility': 'PHKL', 'drug': 'Noradrenaline', 'rate': 8.0, 'vtbi': 48.0},
    {'label': 'DEMO-P3', 'device_id': '00DEMO0000000003', 'ward': 'WARD D6',
     'facility': 'PHKL', 'drug': 'Insulin', 'rate': 4.0, 'vtbi': 40.0},
]

ALARM_TEXTS = ['Syringe Holder Open', 'Occlusion Downstream', 'Occlusion Upstream',
               'Air In Line', 'Battery Low', 'Battery Empty', 'VTBI Near End',
               'Infusion Complete', 'Door Open', 'Standby Timeout']

# Editable pump settings (label/device_id are fixed once created)
EDITABLE_FIELDS = {'ward', 'facility', 'mrn', 'patient', 'drug', 'rate', 'vtbi'}


class DemoPump:
    def __init__(self, spec):
        self.label = spec['label']
        self.device_id = spec['device_id']
        self.ward = spec.get('ward', 'DEMO WARD')
        self.facility = spec.get('facility', 'DEMO')
        self.mrn = spec.get('mrn', '')
        self.patient = spec.get('patient', '')
        self.drug = spec.get('drug', 'Normal Saline')
        self.rate = float(spec.get('rate', 25.0))
        self.vtbi = float(spec.get('vtbi', 50.0))
        self.infused = 0.0
        self.status = spec.get('status', 'infusing')   # infusing / stopped / complete
        self.alarm_text = None
        self.battery = 100.0

    def update(self, fields):
        for key, value in fields.items():
            if key not in EDITABLE_FIELDS or value in (None, ''):
                continue
            if key in ('rate', 'vtbi'):
                value = float(value)
                if value <= 0:
                    raise ValueError(f'{key} must be > 0')
            setattr(self, key, value)
        if self.infused > self.vtbi:
            self.infused = self.vtbi

    def to_dict(self):
        return {
            'label': self.label, 'device_id': self.device_id, 'ward': self.ward,
            'mrn': self.mrn, 'patient': self.patient.replace('^', ' '),
            'drug': self.drug, 'rate': self.rate, 'vtbi': self.vtbi,
            'infused': round(self.infused, 2), 'status': self.status,
            'alarm': self.alarm_text, 'battery': round(self.battery, 0),
        }


def _ts():
    return datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S+0000')


def _ctrl_id():
    return uuid.uuid4().hex[:20]


class DemoSimulator:
    """Owns the virtual pump fleet and the periodic sender thread."""

    def __init__(self, mllp_host, mllp_port, interval_sec=10):
        self.mllp_host = mllp_host
        self.mllp_port = mllp_port
        self.interval_sec = interval_sec
        self.pumps = {}
        self.running = False
        self._stop = threading.Event()
        self._thread = None
        self._lock = threading.Lock()
        self._generation = 0
        self.sent_count = 0
        self.last_error = None

    # ----------------------------------------------------------------- control

    def _start_ticker_locked(self):
        """Start the periodic sender thread. Caller must hold self._lock."""
        if self.running:
            return False
        self.running = True
        self._stop.clear()
        self._generation += 1
        self._thread = threading.Thread(
            target=self._loop, args=(self._generation,), daemon=True)
        self._thread.start()
        return True

    def start(self, fleet=None, interval_sec=None):
        with self._lock:
            if interval_sec:
                self.interval_sec = max(2, int(interval_sec))
            if fleet:
                self.pumps = {s['label']: DemoPump(s) for s in fleet}
            elif not self.pumps:
                self.pumps = {s['label']: DemoPump(s) for s in DEFAULT_FLEET}
            started = self._start_ticker_locked()
        if not started:
            result = self.status()
            result['message'] = 'Demo already running - fleet unchanged'
            return result
        logger.info('Demo started: %d pumps, every %ds', len(self.pumps), self.interval_sec)
        # Send an immediate first round so the UI updates right away
        self._tick()
        result = self.status()
        if self.last_error:
            result['message'] = f'Demo started, but sending failed: {self.last_error}'
        else:
            result['message'] = (f'Demo started - {len(self.pumps)} pumps sending '
                                 f'every {self.interval_sec}s')
        return result

    def stop(self):
        with self._lock:
            was_running = self.running
            self.running = False
            self._stop.set()
        logger.info('Demo stopped')
        result = self.status()
        result['message'] = 'Demo stopped' if was_running else 'Demo was not running'
        return result

    def status(self):
        return {
            'running': self.running,
            'interval_sec': self.interval_sec,
            'sent_count': self.sent_count,
            'last_error': self.last_error,
            'alarm_options': ALARM_TEXTS,
            'default_fleet': [s['label'] for s in DEFAULT_FLEET],
            'pumps': [p.to_dict() for p in self.pumps.values()],
        }

    def add_pump(self, spec):
        spec = dict(spec or {})
        with self._lock:
            used_ids = {p.device_id for p in self.pumps.values()}
            n = 1
            while f'DEMO-P{n}' in self.pumps or f'00DEMO{n:010d}' in used_ids:
                n += 1
            label = (spec.get('label') or '').strip() or f'DEMO-P{n}'
            if label in self.pumps:
                raise ValueError(f'Pump {label} already exists')
            spec['label'] = label
            spec.setdefault('device_id', f'00DEMO{n:010d}')
            if spec['device_id'] in used_ids:
                raise ValueError(f"Device id {spec['device_id']} already exists")
            spec.setdefault('status', 'stopped')
            pump = DemoPump(spec)
            pump.update({k: spec[k] for k in ('rate', 'vtbi') if k in spec})  # validates
            self.pumps[pump.label] = pump
        return pump.to_dict()

    def remove_pump(self, label):
        with self._lock:
            self._get_pump(label)
            return self.pumps.pop(label).to_dict()

    def update_pump(self, label, fields):
        pump = self._get_pump(label)
        pump.update(fields or {})
        if self.running:
            self._send(self._build_pcd01(pump))
        return pump.to_dict()

    def _get_pump(self, label):
        pump = self.pumps.get(label)
        if not pump:
            configured = ', '.join(self.pumps) or 'none'
            raise KeyError(f'Unknown demo pump "{label}" (configured: {configured})')
        return pump

    def pump_action(self, label, action, params=None):
        params = params or {}
        message = None
        pump = self._get_pump(label)
        if action == 'start':
            pump.update(params)  # optionally change drug/rate/etc. at start
            if pump.status == 'complete':
                pump.infused = 0.0
            pump.status = 'infusing'
            with self._lock:
                # Starting an infusion implies the demo should be sending
                if self._start_ticker_locked():
                    message = f'{label} started - demo ticker auto-started'
        elif action == 'stop':
            pump.status = 'stopped'
        elif action == 'complete':
            pump.infused = pump.vtbi
            pump.status = 'complete'
        elif action == 'alarm':
            requested = (params.get('alarm_text') or '').strip()
            if requested:
                pump.alarm_text = requested[:80]
            else:
                idx = sum(1 for p in self.pumps.values() if p.alarm_text) % len(ALARM_TEXTS)
                pump.alarm_text = ALARM_TEXTS[idx]
            pump.status = 'stopped'
            self._send(self._build_pcd04(pump, phase='start', state='active'))
        elif action == 'clear_alarm':
            if pump.alarm_text:
                self._send(self._build_pcd04(pump, phase='end', state='inactive'))
                pump.alarm_text = None
        else:
            raise ValueError(f'Unknown action: {action}')
        # Push the new state out immediately
        try:
            self._send(self._build_pcd01(pump))
        except Exception as e:
            raise RuntimeError(f'Could not send HL7 to the engine MLLP port: {e}') from e
        result = pump.to_dict()
        if message:
            result['message'] = message
        return result

    # ------------------------------------------------------------------- loop

    def _loop(self, generation):
        while not self._stop.wait(self.interval_sec):
            if not self.running or generation != self._generation:
                break
            self._tick()

    def _tick(self):
        for pump in list(self.pumps.values()):
            try:
                if pump.status == 'infusing':
                    pump.infused = min(
                        pump.vtbi, pump.infused + pump.rate * self.interval_sec / 3600.0)
                    pump.battery = max(20.0, pump.battery - 0.05)
                    if pump.infused >= pump.vtbi:
                        pump.status = 'complete'
                self._send(self._build_pcd01(pump))
            except Exception as e:
                self.last_error = str(e)
                logger.error('Demo send failed for %s: %s', pump.label, e)

    # -------------------------------------------------------------- messaging

    def _send(self, raw):
        frame = MLLP_START_BLOCK + raw.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
        with socket.create_connection((self.mllp_host, self.mllp_port), timeout=5) as sock:
            sock.sendall(frame)
            sock.settimeout(5)
            sock.recv(4096)  # ACK
        self.sent_count += 1
        self.last_error = None

    def _msh_pid_obr(self, pump, profile, msg_type, drug_field):
        ts = _ts()
        # Real pumps don't know their patient - PID carries "Unknown Patient"
        # unless an MRN was explicitly configured on the demo pump.
        if pump.mrn:
            pid = f'PID|||{pump.mrn}^^^DEMO^PI||{pump.patient or "DEMO^PATIENT"}||||||||||||||||||||||||||N'
        else:
            pid = 'PID|||Unknown Patient^Unknown Patient||^^^^^^U||||||||||||||||||||||||||Y'
        return [
            f'MSH|^~\\&|PAT_DEVICE_BBRAUN^{pump.device_id}^EUI-64|BBRAUN|DEMO|DEMO|{ts}||'
            f'{msg_type}|{_ctrl_id()}|P|2.6|||AL|NE||ASCII|en^English^ISO639||{profile}',
            pid,
            f'OBR|1|0^PAT_DEVICE_BBRAUN^{pump.device_id}^EUI-64|'
            f'0^PAT_DEVICE_BBRAUN^{pump.device_id}^EUI-64|{drug_field}|||{ts}',
        ]

    def _build_pcd01(self, pump):
        status = 'infusing' if pump.status == 'infusing' else 'not-infusing'
        delivering = 'delivering' if pump.status == 'infusing' else 'not-delivering'
        rate = pump.rate if pump.status == 'infusing' else 0.0
        remaining = max(0.0, pump.vtbi - pump.infused)
        remain_sec = int(remaining / pump.rate * 3600) if pump.rate > 0 else 0
        segs = self._msh_pid_obr(
            pump,
            'IHE_PCD_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.1^ISO',
            'ORU^R01^ORU_R01',
            f'DEMO01^{pump.drug}',
        )
        segs += [
            f'OBX|1||70049^MDC_DEV_PUMP_INFUS_LVP_MDS^MDC|1.0.0.0|||||||X|||||||'
            f'{pump.label}^^0012210000000000^EUI-64',
            'OBX|2||70050^MDC_DEV_PUMP_INFUS_LVP_VMD^MDC|1.1.0.0|||||||X',
            f'OBX|16|ST|67972^MDC_ATTR_SYS_ID^MDC|1.1.0.9|{pump.device_id}||||||F',
            'OBX|3|ST|184520^MDC_PUMP_DRUG_LIBRARY_NAME^MDC|1.1.0.1|DEMO_LIB||||||R',
            'OBX|4|ST|67880^MDC_ATTR_ID_MODEL^MDC|1.1.0.8|B Braun SpacePlus Infusomat (Demo)||||||F',
            f'OBX|5|ST|0^MDC_ATTR_PUMP_PILLAR_DETAILS^MDC|1.1.0.24|0^0||||||F|||||||'
            f'~~{pump.ward}~{pump.facility}^^0012210000000000^EUI-64',
            'OBX|6||70067^MDC_DEV_PUMP_DELIVERY_INFO^MDC|1.1.1.0|||||||X',
            f'OBX|7|CWE|184519^MDC_PUMP_INFUSING_STATUS^MDC|1.1.1.1|^pump-status-{status}||||||R',
            f'OBX|8|NM|158014^MDC_FLOW_FLUID_PUMP_CURRENT^MDC|1.1.1.2|{rate:g}|'
            '265266^MDC_DIM_MILLI_L_PER_HR^MDC^mL/h^mL/h^UCUM|||||R',
            f'OBX|9|CWE|158005^MDC_PUMP_CURRENT_DELIVERY_STATUS^MDC|1.1.2.22|'
            f'^pump-delivery-status-{delivering}||||||R',
            f'OBX|10|NM|157884^MDC_VOL_FLUID_TBI^MDC|1.1.1.3|{pump.vtbi:g}|'
            '263762^MDC_DIM_MILLI_L^MDC^mL^mL^UCUM|||||R',
            f'OBX|11|NM|157993^MDC_VOL_FLUID_DELIV_TOTAL^MDC|1.1.1.4|{pump.infused:.2f}|'
            '263762^MDC_DIM_MILLI_L^MDC^mL^mL^UCUM|||||R',
            f'OBX|12|NM|157872^MDC_VOL_FLUID_TBI_REMAIN^MDC|1.1.1.5|{remaining:.2f}|'
            '263762^MDC_DIM_MILLI_L^MDC^mL^mL^UCUM|||||R',
            f'OBX|13|NM|157916^MDC_TIME_PD_REMAIN^MDC|1.1.1.6|{remain_sec}|'
            '264320^MDC_DIM_SEC^MDC^s^s^UCUM|||||R',
            f'OBX|14|ST|184514^MDC_DRUG_NAME_LABEL^MDC|1.1.1.7|{pump.drug}||||||R',
            f'OBX|15|NM|67996^MDC_ATTR_VAL_BATT_CHARGE^MDC|1.1.0.10|{pump.battery:.0f}|'
            '262688^MDC_DIM_PERCENT^MDC^%^%^UCUM|||||R',
        ]
        return '\r'.join(segs) + '\r'

    def _build_pcd04(self, pump, phase, state):
        ts = _ts()
        segs = self._msh_pid_obr(
            pump,
            'IHE_PCD_ACM_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.4^ISO',
            'ORU^R40^ORU_R40',
            '196616^MDC_EVT_ALARM^MDC',
        )
        segs += [
            f'OBX|1||70049^MDC_DEV_PUMP_INFUS_LVP_MDS^MDC|1.0.0.0|||||||X|||||||'
            f'{pump.label}^^0012210000000000^EUI-64',
            'OBX|2||70050^MDC_DEV_PUMP_INFUS_LVP_VMD^MDC|1.1.0.0|||||||X',
            f'OBX|9|ST|67972^MDC_ATTR_SYS_ID^MDC|1.1.0.9|{pump.device_id}||||||F',
            f'OBX|3|CWE|196616^MDC_EVT_ALARM^MDC|1.1.0.0.1|'
            f'196670^MDC_EVT_PUMP_GENERAL_ALARM^MDC||||||F|||{ts}',
            'OBX|4|ST|68481^MDC_ATTR_EVENT_PHASE^MDC|1.1.0.0.3|' + phase + '||||||F',
            'OBX|5|ST|68482^MDC_ATTR_ALARM_STATE^MDC|1.1.0.0.4|' + state + '||||||F',
            'OBX|6|ST|68484^MDC_ATTR_ALARM_PRIORITY^MDC|1.1.0.0.6|PH||||||F',
            'OBX|7|ST|68485^MDC_ATTR_ALERT_TYPE^MDC|1.1.0.0.7|ST||||||F',
            f'OBX|8|ST|68546^MDC_ATTR_ALERT_TEXT^MDC|1.1.0.0.8|'
            f'{pump.alarm_text or "Pump Alarm"}||||||F',
        ]
        return '\r'.join(segs) + '\r'
