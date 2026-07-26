"""Storage for the infusion engine - PostgreSQL (production) or SQLite (fallback).

Backend selection: if INFUSION_DB_HOST is set the engine uses PostgreSQL
(pg8000 driver, pure Python); otherwise it falls back to an embedded SQLite
file so local development needs nothing installed.

Four tables, identical on both backends:
  messages  - every raw HL7 message, always stored even if parsing fails
  readings  - one parsed snapshot per ORU status message
  alarms    - one row per PCD-04 alarm message
  pumps     - latest known state per pump (device_id = EUI-64 serial)
"""

import json
import logging
import os
import sqlite3
import threading
from datetime import datetime, timedelta

try:
    import pg8000.dbapi as pg_driver
except ImportError:
    pg_driver = None  # SQLite-only mode

logger = logging.getLogger('infusion.db')

# {AUTOINC} is replaced per backend. All timestamps are ISO-8601 TEXT.
SCHEMA = """
CREATE TABLE IF NOT EXISTS messages (
    id {AUTOINC},
    received_at TEXT NOT NULL,
    source_ip TEXT,
    device_id TEXT,
    station_id TEXT,
    message_type TEXT,
    trigger_event TEXT,
    pcd_profile TEXT,
    message_control_id TEXT,
    message_datetime TEXT,
    patient_mrn TEXT,
    patient_name TEXT,
    parse_status TEXT NOT NULL DEFAULT 'pending',
    parse_error TEXT,
    raw TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_messages_device ON messages(device_id);
CREATE INDEX IF NOT EXISTS idx_messages_received ON messages(received_at);

CREATE TABLE IF NOT EXISTS readings (
    id {AUTOINC},
    message_id BIGINT NOT NULL,
    device_id TEXT,
    pump_label TEXT,
    patient_mrn TEXT,
    patient_name TEXT,
    observed_at TEXT,
    pump_status TEXT,
    delivery_status TEXT,
    not_delivering_reason TEXT,
    flow_rate DOUBLE PRECISION,
    flow_rate_unit TEXT,
    vtbi DOUBLE PRECISION,
    volume_infused DOUBLE PRECISION,
    volume_remaining DOUBLE PRECISION,
    time_remaining_sec DOUBLE PRECISION,
    medication TEXT,
    drug_name TEXT,
    battery_percent DOUBLE PRECISION,
    power_status TEXT,
    observations TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_readings_device ON readings(device_id, observed_at);
CREATE INDEX IF NOT EXISTS idx_readings_mrn ON readings(patient_mrn);

CREATE TABLE IF NOT EXISTS alarms (
    id {AUTOINC},
    message_id BIGINT NOT NULL,
    device_id TEXT,
    pump_label TEXT,
    observed_at TEXT,
    event_code TEXT,
    event_name TEXT,
    phase TEXT,
    state TEXT,
    inactivation TEXT,
    priority TEXT,
    alert_type TEXT,
    alert_text TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_alarms_device ON alarms(device_id, observed_at);

CREATE TABLE IF NOT EXISTS pumps (
    device_id TEXT PRIMARY KEY,
    station_id TEXT,
    pump_label TEXT,
    pump_model TEXT,
    pump_type TEXT,
    device_uuid TEXT,
    firmware_version TEXT,
    drug_library TEXT,
    care_area TEXT,
    ward TEXT,
    facility TEXT,
    patient_mrn TEXT,
    patient_name TEXT,
    medication TEXT,
    drug_name TEXT,
    pump_status TEXT,
    delivery_status TEXT,
    not_delivering_reason TEXT,
    flow_rate DOUBLE PRECISION,
    flow_rate_unit TEXT,
    vtbi DOUBLE PRECISION,
    volume_infused DOUBLE PRECISION,
    volume_remaining DOUBLE PRECISION,
    time_remaining_sec DOUBLE PRECISION,
    battery_percent DOUBLE PRECISION,
    power_status TEXT,
    device_ip TEXT,
    wifi_ssid TEXT,
    active_alarm TEXT,
    alarm_priority TEXT,
    alarm_since TEXT,
    last_observed_at TEXT,
    last_message_id BIGINT,
    last_seen_at TEXT,
    message_count BIGINT NOT NULL DEFAULT 0
)
"""

# pumps columns updated from each parsed message with "keep old value if new is NULL"
_PUMP_STATE_COLS = [
    'station_id', 'pump_label', 'pump_model', 'pump_type', 'device_uuid', 'firmware_version',
    'drug_library', 'care_area', 'ward', 'facility',
    'patient_mrn', 'patient_name', 'medication', 'drug_name',
    'pump_status', 'delivery_status', 'not_delivering_reason',
    'flow_rate', 'flow_rate_unit', 'vtbi', 'volume_infused', 'volume_remaining',
    'time_remaining_sec', 'battery_percent', 'power_status', 'device_ip', 'wifi_ssid',
]

_MIGRATED_COLUMNS = {  # columns added after the first release: table -> [(col, type)]
    'readings': [('patient_mrn', 'TEXT'), ('patient_name', 'TEXT')],
    'messages': [('station_id', 'TEXT')],
    'pumps': [('station_id', 'TEXT')],
}


def _now():
    return datetime.now().astimezone().isoformat(timespec='seconds')


class Database:
    """One shared connection guarded by a lock; auto-reconnects on Postgres."""

    def __init__(self, sqlite_path=None, pg=None):
        self.driver = 'postgres' if pg else 'sqlite'
        if self.driver == 'postgres' and pg_driver is None:
            raise RuntimeError('INFUSION_DB_HOST is set but pg8000 is not installed')
        self._pg = pg
        self._sqlite_path = sqlite_path
        self._lock = threading.RLock()
        self._conn = None
        self._connect()
        self._init_schema()
        logger.info('Database ready (%s)', self.describe())

    def describe(self):
        if self.driver == 'postgres':
            return (f"postgresql://{self._pg['user']}@{self._pg['host']}:"
                    f"{self._pg['port']}/{self._pg['database']}")
        return f'sqlite:{self._sqlite_path}'

    # ------------------------------------------------------------ connection

    def _connect(self):
        if self._conn is not None:
            try:
                self._conn.close()
            except Exception:
                pass
        if self.driver == 'postgres':
            self._conn = pg_driver.connect(
                host=self._pg['host'], port=self._pg['port'],
                database=self._pg['database'], user=self._pg['user'],
                password=self._pg['password'], timeout=10,
            )
        else:
            os.makedirs(os.path.dirname(os.path.abspath(self._sqlite_path)), exist_ok=True)
            self._conn = sqlite3.connect(self._sqlite_path, check_same_thread=False)
            self._conn.execute('PRAGMA journal_mode=WAL')
            self._conn.execute('PRAGMA synchronous=NORMAL')

    def close(self):
        with self._lock:
            try:
                self._conn.close()
            except Exception:
                pass

    def _sql(self, sql):
        return sql.replace('?', '%s') if self.driver == 'postgres' else sql

    def _run(self, fn):
        """Run fn under the lock; on a dropped Postgres connection retry once."""
        with self._lock:
            try:
                return fn()
            except Exception as first_error:
                try:
                    self._conn.rollback()
                except Exception:
                    pass
                if self.driver != 'postgres':
                    raise
                logger.warning('Postgres error, reconnecting and retrying: %s', first_error)
                try:
                    self._connect()
                    return fn()
                except Exception:
                    raise first_error from None

    def _query(self, sql, params=(), one=False):
        def fn():
            cur = self._conn.cursor()
            try:
                cur.execute(self._sql(sql), tuple(params))
                cols = [d[0] for d in cur.description]
                if one:
                    row = cur.fetchone()
                    return dict(zip(cols, row)) if row else None
                return [dict(zip(cols, r)) for r in cur.fetchall()]
            finally:
                cur.close()
                if self.driver == 'postgres':
                    # End the implicit read transaction - otherwise the shared
                    # connection sits "idle in transaction" and blocks DDL/vacuum
                    try:
                        self._conn.rollback()
                    except Exception:
                        pass
        return self._run(fn)

    def _write(self, sql, params=(), returning=False):
        def fn():
            cur = self._conn.cursor()
            try:
                cur.execute(self._sql(sql), tuple(params))
                result = cur.fetchone()[0] if returning else cur.rowcount
                self._conn.commit()
                return result
            finally:
                cur.close()
        return self._run(fn)

    # ---------------------------------------------------------------- schema

    def _init_schema(self):
        autoinc = ('BIGSERIAL PRIMARY KEY' if self.driver == 'postgres'
                   else 'INTEGER PRIMARY KEY AUTOINCREMENT')
        ddl = SCHEMA.replace('{AUTOINC}', autoinc)

        def fn():
            cur = self._conn.cursor()
            try:
                for statement in ddl.split(';'):
                    if statement.strip():
                        cur.execute(statement)
                self._conn.commit()
            finally:
                cur.close()
        self._run(fn)
        self._migrate()

    def _migrate(self):
        """Add columns introduced after the first release to existing databases."""
        for table, columns in _MIGRATED_COLUMNS.items():
            if self.driver == 'postgres':
                for col, ctype in columns:
                    self._write(f'ALTER TABLE {table} ADD COLUMN IF NOT EXISTS {col} {ctype}')
            else:
                existing = {r['name'] for r in self._query(f'PRAGMA table_info({table})')}
                for col, ctype in columns:
                    if col not in existing:
                        self._write(f'ALTER TABLE {table} ADD COLUMN {col} {ctype}')

    # ------------------------------------------------------------------ writes

    def store_raw_message(self, raw, source_ip):
        """Store the raw HL7 immediately; parsing details are filled in after."""
        return self._write(
            'INSERT INTO messages (received_at, source_ip, raw) VALUES (?, ?, ?) RETURNING id',
            (_now(), source_ip, raw), returning=True,
        )

    def mark_parse_error(self, message_id, error):
        self._write(
            'UPDATE messages SET parse_status=?, parse_error=? WHERE id=?',
            ('error', str(error)[:500], message_id),
        )

    def save_parsed(self, message_id, msg):
        """Persist a ParsedMessage: message metadata, reading/alarm, pump state."""
        v = msg.values

        def fn():
            cur = self._conn.cursor()
            try:
                cur.execute(self._sql(
                    '''UPDATE messages SET device_id=?, station_id=?, message_type=?,
                       trigger_event=?, pcd_profile=?, message_control_id=?,
                       message_datetime=?, patient_mrn=?, patient_name=?,
                       parse_status=? WHERE id=?'''),
                    (msg.device_id, msg.station_id, msg.message_type, msg.trigger_event,
                     msg.pcd_profile, msg.message_control_id, msg.message_datetime,
                     msg.patient_mrn, msg.patient_name, 'parsed', message_id))

                if msg.alarm:
                    a = msg.alarm
                    cur.execute(self._sql(
                        '''INSERT INTO alarms (message_id, device_id, pump_label, observed_at,
                           event_code, event_name, phase, state, inactivation, priority,
                           alert_type, alert_text, created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'''),
                        (message_id, msg.device_id, msg.pump_label, a['observed_at'],
                         a['event_code'], a['event_name'], a['phase'], a['state'],
                         a['inactivation'], a['priority'], a['alert_type'],
                         a['alert_text'], _now()))
                elif msg.is_oru and msg.observations:
                    cur.execute(self._sql(
                        '''INSERT INTO readings (message_id, device_id, pump_label,
                           patient_mrn, patient_name, observed_at,
                           pump_status, delivery_status, not_delivering_reason,
                           flow_rate, flow_rate_unit, vtbi, volume_infused,
                           volume_remaining, time_remaining_sec, medication, drug_name,
                           battery_percent, power_status, observations, created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'''),
                        (message_id, msg.device_id, msg.pump_label,
                         msg.patient_mrn, msg.patient_name, msg.observed_at,
                         v.get('pump_status'), v.get('delivery_status'),
                         v.get('not_delivering_reason'),
                         _num(v.get('flow_rate')), msg.units.get('flow_rate'),
                         _num(v.get('vtbi')), _num(v.get('volume_infused')),
                         _num(v.get('volume_remaining')), _num(v.get('time_remaining_sec')),
                         msg.medication, v.get('drug_name'), _num(v.get('battery_percent')),
                         v.get('power_status'), json.dumps(msg.observations), _now()))

                if msg.device_id:
                    self._upsert_pump(cur, message_id, msg)
                self._conn.commit()
            finally:
                cur.close()
        self._run(fn)

    def _upsert_pump(self, cur, message_id, msg):
        v = msg.values
        state = {
            'station_id': msg.station_id,
            'pump_label': msg.pump_label,
            'pump_model': v.get('pump_model'),
            'pump_type': msg.pump_type,
            'device_uuid': v.get('device_uuid'),
            'firmware_version': v.get('firmware_version'),
            'drug_library': v.get('drug_library'),
            'care_area': v.get('care_area'),
            'ward': msg.ward,
            'facility': msg.facility,
            'patient_mrn': msg.patient_mrn,
            'patient_name': msg.patient_name,
            'medication': msg.medication,
            'drug_name': v.get('drug_name'),
            'pump_status': v.get('pump_status'),
            'delivery_status': v.get('delivery_status'),
            'not_delivering_reason': v.get('not_delivering_reason'),
            'flow_rate': _num(v.get('flow_rate')),
            'flow_rate_unit': msg.units.get('flow_rate'),
            'vtbi': _num(v.get('vtbi')),
            'volume_infused': _num(v.get('volume_infused')),
            'volume_remaining': _num(v.get('volume_remaining')),
            'time_remaining_sec': _num(v.get('time_remaining_sec')),
            'battery_percent': _num(v.get('battery_percent')),
            'power_status': v.get('power_status'),
            'device_ip': v.get('device_ip'),
            'wifi_ssid': v.get('wifi_ssid'),
        }
        cols = ', '.join(state.keys())
        placeholders = ', '.join('?' for _ in state)
        updates = ', '.join(
            f'{c}=COALESCE(excluded.{c}, pumps.{c})' for c in _PUMP_STATE_COLS
        )
        cur.execute(self._sql(
            f'''INSERT INTO pumps (device_id, {cols}, last_observed_at,
                last_message_id, last_seen_at, message_count)
                VALUES (?, {placeholders}, ?, ?, ?, 1)
                ON CONFLICT(device_id) DO UPDATE SET
                {updates},
                last_observed_at=COALESCE(excluded.last_observed_at, pumps.last_observed_at),
                last_message_id=excluded.last_message_id,
                last_seen_at=excluded.last_seen_at,
                message_count=pumps.message_count+1'''),
            (msg.device_id, *state.values(), msg.observed_at, message_id, _now()))

        if msg.alarm:
            a = msg.alarm
            if a['state'] == 'active' and a['phase'] != 'end':
                cur.execute(self._sql(
                    '''UPDATE pumps SET active_alarm=?, alarm_priority=?,
                       alarm_since=COALESCE(alarm_since, ?) WHERE device_id=?'''),
                    (a['alert_text'] or a['event_name'], a['priority'],
                     a['observed_at'], msg.device_id))
            else:
                cur.execute(self._sql(
                    '''UPDATE pumps SET active_alarm=NULL, alarm_priority=NULL,
                       alarm_since=NULL WHERE device_id=?'''),
                    (msg.device_id,))

    def purge_older_than(self, days):
        """Delete messages/readings/alarms older than N days. Returns rows removed."""
        cutoff = (datetime.now().astimezone() - timedelta(days=days)).isoformat()
        total = 0
        for table, col in (('messages', 'received_at'),
                           ('readings', 'created_at'),
                           ('alarms', 'created_at')):
            total += self._write(f'DELETE FROM {table} WHERE {col} < ?', (cutoff,))
        return total

    # ------------------------------------------------------------------- reads

    def list_pumps(self):
        return self._query('SELECT * FROM pumps ORDER BY ward, pump_label')

    def get_pump(self, device_id):
        return self._query('SELECT * FROM pumps WHERE device_id=?', (device_id,), one=True)

    def get_patient(self, mrn, limit=50):
        """Infusion info for one patient MRN: pumps, readings, alarms."""
        pumps = self._query('SELECT * FROM pumps WHERE patient_mrn=?', (mrn,))
        readings = self.list_readings(mrn=mrn, limit=limit)
        device_ids = {p['device_id'] for p in pumps} | {r['device_id'] for r in readings}
        alarms = []
        if device_ids:
            marks = ','.join('?' for _ in device_ids)
            alarms = self._query(
                f'SELECT * FROM alarms WHERE device_id IN ({marks}) '
                'ORDER BY id DESC LIMIT ?', (*device_ids, limit))
        if not pumps and not readings:
            return None
        name = pumps[0]['patient_name'] if pumps else readings[0]['patient_name']
        return {
            'mrn': mrn,
            'patient_name': name,
            'pumps': pumps,
            'readings': readings,
            'alarms': alarms,
        }

    def list_readings(self, device_id=None, mrn=None, since=None, limit=100, offset=0):
        sql = 'SELECT * FROM readings WHERE 1=1'
        params = []
        if device_id:
            sql += ' AND device_id=?'
            params.append(device_id)
        if mrn:
            sql += ' AND patient_mrn=?'
            params.append(mrn)
        if since:
            sql += ' AND observed_at >= ?'
            params.append(since)
        sql += ' ORDER BY id DESC LIMIT ? OFFSET ?'
        params += [limit, offset]
        rows = self._query(sql, params)
        for r in rows:
            if r.get('observations'):
                r['observations'] = json.loads(r['observations'])
        return rows

    def list_alarms(self, device_id=None, state=None, limit=100, offset=0):
        sql = 'SELECT * FROM alarms WHERE 1=1'
        params = []
        if device_id:
            sql += ' AND device_id=?'
            params.append(device_id)
        if state:
            sql += ' AND state=?'
            params.append(state)
        sql += ' ORDER BY id DESC LIMIT ? OFFSET ?'
        params += [limit, offset]
        return self._query(sql, params)

    def list_messages(self, device_id=None, trigger_event=None, since=None,
                      limit=100, offset=0, include_raw=False):
        cols = '*' if include_raw else (
            'id, received_at, source_ip, device_id, message_type, trigger_event, '
            'pcd_profile, message_control_id, message_datetime, patient_mrn, '
            'patient_name, parse_status, parse_error, length(raw) AS raw_length'
        )
        sql = f'SELECT {cols} FROM messages WHERE 1=1'
        params = []
        if device_id:
            sql += ' AND device_id=?'
            params.append(device_id)
        if trigger_event:
            sql += ' AND trigger_event=?'
            params.append(trigger_event)
        if since:
            sql += ' AND received_at >= ?'
            params.append(since)
        sql += ' ORDER BY id DESC LIMIT ? OFFSET ?'
        params += [limit, offset]
        return self._query(sql, params)

    def get_message(self, message_id):
        return self._query('SELECT * FROM messages WHERE id=?', (message_id,), one=True)

    def stats(self):
        counts = {}
        for table in ('messages', 'readings', 'alarms', 'pumps'):
            row = self._query(f'SELECT COUNT(*) AS n FROM {table}', one=True)
            counts[table] = row['n'] if row else 0
        last = self._query(
            'SELECT received_at FROM messages ORDER BY id DESC LIMIT 1', one=True)
        counts['last_message_at'] = last['received_at'] if last else None
        counts['database'] = self.describe()
        return counts


def _num(value):
    if isinstance(value, (int, float)):
        return value
    return None
