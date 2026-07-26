"""HL7 v2.x parser for infusion pump messages (B.Braun SpacePlus / IHE PCD).

Handles the message types the pumps actually send:
  - ORU^R01 (IHE PCD-01)  periodic infusion status
  - ORU^R42 (IHE PCD-10)  infusion events (start/stop of a delivery)
  - ORU^R40 (IHE PCD-04)  alarms / alerts
  - ORU^R01 (IHE PCD-15)  device memory reports (battery etc.)

Everything is keyed off the MDC observation codes in the OBX segments.
"""

import re
from datetime import datetime, timezone, timedelta

# MDC observation code -> friendly field name.
# Same mapping the bbraun listener uses, kept in sync so both agree on names.
MDC_CODES = {
    # Pump status
    '184519': 'pump_status',           # MDC_PUMP_INFUSING_STATUS
    '158014': 'flow_rate',             # MDC_FLOW_FLUID_PUMP_CURRENT (mL/h)
    '157784': 'flow_rate_programmed',  # MDC_FLOW_FLUID_PUMP
    '158005': 'delivery_status',       # MDC_PUMP_CURRENT_DELIVERY_STATUS
    '158006': 'not_delivering_reason', # MDC_PUMP_NOT_DELIVERING_REASON
    '158008': 'delivery_mode',         # MDC_PUMP_PROGRAM_DELIVERY_MODE
    '158012': 'channel_label',         # MDC_PUMP_SOURCE_CHANNEL_LABEL

    # Volumes
    '157884': 'vtbi',                  # MDC_VOL_FLUID_TBI (volume to be infused)
    '157872': 'volume_remaining',      # MDC_VOL_FLUID_TBI_REMAIN
    '157993': 'volume_infused',        # MDC_VOL_FLUID_DELIV_TOTAL
    '157992': 'segment_volume',        # MDC_VOL_FLUID_DELIV_SEGMENT

    # Times (seconds)
    '157996': 'programmed_time_sec',   # MDC_TIME_PD_PROG
    '157916': 'time_remaining_sec',    # MDC_TIME_PD_REMAIN
    '157997': 'time_remaining_sec',    # MDC_TIME_PD_REMAIN_CONTAINER (syringe)

    # Drug
    '184514': 'drug_name',             # MDC_DRUG_NAME_LABEL
    '157760': 'drug_concentration',    # MDC_CONC_DRUG
    '184520': 'drug_library',          # MDC_PUMP_DRUG_LIBRARY_NAME
    '184516': 'care_area',             # MDC_PUMP_DRUG_LIBRARY_CARE_AREA

    # Dose
    '157999': 'dose_tbi',              # MDC_DOSE_DRUG_TBI
    '158000': 'dose_remaining',        # MDC_DOSE_DRUG_TBI_REMAIN
    '158001': 'dose_delivered',        # MDC_DOSE_DRUG_DELIV_TOTAL

    # Device identity
    '67880': 'pump_model',             # MDC_ATTR_ID_MODEL
    '67972': 'device_uuid',            # MDC_ATTR_SYS_ID
    '531976': 'firmware_version',      # MDC_ID_PROD_SPEC_FW

    # Syringe
    '157880': 'syringe_size',          # MDC_VOL_SYRINGE
    '157984': 'syringe_actual_vol',    # MDC_VOL_SYRINGE_ACTUAL
    '184488': 'syringe_manufacturer',  # MDC_SYRINGE_MANUFACTURER

    # Power / battery
    '67925': 'power_status',           # MDC_ATTR_POWER_STAT (onBattery/onMains)
    '67996': 'battery_percent',        # MDC_ATTR_VAL_BATT_CHARGE
    '67976': 'battery_time_min',       # MDC_ATTR_TIME_BATT_REMAIN
    '68020': 'battery_status',         # MDC_ATTR_BATT_STAT
    '68023': 'battery_capacity',       # MDC_ATTR_CAPAC_BATT_FULL

    # Network
    '69408': 'wifi_state',             # MDC_NCC_WIRELESS_STATE
    '69410': 'device_ip',              # MDC_NCC_WIRELESS_DEVICE_IPV4_ADDR
    '69416': 'device_mac',             # MDC_NCC_WIRELESS_MAC
    '69417': 'wifi_ssid',              # MDC_NCC_WIRELESS_SSID
    '69425': 'wifi_strength',          # MDC_NCC_WIRELESS_STRENGTH_PERCENT

    # Alarm attributes (PCD-04)
    '196616': 'alarm_event',           # MDC_EVT_ALARM
    '68012': 'alarm_condition',        # MDC_ATTR_AL_COND
    '68480': 'alert_source',           # MDC_ATTR_ALERT_SOURCE
    '68481': 'event_phase',            # MDC_ATTR_EVENT_PHASE (start/update/end)
    '68482': 'alarm_state',            # MDC_ATTR_ALARM_STATE (active/inactive)
    '68483': 'alarm_inactivation',     # MDC_ATTR_ALARM_INACTIVATION_STATE
    '68484': 'alarm_priority',         # MDC_ATTR_ALARM_PRIORITY (PH/PM/PL/ST)
    '68485': 'alert_type',             # MDC_ATTR_ALERT_TYPE
    '68546': 'alert_text',             # MDC_ATTR_ALERT_TEXT

    # Events (PCD-10)
    '68487': 'event_condition',        # MDC_ATTR_EVT_COND
    '68488': 'event_source',           # MDC_ATTR_EVT_SOURCE

    # Patient
    '68063': 'patient_weight',         # MDC_ATTR_PT_WEIGHT (kg)
}

# Pump-type from the MDS-level device code (containment 1.0.0.0)
MDS_PUMP_TYPES = {
    '70049': 'lvp',        # MDC_DEV_PUMP_INFUS_LVP_MDS (Infusomat - large volume)
    '70053': 'syringe',    # MDC_DEV_PUMP_INFUS_SYRINGE_MDS (Perfusor)
    '69986': 'pump',       # MDC_DEV_PUMP_INFUS_VMD (generic)
}

# Location attribute OBX (value in OBX-18 facility component)
PILLAR_DETAILS_NAME = 'MDC_ATTR_PUMP_PILLAR_DETAILS'

_SEGMENT_RE = re.compile(r'^[A-Z][A-Z0-9]{2}$')


def _field(fields, index, default=''):
    return fields[index] if len(fields) > index else default


def _comp(value, index, default=''):
    parts = value.split('^')
    return parts[index] if len(parts) > index else default


def parse_hl7_timestamp(ts):
    """'20260219075836+0000' -> ISO 8601 string, or None."""
    if not ts:
        return None
    ts = ts.strip()
    m = re.match(r'^(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?(?:\.\d+)?([+-]\d{4})?$', ts)
    if not m:
        return None
    year, month, day, hour, minute, second, offset = m.groups()
    try:
        dt = datetime(int(year), int(month), int(day),
                      int(hour or 0), int(minute or 0), int(second or 0))
    except ValueError:
        return None
    if offset:
        sign = 1 if offset[0] == '+' else -1
        tz = timezone(sign * timedelta(hours=int(offset[1:3]), minutes=int(offset[3:5])))
        dt = dt.replace(tzinfo=tz)
    return dt.isoformat()


def split_segments(raw):
    """Split raw HL7 into segment field-lists. Tolerates \n and wrapped lines."""
    lines = []
    for line in raw.replace('\r\n', '\r').replace('\n', '\r').split('\r'):
        line = line.rstrip()
        if not line:
            continue
        seg_name = line.split('|', 1)[0]
        if _SEGMENT_RE.match(seg_name):
            lines.append(line)
        elif lines:
            # Continuation of a wrapped line (e.g. copy/paste artifacts)
            lines[-1] += line
    return [line.split('|') for line in lines]


def _strip_prefix(value, *prefixes):
    for p in prefixes:
        if value.startswith(p):
            return value[len(p):]
    return value


def _cwe_text(value):
    """CWE like '^pump-status-not-infusing' or '197218^MDC_EVT_X^MDC' -> best text."""
    comps = value.split('^')
    if len(comps) > 1 and comps[1]:
        return comps[1]
    return comps[0]


def _unit_text(obx_units):
    """OBX-6 like '265266^MDC_DIM_MILLI_L_PER_HR^MDC^mL/h^mL/h^UCUM' -> 'mL/h'."""
    if not obx_units:
        return None
    comps = obx_units.split('^')
    for idx in (3, 4):  # UCUM print names
        if len(comps) > idx and comps[idx]:
            return comps[idx]
    if len(comps) > 1 and comps[1]:
        return comps[1]
    return comps[0] or None


class ParsedMessage:
    """Result of parsing one HL7 message."""

    def __init__(self):
        self.message_type = None        # 'ORU^R01^ORU_R01'
        self.trigger_event = None       # 'R01' / 'R40' / 'R42'
        self.pcd_profile = None         # 'IHE_PCD_001' ...
        self.message_control_id = None
        self.message_datetime = None    # ISO
        self.version = None
        self.sending_application = None
        self.vendor = None              # MSH-4
        self.station_id = None          # EUI-64 from MSH-3 (SpaceStation/gateway)
        self.device_id = None           # unique PER-PUMP key, resolved after OBX parse
        self.patient_mrn = None
        self.patient_name = None
        self.observed_at = None         # OBR-7 ISO
        self.medication = None          # OBR-4 text
        self.medication_code = None
        self.pump_label = None          # e.g. I51559 / P2449
        self.pump_type = None           # lvp / syringe
        self.ward = None
        self.facility = None
        self.values = {}                # field_name -> value (flat, last wins)
        self.units = {}                 # field_name -> unit text
        self.observations = []          # every parsed OBX as dict
        self.alarm = None               # dict for PCD-04 messages
        self.is_oru = False

    def to_dict(self):
        return {k: v for k, v in self.__dict__.items()}


def parse_message(raw):
    """Parse a raw HL7 string into a ParsedMessage. Raises ValueError if no MSH."""
    msg = ParsedMessage()
    segments = split_segments(raw)
    if not segments or segments[0][0] != 'MSH':
        raise ValueError('Message does not start with MSH segment')

    obx_list = []
    for fields in segments:
        seg = fields[0]
        if seg == 'MSH':
            _parse_msh(msg, fields)
        elif seg == 'PID':
            _parse_pid(msg, fields)
        elif seg == 'OBR':
            _parse_obr(msg, fields)
        elif seg == 'OBX':
            obx_list.append(fields)

    for fields in obx_list:
        _parse_obx(msg, fields)

    # Resolve the per-pump identity. The MSH-3 EUI-64 identifies the
    # SpaceStation/gateway - several pumps can share it - so prefer the pump's
    # own system id (MDC_ATTR_SYS_ID), then station+equipment label.
    uuid = msg.values.get('device_uuid')
    if uuid:
        msg.device_id = uuid
    elif msg.pump_label and msg.station_id:
        msg.device_id = f'{msg.station_id}:{msg.pump_label}'
    elif msg.pump_label:
        msg.device_id = msg.pump_label
    else:
        msg.device_id = msg.station_id

    if msg.trigger_event == 'R40':
        msg.alarm = _build_alarm(msg)

    return msg


def _parse_msh(msg, fields):
    # MSH is special: fields[1] is the encoding chars, so MSH-n = fields[n-1]
    sending_app = _field(fields, 2)
    msg.sending_application = _comp(sending_app, 0)
    msg.station_id = _comp(sending_app, 1) or None  # EUI-64 of the station/gateway
    msg.vendor = _field(fields, 3)
    msg.message_datetime = parse_hl7_timestamp(_field(fields, 6))
    msg.message_type = _field(fields, 8)
    msg.trigger_event = _comp(msg.message_type, 1)
    msg.message_control_id = _field(fields, 9)
    msg.version = _field(fields, 11)
    msg.pcd_profile = _comp(_field(fields, 20), 0) or None
    msg.is_oru = msg.message_type.startswith('ORU')


def _parse_pid(msg, fields):
    mrn_field = _field(fields, 3)
    mrn = _comp(mrn_field, 0)
    if mrn and mrn.lower() != 'unknown patient':
        msg.patient_mrn = mrn
    name_field = _field(fields, 5)
    comps = name_field.split('^')
    family = comps[0] if len(comps) > 0 else ''
    given = comps[1] if len(comps) > 1 else ''
    if family and given and family != given:
        msg.patient_name = f'{given} {family}'.strip()
    else:
        msg.patient_name = family or given or None
    if mrn and not msg.patient_mrn and mrn.lower() == 'unknown patient':
        msg.patient_name = msg.patient_name or 'Unknown Patient'


def _parse_obr(msg, fields):
    med = _field(fields, 4)
    code, name = _comp(med, 0), _comp(med, 1)
    # OBR-4 is the medication for infusion reports; for alarm/event/memory
    # reports it is the report type (MDC_EVT_ALARM / MDC_OBS_MEM) - skip those.
    if name and not name.startswith('MDC_'):
        msg.medication = name
        msg.medication_code = code or None
    msg.observed_at = parse_hl7_timestamp(_field(fields, 7)) or msg.message_datetime


def _parse_obx(msg, fields):
    code_field = _field(fields, 3)
    code = _comp(code_field, 0)
    code_name = _comp(code_field, 1)
    value_type = _field(fields, 2)
    channel = _field(fields, 4)
    raw_value = _field(fields, 5)
    equipment = _field(fields, 18)

    # Device-structure OBX (MDS/VMD): grab pump type and label, no value
    if code in MDS_PUMP_TYPES:
        if MDS_PUMP_TYPES[code] != 'pump' or not msg.pump_type:
            msg.pump_type = MDS_PUMP_TYPES[code]
        label = _comp(equipment, 0)
        if label:
            msg.pump_label = label
        return

    # Location is hidden in the pillar-details OBX-18: '~~WARD D5~PHKL^^...'
    if code_name == PILLAR_DETAILS_NAME:
        place = _comp(equipment, 0)
        parts = place.split('~')
        if len(parts) >= 4:
            msg.ward = parts[2] or None
            msg.facility = parts[3] or None
        elif len(parts) >= 3:
            msg.ward = parts[2] or None
        return

    field_name = MDC_CODES.get(code)
    if not field_name or not raw_value:
        return

    # Decode the value by HL7 type
    if value_type == 'NM':
        try:
            value = float(raw_value.split('^')[0])
        except ValueError:
            value = raw_value
    elif value_type == 'CWE':
        value = _cwe_text(raw_value)
    else:  # ST and friends
        value = raw_value

    unit = _unit_text(_field(fields, 6))
    obx_ts = parse_hl7_timestamp(_field(fields, 14))

    if field_name == 'pump_status' and isinstance(value, str):
        value = _strip_prefix(value, 'pump-status-')
    elif field_name == 'delivery_status' and isinstance(value, str):
        value = _strip_prefix(value, 'pump-delivery-status-')
    elif field_name == 'alarm_event' and isinstance(value, str):
        # keep the MDC event name, e.g. MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT
        msg.values['alarm_event_code'] = raw_value.split('^')[0]
        if obx_ts:
            msg.values['alarm_observed_at'] = obx_ts

    msg.values[field_name] = value
    if unit:
        msg.units[field_name] = unit
    msg.observations.append({
        'code': code,
        'name': code_name,
        'field': field_name,
        'channel': channel,
        'value': value,
        'unit': unit,
        'observed_at': obx_ts,
    })


def _build_alarm(msg):
    v = msg.values
    if 'alarm_event' not in v and 'alert_text' not in v:
        return None
    return {
        'event_code': v.get('alarm_event_code'),
        'event_name': v.get('alarm_event'),
        'phase': v.get('event_phase'),           # start / update / end
        'state': v.get('alarm_state'),           # active / inactive
        'inactivation': v.get('alarm_inactivation'),
        'priority': v.get('alarm_priority'),     # PH / PM / PL / ST
        'alert_type': v.get('alert_type'),
        'alert_text': v.get('alert_text'),
        'observed_at': v.get('alarm_observed_at') or msg.observed_at,
    }


def build_ack(raw_or_msg, ack_code='AA', text='Message received'):
    """Build an HL7 ACK for a received message (ParsedMessage or raw string)."""
    control_id = ''
    sending_app = ''
    sending_fac = ''
    version = '2.6'
    if isinstance(raw_or_msg, ParsedMessage):
        control_id = raw_or_msg.message_control_id or ''
        sending_app = raw_or_msg.sending_application or ''
        sending_fac = raw_or_msg.vendor or ''
        version = raw_or_msg.version or version
    else:
        try:
            fields = split_segments(raw_or_msg)[0]
            sending_app = _comp(_field(fields, 2), 0)
            sending_fac = _field(fields, 3)
            control_id = _field(fields, 9)
            version = _field(fields, 11) or version
        except Exception:
            pass
    timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
    return (
        f'MSH|^~\\&|INFUSION_ENGINE|INFUSION_ENGINE|{sending_app}|{sending_fac}|'
        f'{timestamp}||ACK|{timestamp}|P|{version}\r'
        f'MSA|{ack_code}|{control_id}|{text}\r'
    )
