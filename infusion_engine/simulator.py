#!/usr/bin/env python3
"""B.Braun HL7 scenario simulator for the infusion engine.

One tool that replays realistic B.Braun SpacePlus traffic - PCD-01 status,
PCD-10 delivery events, PCD-04 alarms and PCD-15 device reports - into the
engine's MLLP port, so the whole receive -> parse -> store -> API path can be
exercised without physical pumps.

    python simulator.py --list                  # every scenario, with a summary
    python simulator.py normal                  # run one scenario
    python simulator.py occlusion --speed 60    # 60x faster than real time
    python simulator.py multi-pump alarm-storm  # several, back to back
    python simulator.py all                     # the whole catalogue
    python simulator.py normal --dry-run        # print the HL7, send nothing
    python simulator.py normal --host 10.0.0.5 --port 6000

Segment layout, MDC codes and units are taken from the real captures in
tests/samples/ (Infusomat LVP + Perfusor syringe, alarm sequence, PCD-15
battery/wifi reports), so what the engine sees here is what a ward sends.

The simulator keeps its own clock: every message is stamped with *simulated*
time, and --speed only changes how long the run takes in wall-clock seconds.
That means a one-hour infusion can be replayed in a minute with timestamps and
volume arithmetic that still line up.
"""

import argparse
import os
import random
import socket
import sys
import time
import uuid
from datetime import datetime, timedelta, timezone

MLLP_START_BLOCK = b'\x0b'
MLLP_END_BLOCK = b'\x1c'
MLLP_CARRIAGE_RETURN = b'\x0d'

DEFAULT_HOST = os.getenv('INFUSION_SIM_HOST', '127.0.0.1')
DEFAULT_PORT = int(os.getenv('INFUSION_MLLP_PORT', '6000'))

# EUI-64s from the real captures: MSH-3 is the SpaceStation the pumps sit in,
# OBX-18 carries the equipment-instance id of the rack.
STATION_EUI = '0012211839000001'
EQUIPMENT_EUI = '0012210000000000'

PROFILE_PCD01 = 'IHE_PCD_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.1^ISO'
PROFILE_PCD04 = 'IHE_PCD_ACM_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.4^ISO'
PROFILE_PCD10 = 'IHE_PCD_010^IHE PCD^1.3.6.1.4.1.19376.1.6.4.10^ISO'
PROFILE_PCD15 = 'IHE_PCD_015^^1.3.6.1.4.1.19376.1.6.4.15^ISO'

# Units exactly as the pumps send them (code^name^MDC^print^print^UCUM)
U_ML_H = '265266^MDC_DIM_MILLI_L_PER_HR^MDC^mL/h^mL/h^UCUM'
U_ML = '263762^MDC_DIM_MILLI_L^MDC^mL^mL^UCUM'
U_SEC = '264320^MDC_DIM_X_SEC^MDC^s^s^UCUM'
U_MIN = '264352^MDC_DIM_MIN^MDC^min^min^UCUM'
U_PCT = '262688^MDC_DIM_PERCENT^MDC^%^%^UCUM'
U_UG = '263891^MDC_DIM_MICRO_G^MDC^ug^ug^UCUM'
U_MG_ML = '264306^MDC_DIM_MILLI_G_PER_ML^MDC^mg/mL^mg/mL^UCUM'
U_KG = '263875^MDC_DIM_KILO_G^MDC^kg^kg^UCUM'
U_MAH = '268242^MDC_DIM_MILLI_AMP_HR^MDC^mA.h^mA.h^UCUM'
U_MBIT = '274212^MDC_DIM_MEGA_BIT_PER_SEC^MDC^Mbit/s^Mbit/s^UCUM'

PUMP_TYPES = {
    'lvp': {
        'mds': ('70049', 'MDC_DEV_PUMP_INFUS_LVP_MDS'),
        'vmd': ('70050', 'MDC_DEV_PUMP_INFUS_LVP_VMD'),
        'model': 'B Braun SpacePlus Infusomat',
        'prefix': 'I',
        # The LVP capture uses the shorter MDC_PUMP_* spellings for these
        'names': {
            'source': 'MDC_DEV_PUMP_INFUSATE_SOURCE_PRIMARY_CHAN',
            'channel': 'MDC_PUMP_SOURCE_CHANNEL_LABEL',
            'mode': 'MDC_PUMP_PROGRAM_DELIVERY_MODE',
            'delivery': 'MDC_PUMP_CURRENT_DELIVERY_STATUS',
            'reason': 'MDC_PUMP_NOT_DELIVERING_REASON',
            'sources': 'MDC_PUMP_ACTIVE_SOURCES',
        },
    },
    'syringe': {
        'mds': ('70053', 'MDC_DEV_PUMP_INFUS_SYRINGE_MDS'),
        'vmd': ('70054', 'MDC_DEV_PUMP_INFUS_SYRINGE_VMD'),
        'model': 'B Braun SpacePlus Perfusor',
        'prefix': 'P',
        'names': {
            'source': 'MDC_DEV_PUMP_INFUSATE_SOURCE_PRIMARY',
            'channel': 'MDC_DEV_PUMP_SOURCE_CHANNEL_LABEL',
            'mode': 'MDC_DEV_PUMP_PROGRAM_DELIVERY_MODE',
            'delivery': 'MDC_DEV_PUMP_CURRENT_DELIVERY_STATUS',
            'reason': 'MDC_DEV_PUMP_NOT_DELIVERING_REASON',
            'sources': 'MDC_DEV_PUMP_ACTIVE_SOURCES',
        },
    },
}

# PCD-10 delivery events (both codes appear in the captured samples)
EVT_DELIV_START = ('197288', 'MDC_EVT_PUMP_DELIV_START')
EVT_DELIV_COMP = ('197292', 'MDC_EVT_PUMP_DELIV_COMP')
EVT_NEAR_COMP = ('197334', 'MDC_EVT_VOL_INFUS_NEAR_COMP')

# Alarm catalogue. Only the event codes that appear in the captures are used
# verbatim (syringe holder open, VTBI near end); B.Braun's per-alarm MDC codes
# for the rest are not in the samples, so those carry the generic pump alarm
# code and are told apart by MDC_ATTR_ALERT_TEXT - which is what the engine
# keys alarms off anyway.
GENERAL_ALARM = ('196670', 'MDC_EVT_PUMP_GENERAL_ALARM')

ALARMS = {
    # key: (alert text, priority, stops delivery?, event code)
    'occlusion-downstream': ('Occlusion Downstream', 'PH', True, GENERAL_ALARM),
    'occlusion-upstream': ('Occlusion Upstream', 'PH', True, GENERAL_ALARM),
    'air-in-line': ('Air In Line', 'PH', True, GENERAL_ALARM),
    'syringe-holder-open': ('Syringe Holder Open', 'PH', True,
                            ('197218', 'MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT')),
    'door-open': ('Door Open', 'PH', True, GENERAL_ALARM),
    'free-flow': ('Free Flow Protection', 'PH', True, GENERAL_ALARM),
    'drop-sensor': ('Drop Sensor Error', 'PM', True, GENERAL_ALARM),
    'vtbi-near-end': ('VTBI Near End', 'PM', False, EVT_NEAR_COMP),
    'infusion-complete': ('Infusion Complete', 'PM', True, GENERAL_ALARM),
    'battery-low': ('Battery Low', 'PM', False, GENERAL_ALARM),
    'battery-empty': ('Battery Empty', 'PH', True, GENERAL_ALARM),
    'standby-timeout': ('Standby Timeout', 'PL', False, GENERAL_ALARM),
}

_CTRL_SEQ = 0


def _ctrl_id(when):
    """20-digit control id shaped like the real ones (.NET ticks + sequence)."""
    global _CTRL_SEQ
    _CTRL_SEQ += 1
    ticks = int((when - datetime(1, 1, 1, tzinfo=timezone.utc)).total_seconds() * 10_000_000)
    return f'{ticks}{_CTRL_SEQ % 100:02d}'


def _hl7_ts(when):
    return when.strftime('%Y%m%d%H%M%S%z')


def _num(value):
    """Format a number the way the pumps do: 0, 18, 0.9, 30.95."""
    return f'{round(float(value), 2):g}'


def _obx(seq, vtype, code, name, sub_id, value='', units='', status='R',
         observed='', equipment=''):
    """One OBX segment. Field indexes are HL7 v2.6 positions (OBX-n = fields[n])."""
    fields = [''] * 19
    fields[0] = 'OBX'
    fields[1] = str(seq)
    fields[2] = vtype
    fields[3] = f'{code}^{name}^MDC' if name else str(code)
    fields[4] = sub_id
    fields[5] = value
    fields[6] = units
    fields[11] = status
    fields[14] = observed
    fields[18] = equipment
    return '|'.join(fields).rstrip('|')


class _Obx:
    """Collects OBX segments and numbers them 1..n like a real message."""

    def __init__(self):
        self.segments = []

    def add(self, vtype, code, name, sub_id, value='', units='', status='R',
            observed='', equipment=''):
        self.segments.append(_obx(len(self.segments) + 1, vtype, code, name, sub_id,
                                  value, units, status, observed, equipment))

    def device(self, code, name, sub_id, equipment=''):
        """Structure-only OBX (MDS / VMD / info blocks): no value, status X."""
        self.add('', code, name, sub_id, status='X', equipment=equipment)


class ActiveAlarm:
    """One alarm occurrence and its PCD-04 order ids (start -> update -> end)."""

    def __init__(self, key, pump, index):
        text, priority, stops, event = ALARMS[key]
        self.key = key
        self.text = text
        self.priority = priority
        self.stops = stops
        self.event_code, self.event_name = event
        self.base_id = f'{pump.alarm_label}_{uuid.uuid4()}_{index}'
        self.sequence = 0
        self.phase = 'start'
        self.state = 'active'
        self.inactivation = 'enabled'

    @property
    def filler_id(self):
        return f'{self.base_id}_{self.sequence}'

    @property
    def parent_id(self):
        return f'{self.base_id}_0'


class SimPump:
    """A simulated B.Braun pump: identity, program and physical state."""

    def __init__(self, label='I51559', pump_type='lvp', ward='WARD D5', facility='PHKL',
                 station_id=STATION_EUI, device_id=None, drug_library='PHKL_D5',
                 care_area='General Ward', drug='', rate=0.0, vtbi=0.0,
                 concentration=None, weight=None, syringe_size=50.0,
                 mrn='', patient='', battery=100.0, ip='10.0.0.13',
                 mac='c0:ee:40:0d:16:8f'):
        spec = PUMP_TYPES[pump_type]
        self.pump_type = pump_type
        self.label = label
        self.station_id = station_id
        # Deterministic per-label UUID so re-running a scenario updates the same
        # pump record instead of creating a new one every time.
        self.device_id = device_id or str(
            uuid.uuid5(uuid.NAMESPACE_OID, f'bbraun-sim:{station_id}:{label}'))
        self.model = spec['model']
        self.mds_code, self.mds_name = spec['mds']
        self.vmd_code, self.vmd_name = spec['vmd']
        self.names = spec['names']
        self.ward = ward
        self.facility = facility
        self.drug_library = drug_library
        self.care_area = care_area
        self.mrn = mrn
        self.patient = patient

        # Program
        self.drug = drug
        self.rate = 0.0                 # current rate (0 while stopped)
        self.programmed_rate = rate
        self.vtbi = vtbi
        self.infused = 0.0
        self.concentration = concentration   # mg/mL - drives the dose OBX block
        self.weight = weight                 # kg
        self.delivery_mode = 'continuous'
        self.channel_label = 'Primary'
        self.program_id = None
        self.segment_volume = None
        self.locked = set()             # program fields pinned from the command line

        # Physical state
        self.status = 'stopped'         # infusing / stopped / complete / standby / off
        self.not_delivering_reason = 'pump-stopped-user'
        self.pre_alarm = None           # MDC_ATTR_AL_COND shown inside PCD-01
        self.alarm = None
        self.alarm_count = 0
        self.alarm_on_complete = True
        self.online = True              # False = pump/station not reporting at all
        self.syringe_size = syringe_size
        self.syringe_actual = syringe_size
        self.syringe_mfr = 'B.Braun OPS 50 mL'
        self.battery = battery
        self.battery_capacity = 1928
        self.power = 'onMains'
        self.firmware = 'Pump Firmware = 1.1.13, Wifi Firmware = 1.4.59'
        self.wifi_state = 'Active'
        self.wifi_ssid = 'BBraunPumps'
        self.wifi_strength = 96
        self.ip = ip
        self.mac = mac

    # ---------------------------------------------------------------- state

    @property
    def alarm_label(self):
        """Label as it appears in PCD-04 - the captures drop the type prefix."""
        return self.label[1:] if self.label[:1] in ('I', 'P') else self.label

    @property
    def infusing(self):
        return self.status == 'infusing'

    @property
    def remaining(self):
        return max(0.0, self.vtbi - self.infused)

    @property
    def time_remaining_sec(self):
        return int(self.remaining / self.rate * 3600) if self.rate > 0 else 0

    @property
    def programmed_time_sec(self):
        return int(self.vtbi / self.programmed_rate * 3600) if self.programmed_rate > 0 else 0

    @property
    def battery_minutes(self):
        return int(self.battery / 100.0 * 700)

    def dose(self, volume_ml):
        """mL -> micrograms, using the programmed concentration."""
        return None if self.concentration is None else volume_ml * self.concentration * 1000

    def program(self, drug=None, rate=None, vtbi=None, concentration=None, weight=None):
        """Load a program. Values pinned from the CLI win over the scenario's."""
        if drug is not None and 'drug' not in self.locked:
            self.drug = drug
        if rate is not None and 'rate' not in self.locked:
            self.programmed_rate = float(rate)
        if vtbi is not None:
            if 'vtbi' not in self.locked:
                self.vtbi = float(vtbi)
            self.infused = 0.0
        if concentration is not None:
            self.concentration = float(concentration)
        if weight is not None:
            self.weight = float(weight)

    def start(self):
        self.status = 'infusing'
        self.rate = self.programmed_rate
        self.not_delivering_reason = None
        self.segment_volume = None
        self.program_id = self.program_id or str(uuid.uuid4())

    def stop(self, reason='pump-stopped-user'):
        self.status = 'stopped'
        self.rate = 0.0
        self.not_delivering_reason = reason

    def complete(self):
        self.status = 'complete'
        self.rate = 0.0
        self.infused = self.vtbi
        self.segment_volume = self.vtbi
        self.not_delivering_reason = 'pump-stopped-alarming'

    def advance(self, seconds):
        """Move the pump forward in simulated time. True when VTBI just ran out."""
        finished = False
        if self.infusing and self.rate > 0:
            delivered = min(self.remaining, self.rate * seconds / 3600.0)
            self.infused += delivered
            self.syringe_actual = max(0.0, self.syringe_actual - delivered)
            finished = self.remaining <= 0.0001
        if self.power == 'onBattery':
            self.battery = max(0.0, self.battery - seconds / 3600.0 * 12.5)  # ~8 h runtime
        elif self.battery < 100:
            self.battery = min(100.0, self.battery + seconds / 3600.0 * 40)
        # The pumps flag "near end" inside PCD-01 before the completion alarm
        if self.infusing and self.vtbi and self.remaining <= self.vtbi * 0.1:
            self.pre_alarm = EVT_NEAR_COMP
        return finished


# --------------------------------------------------------------------- messages


def _msh(pump, msg_type, profile, when, receiving_app='QMED', receiving_fac='QMED'):
    return (f'MSH|^~\\&|PAT_DEVICE_BBRAUN^{pump.station_id}^EUI-64|BBRAUN|'
            f'{receiving_app}|{receiving_fac}|{_hl7_ts(when)}||{msg_type}|'
            f'{_ctrl_id(when)}|P|2.6|||AL|NE||ASCII|en^English^ISO639||{profile}')


def _pid(pump):
    """Real pumps know no patient; an MRN is only set when a gateway enriches."""
    if pump.mrn:
        name = pump.patient or 'DOE^JOHN'
        return f'PID|||{pump.mrn}^^^IHE^PI||{name}^^^^^^L||||||||||||||||||||||||||N'
    return 'PID|||Unknown Patient^Unknown Patient||^^^^^^U||||||||||||||||||||||||||Y'


def _obr_infusion(pump, when):
    order = pump.program_id or '0'
    entity = f'PAT_DEVICE_BBRAUN^{pump.station_id}^EUI-64'
    drug = f'{pump.drug}^{pump.drug}' if pump.drug else '999999^Unknown medication'
    return (f'OBR|1|{order}^{entity}|{order}^{entity}|{drug}|||{_hl7_ts(when)}')


def _equipment(pump):
    return f'{pump.label}^^{EQUIPMENT_EUI}^EUI-64'


def _delivery_block(pump, obx):
    """The OBX block shared by PCD-01 and PCD-10 (status, volumes, dose, syringe)."""
    n = pump.names
    status = 'infusing' if pump.infusing else 'not-infusing'
    delivering = 'delivering' if pump.infusing else 'not-delivering'

    obx.device('70067', 'MDC_DEV_PUMP_DELIVERY_INFO', '1.1.1.0')
    obx.add('CWE', '184519', 'MDC_PUMP_INFUSING_STATUS', '1.1.1.1', f'^pump-status-{status}')
    obx.add('NM', '158014', 'MDC_FLOW_FLUID_PUMP_CURRENT', '1.1.1.2',
            _num(pump.rate), U_ML_H)
    obx.add('CWE', '158016', n['sources'], '1.1.1.3', '^pump-source-info-primary')

    obx.device('70071', n['source'], '1.1.2.0')
    obx.add('ST', '158012', n['channel'], '1.1.2.1', pump.channel_label)
    if pump.drug:
        obx.add('ST', '184514', 'MDC_DRUG_NAME_LABEL', '1.1.2.2', pump.drug)
    if pump.care_area:
        obx.add('ST', '184516', 'MDC_PUMP_DRUG_LIBRARY_CARE_AREA', '1.1.2.4', pump.care_area)
    obx.add('NM', '157784', 'MDC_FLOW_FLUID_PUMP', '1.1.2.5',
            _num(pump.programmed_rate), U_ML_H)
    if pump.concentration is not None:
        obx.add('NM', '157760', 'MDC_CONC_DRUG', '1.1.2.7', _num(pump.concentration), U_MG_ML)
    obx.add('NM', '157884', 'MDC_VOL_FLUID_TBI', '1.1.2.8', _num(pump.vtbi), U_ML)
    obx.add('NM', '157872', 'MDC_VOL_FLUID_TBI_REMAIN', '1.1.2.9', _num(pump.remaining), U_ML)
    if pump.segment_volume is not None:
        obx.add('NM', '157992', 'MDC_VOL_FLUID_DELIV_SEGMENT', '1.1.2.10',
                _num(pump.segment_volume), U_ML)
    obx.add('NM', '157993', 'MDC_VOL_FLUID_DELIV_TOTAL', '1.1.2.11', _num(pump.infused), U_ML)

    if pump.concentration is not None:
        obx.add('NM', '157999', 'MDC_DOSE_DRUG_TBI', '1.1.2.12',
                _num(pump.dose(pump.vtbi)), U_UG)
        obx.add('NM', '158000', 'MDC_DOSE_DRUG_TBI_REMAIN', '1.1.2.13',
                _num(pump.dose(pump.remaining)), U_UG)
        if pump.segment_volume is not None:
            obx.add('NM', '158003', 'MDC_DOSE_DRUG_DELIV_SEGMENT', '1.1.2.14',
                    _num(pump.dose(pump.segment_volume)), U_UG)
        obx.add('NM', '158001', 'MDC_DOSE_DRUG_DELIV_TOTAL', '1.1.2.15',
                _num(pump.dose(pump.infused)), U_UG)

    obx.add('NM', '157996', 'MDC_TIME_PD_PROG', '1.1.2.16', str(pump.programmed_time_sec), U_SEC)
    obx.add('NM', '157916', 'MDC_TIME_PD_REMAIN', '1.1.2.17', str(pump.time_remaining_sec), U_SEC)
    if pump.weight:
        obx.add('NM', '68063', 'MDC_ATTR_PT_WEIGHT', '1.1.2.18', _num(pump.weight), U_KG)
    obx.add('CWE', '158008', n['mode'], '1.1.2.21',
            f'^pump-program-delivery-mode-{pump.delivery_mode}')
    obx.add('CWE', '158005', n['delivery'], '1.1.2.22',
            f'^pump-delivery-status-{delivering}')
    if not pump.infusing and pump.not_delivering_reason:
        obx.add('CWE', '158006', n['reason'], '1.1.2.23', f'^{pump.not_delivering_reason}')

    if pump.pump_type == 'syringe':
        obx.device('70091', 'MDC_DEV_PUMP_SYRINGE_INFO', '1.1.6.0')
        obx.add('NM', '157880', 'MDC_VOL_SYRINGE', '1.1.6.1', _num(pump.syringe_size), U_ML)
        obx.add('NM', '157984', 'MDC_VOL_SYRINGE_ACTUAL', '1.1.6.2',
                _num(pump.syringe_actual), U_ML)
        obx.add('ST', '184488', 'MDC_SYRINGE_MANUFACTURER', '1.1.6.3', pump.syringe_mfr)


def _identity_block(pump, obx, event=None):
    """MDS/VMD, drug library, pre-alarm condition, model, system id, location."""
    obx.device(pump.mds_code, pump.mds_name, '1.0.0.0', equipment=_equipment(pump))
    if pump.pump_type == 'lvp':
        obx.add('ST', '0', 'MDC_ATTR_PILLAR_ASSEMBLY', '1.0.0.2',
                '0^0^0^pillar-orientation-vertical^pillar-direction-right^'
                'slot-rack-direction-up', status='F')
    obx.device(pump.vmd_code, pump.vmd_name, '1.1.0.0')
    obx.add('ST', '184520', 'MDC_PUMP_DRUG_LIBRARY_NAME', '1.1.0.1', pump.drug_library)
    if event:
        obx.add('CWE', '68487', 'MDC_ATTR_EVT_COND', '1.1.0.3',
                f'{event[0]}^{event[1]}^MDC')
        obx.add('ST', '68488', 'MDC_ATTR_EVT_SOURCE', '1.1.0.4', '1.1.2.0')
    if pump.pre_alarm:
        obx.add('CWE', '68012', 'MDC_ATTR_AL_COND', '1.1.0.5',
                f'{pump.pre_alarm[0]}^{pump.pre_alarm[1]}^MDC')
        obx.add('ST', '68480', 'MDC_ATTR_ALERT_SOURCE', '1.1.0.6', '1.1.2.0')
    obx.add('ST', '67880', 'MDC_ATTR_ID_MODEL', '1.1.0.8', pump.model, status='F')
    obx.add('ST', '67972', 'MDC_ATTR_SYS_ID', '1.1.0.9', pump.device_id, status='F')
    if pump.ward or pump.facility:
        obx.add('ST', '0', 'MDC_ATTR_PUMP_PILLAR_DETAILS', '1.1.0.24', '0^0', status='F',
                equipment=f'~~{pump.ward}~{pump.facility}^^{EQUIPMENT_EUI}^EUI-64')


def build_pcd01(pump, when):
    """ORU^R01 / IHE PCD-01: periodic infusion status."""
    obx = _Obx()
    _identity_block(pump, obx)
    _delivery_block(pump, obx)
    segments = [_msh(pump, 'ORU^R01^ORU_R01', PROFILE_PCD01, when), _pid(pump),
                _obr_infusion(pump, when)] + obx.segments
    return '\r'.join(segments) + '\r'


def build_pcd10(pump, when, event):
    """ORU^R42 / IHE PCD-10: an infusion event (delivery start / complete)."""
    obx = _Obx()
    _identity_block(pump, obx, event=event)
    _delivery_block(pump, obx)
    segments = [_msh(pump, 'ORU^R42^ORU_R01', PROFILE_PCD10, when), _pid(pump),
                _obr_infusion(pump, when)] + obx.segments
    return '\r'.join(segments) + '\r'


def build_pcd04(pump, when, alarm, include_sys_id=True):
    """ORU^R40 / IHE PCD-04: alarm start / update / end."""
    entity = f'PAT_DEVICE_BBRAUN^{pump.station_id}^EUI-64'
    equipment = f'{pump.alarm_label}^^{EQUIPMENT_EUI}^EUI-64'
    obr = [''] * 30
    obr[0] = 'OBR'
    obr[1] = '1'
    obr[2] = f'{_ctrl_id(when)}^{entity}'
    obr[3] = f'{alarm.filler_id}^{entity}'
    obr[4] = '196616^MDC_EVT_ALARM^MDC'
    obr[7] = _hl7_ts(when)
    if alarm.sequence:  # updates and the end message point back at the start
        obr[29] = f'^{alarm.parent_id}&PAT_DEVICE_BBRAUN&{pump.station_id}&EUI-64'

    obx = _Obx()
    obx.device(pump.mds_code, pump.mds_name, '1.0.0.0', equipment=equipment)
    obx.device(pump.vmd_code, pump.vmd_name, '1.1.0.0')
    obx.add('CWE', '196616', 'MDC_EVT_ALARM', '1.1.0.0.1',
            f'{alarm.event_code}^{alarm.event_name}^MDC', status='F', observed=_hl7_ts(when))
    obx.add('CWE', '68480', 'MDC_ATTR_ALERT_SOURCE', '1.1.0.0.2',
            f'{pump.mds_code}^{pump.mds_name}^MDC', status='F', equipment=equipment)
    obx.add('ST', '68481', 'MDC_ATTR_EVENT_PHASE', '1.1.0.0.3', alarm.phase, status='F')
    obx.add('ST', '68482', 'MDC_ATTR_ALARM_STATE', '1.1.0.0.4', alarm.state, status='F')
    obx.add('ST', '68483', 'MDC_ATTR_ALARM_INACTIVATION_STATE', '1.1.0.0.5',
            alarm.inactivation, status='F')
    obx.add('ST', '68484', 'MDC_ATTR_ALARM_PRIORITY', '1.1.0.0.6', alarm.priority, status='F')
    obx.add('ST', '68485', 'MDC_ATTR_ALERT_TYPE', '1.1.0.0.7', 'ST', status='F')
    obx.add('ST', '68546', 'MDC_ATTR_ALERT_TEXT', '1.1.0.0.8', alarm.text, status='F')
    if include_sys_id:
        # Not in the B.Braun captures: without it the engine can only key the
        # alarm as "<station>:<label>", so it lands on a different pump record
        # than the PCD-01 stream. --alarm-identity realistic reproduces that.
        obx.add('ST', '67972', 'MDC_ATTR_SYS_ID', '1.1.0.9', pump.device_id, status='F')

    segments = [_msh(pump, 'ORU^R40^ORU_R40', PROFILE_PCD04, when, 'QMED', 'QMED'),
                _pid(pump), '|'.join(obr).rstrip('|')] + obx.segments
    return '\r'.join(segments) + '\r'


def build_pcd15(pump, when):
    """ORU^R01 / IHE PCD-15: device report - power, battery, wifi, firmware."""
    entity = f'PAT_DEVICE_BBRAUN^{pump.station_id}^EUI-64'
    on_battery = pump.power == 'onBattery'

    obx = _Obx()
    obx.device(pump.mds_code, pump.mds_name, '1.0.0.0', equipment=_equipment(pump))
    obx.device(pump.vmd_code, pump.vmd_name, '1.1.0.0')
    obx.add('ST', '184520', 'MDC_PUMP_DRUG_LIBRARY_NAME', '1.1.0.1', pump.drug_library)
    obx.add('ST', '67880', 'MDC_ATTR_ID_MODEL', '1.1.0.8', pump.model, status='F')
    obx.add('ST', '67972', 'MDC_ATTR_SYS_ID', '1.1.0.9', pump.device_id, status='F')
    obx.add('ST', '67925', 'MDC_ATTR_POWER_STAT', '1.1.0.11',
            'onBattery(1)' if on_battery else 'onMains(0)', status='F')
    obx.add('CWE', '0', 'MDCX_DMC_ATTR_POWER_SOURCE', '1.1.0.12',
            '1^OnBattery' if on_battery else '0^OnMains', status='F')
    obx.add('CWE', '0', 'MDCX_DMC_ATTR_POWER_STATE', '1.1.0.13',
            '0^MDCX_DMC_ATTR_POWER_STATE_ON^MDC', status='F')
    obx.add('ST', '531976', 'MDC_ID_PROD_SPEC_FW', '1.1.0.16', pump.firmware, status='F')

    obx.device('65620', 'MDC_MOC_NCC_WIRELESS', '1.1.1.0')
    obx.add('ST', '69408', 'MDC_NCC_WIRELESS_STATE', '1.1.1.1', pump.wifi_state, status='F')
    obx.add('ST', '69410', 'MDC_NCC_WIRELESS_DEVICE_IPV4_ADDR', '1.1.1.2', pump.ip, status='F')
    obx.add('ST', '69416', 'MDC_NCC_WIRELESS_MAC', '1.1.1.3', pump.mac, status='F')
    obx.add('ST', '69417', 'MDC_NCC_WIRELESS_SSID', '1.1.1.4', pump.wifi_ssid, status='F')
    obx.add('NM', '69425', 'MDC_NCC_WIRELESS_STRENGTH_PERCENT', '1.1.1.6',
            str(pump.wifi_strength), U_PCT, status='F')
    obx.add('NM', '69427', 'MDC_NCC_WIRELESS_TXRATE', '1.1.1.7', '0', U_MBIT, status='F')

    obx.device('65577', 'MDC_MOC_BATTERY', '1.2.0.0')
    if on_battery and pump.battery >= 95:  # the capture only carries it when full
        obx.add('ST', '68020', 'MDC_ATTR_BATT_STAT', '1.2.0.1', 'batt-full(1)', status='F')
    obx.add('NM', '67976', 'MDC_ATTR_TIME_BATT_REMAIN', '1.2.0.2',
            str(pump.battery_minutes), U_MIN, status='F')
    obx.add('NM', '67996', 'MDC_ATTR_VAL_BATT_CHARGE', '1.2.0.3',
            _num(pump.battery), U_PCT, status='F')
    obx.add('NM', '68023', 'MDC_ATTR_CAPAC_BATT_FULL', '1.2.0.5',
            str(pump.battery_capacity), U_MAH, status='F')

    obr = (f'OBR|1|0^{entity}|{pump.label}^{entity}|69135^MDC_OBS_MEM^MDC|||{_hl7_ts(when)}')
    segments = [_msh(pump, 'ORU^R01^ORU_R01', PROFILE_PCD15, when), _pid(pump),
                'PV1||N', obr] + obx.segments
    return '\r'.join(segments) + '\r'


# ------------------------------------------------------------------- simulator


class Simulator:
    """Sends scenario traffic over MLLP, on its own compressible clock."""

    def __init__(self, args):
        self.host = args.host
        self.port = args.port
        self.speed = args.speed
        self.interval = args.interval
        self.dry_run = args.dry_run
        self.out_path = args.out
        self.quiet = args.quiet
        self.per_message_connection = args.connection == 'per-message'
        self.include_sys_id = args.alarm_identity == 'sys-id'
        self.args = args

        self.clock = args.start_time or datetime.now(timezone.utc).replace(microsecond=0)
        self.pumps = []
        self.sock = None
        self.buffered = []
        self.sent = 0
        self.accepted = 0
        self.unexpected = 0
        self.failed = 0
        self._pump_count = 0
        self._out_file = open(args.out, 'w', encoding='utf-8') if args.out else None

    # -------------------------------------------------------------- plumbing

    def close(self):
        if self.sock:
            try:
                self.sock.close()
            except OSError:
                pass
            self.sock = None
        if self._out_file:
            self._out_file.close()
            self._out_file = None

    def _connect(self):
        if self.sock is None:
            self.sock = socket.create_connection((self.host, self.port), timeout=15)
            self.sock.settimeout(15)
        return self.sock

    def _transmit(self, raw):
        """Send one MLLP frame and return the ACK code ('AA' / 'AE' / '')."""
        frame = MLLP_START_BLOCK + raw.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
        for attempt in (1, 2):
            try:
                sock = self._connect()
                sock.sendall(frame)
                response = sock.recv(8192).decode('utf-8', errors='replace')
                if self.per_message_connection:
                    self.close()
                for segment in response.strip('\x0b\x1c\r').split('\r'):
                    if segment.startswith('MSA|'):
                        return segment.split('|')[1]
                return ''
            except (OSError, socket.timeout) as exc:
                self.close()
                if attempt == 2:
                    raise RuntimeError(f'{exc}') from exc
        return ''

    def send(self, raw, kind, pump=None, detail='', expect='AA'):
        self.sent += 1
        label = pump.label if pump else '-'
        if self._out_file:
            self._out_file.write(raw.replace('\r', '\n') + '\n')
        if self.dry_run:
            self._log('--', kind, label, detail)
            if not self.quiet:
                print(raw.replace('\r', '\n'))
            return '--'
        try:
            ack = self._transmit(raw)
        except RuntimeError as exc:
            self.failed += 1
            self._log('!!', kind, label, f'{detail}  send failed: {exc}', force=True)
            return ''
        if ack == expect:
            self.accepted += 1
        else:
            self.unexpected += 1
        self._log(ack or '??', kind, label, detail,
                  force=ack != expect)
        return ack

    def _log(self, ack, kind, label, detail, force=False):
        if self.quiet and not force:
            return
        print(f'  {self.clock:%H:%M:%S}  {kind:<7} {label:<8} {detail:<52} [{ack}]')

    def note(self, text):
        if not self.quiet:
            print(f'  {self.clock:%H:%M:%S}  -- {text}')

    # ------------------------------------------------------------------ time

    def wait(self, seconds):
        """Advance the simulated clock (and every pump) by `seconds`."""
        self.clock += timedelta(seconds=seconds)
        for pump in self.pumps:
            pump.advance(seconds)
        if self.speed > 0 and not self.dry_run:
            time.sleep(seconds / self.speed)

    # ------------------------------------------------------------- messaging

    def new_pump(self, **kwargs):
        """Create a pump; CLI overrides apply to the first pump of a scenario."""
        self._pump_count += 1
        pinned = set()
        if self._pump_count == 1:
            for key in ('pump_type', 'label', 'device_id', 'station_id', 'ward',
                        'facility', 'drug', 'rate', 'vtbi', 'mrn', 'patient'):
                value = getattr(self.args, key, None)
                if value not in (None, ''):
                    kwargs[key] = value
                    if key in ('drug', 'rate', 'vtbi'):
                        pinned.add(key)
            if kwargs.get('pump_type') and 'label' not in kwargs:
                kwargs['label'] = PUMP_TYPES[kwargs['pump_type']]['prefix'] + '51559'
        pump = SimPump(**kwargs)
        # A pinned program must survive the scenario's own start_infusion() calls
        pump.locked = pinned
        self.pumps.append(pump)
        return pump

    def _volumes(self, pump):
        volumes = f'{_num(pump.infused)}/{_num(pump.vtbi)} mL' if pump.vtbi else 'no program'
        return f'{volumes:<16} {pump.drug or "no drug"}'

    def _summary(self, pump):
        if pump.status == 'infusing':
            state = f'infusing {_num(pump.rate)} mL/h'
        elif pump.status == 'complete':
            state = 'complete'
        else:
            state = pump.not_delivering_reason or 'stopped'
        return f'{state:<28} {self._volumes(pump)}'

    def status(self, pump, buffer=False):
        raw = build_pcd01(pump, self.clock)
        if buffer:
            self.buffered.append((raw, 'PCD-01', pump, self._summary(pump)))
            return
        self.send(raw, 'PCD-01', pump, self._summary(pump))

    def event(self, pump, event):
        raw = build_pcd10(pump, self.clock, event)
        self.send(raw, 'PCD-10', pump, f'{event[1]:<28} {self._volumes(pump)}')

    def device_report(self, pump):
        raw = build_pcd15(pump, self.clock)
        detail = (f'{pump.power} {_num(pump.battery)}%  wifi {pump.wifi_state} '
                  f'{pump.wifi_strength}%')
        self.send(raw, 'PCD-15', pump, detail)

    def flush_buffer(self):
        """Send messages that were queued while the pump was offline."""
        for raw, kind, pump, detail in self.buffered:
            self.send(raw, kind, pump, f'{detail}  (buffered)')
        self.buffered = []

    # ---------------------------------------------------------------- alarms

    def raise_alarm(self, pump, key):
        pump.alarm_count += 1
        alarm = ActiveAlarm(key, pump, pump.alarm_count)
        pump.alarm = alarm
        if alarm.stops and pump.infusing:
            pump.stop('pump-stopped-alarming')
        raw = build_pcd04(pump, self.clock, alarm, self.include_sys_id)
        self.send(raw, 'PCD-04', pump, f'ALARM start  {alarm.text} ({alarm.priority})')
        return alarm

    def update_alarm(self, pump, inactivation='audio-paused'):
        alarm = pump.alarm
        if not alarm:
            return
        alarm.sequence += 1
        alarm.phase = 'update'
        alarm.inactivation = inactivation
        raw = build_pcd04(pump, self.clock, alarm, self.include_sys_id)
        self.send(raw, 'PCD-04', pump, f'ALARM update {alarm.text} ({inactivation})')

    def clear_alarm(self, pump):
        alarm = pump.alarm
        if not alarm:
            return
        alarm.sequence += 1
        alarm.phase = 'end'
        alarm.state = 'inactive'
        alarm.inactivation = 'alarm-off'
        raw = build_pcd04(pump, self.clock, alarm, self.include_sys_id)
        self.send(raw, 'PCD-04', pump, f'ALARM end    {alarm.text}')
        pump.alarm = None

    # ------------------------------------------------------------- run loops

    def start_infusion(self, pump, **program):
        pump.program(**program)
        pump.start()
        pump.pre_alarm = None
        self.event(pump, EVT_DELIV_START)

    def _on_complete(self, pump):
        pump.complete()
        pump.pre_alarm = EVT_NEAR_COMP
        self.event(pump, EVT_DELIV_COMP)
        if pump.alarm_on_complete:
            self.raise_alarm(pump, 'infusion-complete')

    def run_for(self, seconds, interval=None, pumps=None):
        """Tick simulated time forward, reporting PCD-01 for every online pump."""
        interval = interval or self.interval
        pumps = pumps if pumps is not None else self.pumps
        elapsed = 0
        while elapsed < seconds - 0.001:
            step = min(interval, seconds - elapsed)
            self.wait(step)
            elapsed += step
            for pump in pumps:
                if not pump.online:
                    continue
                if pump.infusing and pump.remaining <= 0.0001:
                    self._on_complete(pump)
                else:
                    self.status(pump)


# ------------------------------------------------------------------ scenarios

SCENARIOS = {}


class Scenario:
    def __init__(self, key, title, detail, span, run):
        self.key = key
        self.title = title
        self.detail = detail
        self.span = span
        self.run = run


def scenario(key, title, span, detail=''):
    def decorate(fn):
        SCENARIOS[key] = Scenario(key, title, detail, span, fn)
        return fn
    return decorate


@scenario('idle', 'Pump powered on but idle', '~5 min',
          'Baseline traffic from a pump with nothing loaded - what the engine sees '
          'before a nurse programs anything. Registers the pump and its ward.')
def _idle(sim):
    pump = sim.new_pump(label='I51559', ward='WARD D5', facility='PHKL')
    pump.stop('pump-stopped-powered-off')
    sim.note('Pump idle in WARD D5, no infusion programmed')
    sim.status(pump)
    sim.device_report(pump)
    sim.run_for(240, interval=120)


@scenario('normal', 'Routine LVP infusion, start to finish', '~35 min',
          '100 mL antibiotic over 30 min on an Infusomat: delivery-start event, '
          'periodic status, near-end flag, completion event and alarm.')
def _normal(sim):
    pump = sim.new_pump(label='I51559', ward='WARD D5', facility='PHKL')
    pump.stop('pump-stopped-user')
    sim.status(pump)
    sim.wait(60)

    sim.note('Nurse programs 100 mL Cefazolin at 200 mL/h and starts it')
    sim.start_infusion(pump, drug='Cefazolin', rate=200, vtbi=100)
    sim.run_for(31 * 60)

    sim.note('Bag empty - pump alarms until acknowledged')
    sim.wait(45)
    sim.update_alarm(pump)
    sim.wait(30)
    sim.clear_alarm(pump)
    sim.status(pump)


@scenario('syringe', 'Perfusor syringe infusion with dose data', '~25 min',
          'Dobutamine 5 mg/mL on a 50 mL syringe pump: concentration, dose '
          'delivered/remaining, patient weight and syringe fill level.')
def _syringe(sim):
    pump = sim.new_pump(label='P2449', pump_type='syringe', ward='ICU 1', facility='PHKL',
                        care_area='Global Surgery', drug_library='druglib')
    pump.syringe_actual = 32.05
    sim.note('Perfusor loaded with a 50 mL Dobutamine syringe')
    sim.status(pump)
    sim.wait(60)

    sim.start_infusion(pump, drug='Dobutamine', rate=18, vtbi=6,
                       concentration=5, weight=100)
    sim.run_for(21 * 60)
    sim.wait(30)
    sim.clear_alarm(pump)


@scenario('titration', 'Nurse titrates the rate up and down', '~30 min',
          'Noradrenaline on a syringe pump with the rate changed four times - each '
          'change lands as a fresh PCD-01, so the reading history shows the ramp.')
def _titration(sim):
    pump = sim.new_pump(label='P2451', pump_type='syringe', ward='ICU 1', facility='PHKL',
                        care_area='Critical Care', drug_library='druglib')
    sim.start_infusion(pump, drug='Noradrenaline', rate=4, vtbi=45,
                       concentration=0.05, weight=72)
    sim.run_for(8 * 60, interval=120)

    for new_rate, why in ((6, 'MAP still low'), (10, 'MAP dropping further'),
                          (7, 'MAP recovering'), (4, 'weaning off')):
        sim.note(f'Rate {_num(pump.rate)} -> {new_rate} mL/h ({why})')
        pump.programmed_rate = float(new_rate)
        pump.rate = float(new_rate)
        sim.status(pump)
        sim.run_for(5 * 60, interval=120)


@scenario('occlusion', 'Downstream occlusion, muted, cleared, resumed', '~20 min',
          'The classic ward alarm: infusion stops with pump-stopped-alarming, the '
          'nurse mutes it (audio-paused), fixes the line and the infusion resumes.')
def _occlusion(sim):
    pump = sim.new_pump(label='I51560', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Normal Saline', rate=120, vtbi=250)
    sim.run_for(6 * 60, interval=120)

    sim.note('Line kinked below the pump - downstream occlusion')
    sim.raise_alarm(pump, 'occlusion-downstream')
    sim.status(pump)
    sim.wait(20)
    sim.update_alarm(pump)
    sim.wait(70)
    sim.status(pump)

    sim.note('Nurse unkinks the line, clears the alarm and restarts')
    sim.wait(40)
    sim.clear_alarm(pump)
    sim.start_infusion(pump, rate=120)
    sim.run_for(10 * 60, interval=120)


@scenario('air-in-line', 'Air-in-line alarm that fires twice', '~15 min',
          'High-priority alarm, purge, restart, then the same alarm again a few '
          'minutes later - checks that repeat alarms are stored as separate events.')
def _air_in_line(sim):
    pump = sim.new_pump(label='I51561', ward='WARD D6', facility='PHKL')
    sim.start_infusion(pump, drug='Metronidazole', rate=150, vtbi=100)
    sim.run_for(4 * 60, interval=120)

    for round_no in (1, 2):
        sim.note(f'Air detected in the line (occurrence {round_no})')
        sim.raise_alarm(pump, 'air-in-line')
        sim.status(pump)
        sim.wait(30)
        sim.update_alarm(pump)
        sim.wait(60)
        sim.clear_alarm(pump)
        sim.start_infusion(pump, rate=150)
        sim.run_for(4 * 60, interval=120)


@scenario('syringe-holder-open', 'Exact replay of the captured alarm sequence', '~2 min',
          'The four PCD-04 messages from tests/samples/sample2.txt - active, muted, '
          'minimized, cleared - with the real MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT code.')
def _holder_open(sim):
    pump = sim.new_pump(label='P2449', pump_type='syringe', ward='ICU 1', facility='PHKL',
                        drug_library='druglib')
    sim.start_infusion(pump, drug='Dobutamine', rate=18, vtbi=6, concentration=5, weight=100)
    sim.wait(30)

    sim.note('Syringe holder opened while infusing')
    sim.raise_alarm(pump, 'syringe-holder-open')
    sim.wait(15)
    sim.update_alarm(pump)                      # muted
    sim.wait(12)
    sim.update_alarm(pump)                      # minimized - same state, new update
    sim.wait(10)
    sim.clear_alarm(pump)
    sim.status(pump)


@scenario('near-end', 'VTBI near end, completion, then KVO', '~20 min',
          'Runs an infusion down to its last 10% (PCD-01 carries the near-end '
          'condition), completes it, then keeps the vein open at 1 mL/h.')
def _near_end(sim):
    pump = sim.new_pump(label='I51562', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Paracetamol', rate=250, vtbi=50)
    sim.run_for(13 * 60, interval=60)

    sim.wait(30)
    sim.clear_alarm(pump)
    sim.note('Pump switches to KVO to keep the line open')
    pump.delivery_mode = 'kvo'
    pump.programmed_rate = 1.0
    pump.vtbi = pump.infused + 5
    pump.start()
    sim.status(pump)
    sim.run_for(6 * 60, interval=120)


@scenario('battery', 'Unplugged, drains, alarms, back on mains', '~3 h',
          'PCD-15 device reports while the pump runs on battery: charge decays, '
          'Battery Low then Battery Empty fire, mains is restored and it recovers.')
def _battery(sim):
    pump = sim.new_pump(label='I51563', ward='WARD D6', facility='PHKL')
    sim.start_infusion(pump, drug='Normal Saline', rate=80, vtbi=500)
    sim.device_report(pump)
    sim.run_for(10 * 60, interval=300)

    sim.note('Pump unplugged for a transfer - now on battery, 35% left')
    pump.power = 'onBattery'
    pump.battery = 35
    sim.device_report(pump)

    warned = emptied = False
    while pump.battery > 1 and not emptied:
        sim.run_for(20 * 60, interval=600)
        sim.device_report(pump)
        if pump.battery <= 20 and not warned:
            warned = True
            sim.raise_alarm(pump, 'battery-low')
            sim.status(pump)
        if pump.battery <= 5:
            emptied = True
            sim.clear_alarm(pump)
            sim.raise_alarm(pump, 'battery-empty')
            sim.status(pump)

    sim.note('Plugged back into mains')
    pump.power = 'onMains'
    sim.wait(60)
    sim.device_report(pump)
    sim.clear_alarm(pump)
    sim.start_infusion(pump, rate=80)
    sim.run_for(10 * 60, interval=300)
    sim.device_report(pump)


@scenario('power-off', 'Pump switched off mid-infusion, then back on', '~25 min',
          'Final PCD-01 with pump-stopped-powered-off (the shape captured on a real '
          'ward), a reporting gap, then the pump comes back and resumes.')
def _power_off(sim):
    pump = sim.new_pump(label='I51559', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Normal Saline', rate=120, vtbi=250)
    sim.run_for(6 * 60, interval=120)

    sim.note('Pump switched off at the wall - one last status, then silence')
    pump.stop('pump-stopped-powered-off')
    pump.drug = ''
    pump.program_id = None
    sim.status(pump)
    pump.online = False
    sim.run_for(10 * 60, interval=300)          # nothing is sent while off

    sim.note('Pump powered back on and the infusion restarted')
    pump.online = True
    sim.status(pump)
    sim.device_report(pump)
    sim.start_infusion(pump, drug='Normal Saline', rate=120, vtbi=250)
    sim.run_for(6 * 60, interval=120)


@scenario('wifi-drop', 'Wifi degrades, drops, then backfills buffered data', '~30 min',
          'Signal decays in PCD-15 reports, the station goes quiet, then reconnects: '
          'live status first, then the buffered PCD-01s with their original (older) '
          'timestamps. Watch whether stale backfill overwrites the current state.')
def _wifi_drop(sim):
    pump = sim.new_pump(label='I51564', ward='WARD D6', facility='PHKL')
    sim.start_infusion(pump, drug='Vancomycin', rate=100, vtbi=250)
    sim.device_report(pump)
    sim.run_for(5 * 60, interval=150)

    for strength in (62, 31, 12):
        pump.wifi_strength = strength
        sim.wait(150)
        sim.device_report(pump)
        sim.status(pump)

    sim.note('Wifi lost - the pump keeps infusing and buffers its reports')
    pump.wifi_state = 'Inactive'
    pump.wifi_strength = 0
    pump.online = False
    for _ in range(6):
        sim.wait(150)
        sim.status(pump, buffer=True)

    sim.note('Reconnected - live status first, then the backlog arrives out of order')
    pump.online = True
    pump.wifi_state = 'Active'
    pump.wifi_strength = 88
    sim.status(pump)
    sim.device_report(pump)
    sim.flush_buffer()
    sim.run_for(5 * 60, interval=150)


@scenario('standby', 'Infusion paused into standby and resumed', '~25 min',
          'Pump left in standby between doses until the standby-timeout reminder '
          'fires, then the nurse resumes the same program.')
def _standby(sim):
    pump = sim.new_pump(label='I51565', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Potassium Chloride', rate=60, vtbi=100)
    sim.run_for(6 * 60, interval=120)

    sim.note('Nurse pauses the infusion - pump goes to standby')
    pump.stop('pump-stopped-standby')
    sim.status(pump)
    sim.run_for(8 * 60, interval=240)

    sim.raise_alarm(pump, 'standby-timeout')
    sim.wait(60)
    sim.clear_alarm(pump)

    sim.note('Infusion resumed where it left off')
    pump.start()
    sim.event(pump, EVT_DELIV_START)
    sim.run_for(8 * 60, interval=120)


@scenario('multi-pump', 'Four pumps, two wards, one SpaceStation', '~30 min',
          'Two Infusomats and two Perfusors reporting concurrently with different '
          'drugs, rates and wards - the dashboard case. One alarms mid-run.')
def _multi_pump(sim):
    fleet = [
        sim.new_pump(label='I51570', ward='WARD D5', facility='PHKL'),
        sim.new_pump(label='I51571', ward='WARD D5', facility='PHKL'),
        sim.new_pump(label='P2461', pump_type='syringe', ward='ICU 1', facility='PHKL',
                     care_area='Critical Care'),
        sim.new_pump(label='P2462', pump_type='syringe', ward='ICU 1', facility='PHKL',
                     care_area='Critical Care'),
    ]
    programs = [
        dict(drug='Normal Saline', rate=125, vtbi=500),
        dict(drug='Cefazolin', rate=200, vtbi=100),
        dict(drug='Noradrenaline', rate=6, vtbi=45, concentration=0.05, weight=68),
        dict(drug='Insulin', rate=4, vtbi=50, concentration=1, weight=68),
    ]
    for pump, program in zip(fleet, programs):
        sim.start_infusion(pump, **program)

    sim.run_for(12 * 60, interval=120)
    sim.note('One of the ICU syringe pumps hits an occlusion')
    sim.raise_alarm(fleet[2], 'occlusion-upstream')
    sim.status(fleet[2])
    sim.run_for(4 * 60, interval=120)
    sim.clear_alarm(fleet[2])
    fleet[2].start()
    sim.run_for(12 * 60, interval=120)


@scenario('alarm-storm', 'Six pumps alarming at once', '~10 min',
          'Rapid-fire alarms and clears across a fleet - load test for the alarm '
          'table, the active-alarm view and any downstream notifications.')
def _alarm_storm(sim):
    keys = ['occlusion-downstream', 'air-in-line', 'door-open', 'drop-sensor',
            'battery-low', 'free-flow', 'occlusion-upstream', 'vtbi-near-end']
    fleet = []
    for i in range(6):
        pump_type = 'lvp' if i % 2 == 0 else 'syringe'
        prefix = PUMP_TYPES[pump_type]['prefix']
        pump = sim.new_pump(label=f'{prefix}5158{i}', pump_type=pump_type,
                            ward='WARD D5' if i < 3 else 'WARD D6', facility='PHKL')
        sim.start_infusion(pump, drug='Normal Saline', rate=100 + i * 10, vtbi=250)
        fleet.append(pump)

    for round_no in range(3):
        for i, pump in enumerate(fleet):
            sim.raise_alarm(pump, keys[(round_no * 6 + i) % len(keys)])
            sim.wait(5)
        sim.wait(30)
        for pump in fleet:
            sim.clear_alarm(pump)
            if not pump.infusing:
                pump.start()
            sim.wait(5)
        sim.run_for(60, interval=60)


@scenario('alarm', 'Fire one chosen alarm on demand', '~5 min',
          'Starts an infusion and raises whichever alarm --alarm names (default '
          'occlusion-downstream), mutes it, clears it and resumes - the quickest way '
          'to check one specific alert end to end.')
def _single_alarm(sim):
    key = sim.args.alarm
    if key not in ALARMS:
        raise ValueError(f'unknown alarm "{key}" - choose from: {", ".join(sorted(ALARMS))}')
    pump = sim.new_pump(label='I51567', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Normal Saline', rate=120, vtbi=250)
    sim.run_for(2 * 60, interval=60)

    sim.raise_alarm(pump, key)
    sim.status(pump)
    sim.wait(30)
    sim.update_alarm(pump)
    sim.wait(60)
    sim.clear_alarm(pump)
    if not pump.infusing:
        pump.start()
    sim.run_for(2 * 60, interval=60)


@scenario('malformed', 'Broken and hostile messages', '~2 min',
          'Truncated, unparseable and out-of-spec HL7. The engine must store every '
          'raw message and answer AE where it cannot parse - nothing may be lost.')
def _malformed(sim):
    pump = sim.new_pump(label='I51566', ward='WARD D5', facility='PHKL')
    sim.start_infusion(pump, drug='Normal Saline', rate=120, vtbi=250)
    sim.status(pump)
    when = sim.clock

    good = build_pcd01(pump, when)
    lines = good.split('\r')

    cases = [
        ('no MSH segment', '\r'.join(lines[1:]), 'AE'),
        ('not HL7 at all', 'the pump gateway sent us prose\r', 'AE'),
        ('MSH only', lines[0] + '\r', 'AA'),
        ('truncated mid-segment', good[:len(good) // 2], 'AA'),
        ('NM field holding text',
         good.replace(f'|1.1.1.2|{_num(pump.rate)}|', '|1.1.1.2|NaN mL/h|'), 'AA'),
        ('unknown MDC code', good.replace('184514^MDC_DRUG_NAME_LABEL',
                                          '999999^MDC_NOT_A_REAL_CODE'), 'AA'),
        ('LF instead of CR', good.replace('\r', '\n'), 'AA'),
        ('very long alert text', build_pcd04(
            pump, when, _long_text_alarm(pump), sim.include_sys_id), 'AA'),
    ]
    for name, raw, expect in cases:
        sim.send(raw, 'RAW', pump, f'{name:<28} expecting {expect}', expect=expect)
        sim.wait(5)

    sim.status(pump)


def _long_text_alarm(pump):
    alarm = ActiveAlarm('occlusion-downstream', pump, 99)
    alarm.text = ('Occlusion Downstream - check the line from the pump to the patient, '
                  'the three-way tap and the cannula site for kinks or infiltration')
    return alarm


@scenario('soak', 'Throughput soak test', 'instant',
          'N pumps x M status messages as fast as the link allows (--pumps, '
          '--messages). Run it with --speed 0 to measure messages per second.')
def _soak(sim):
    count = max(1, sim.args.pumps)
    messages = max(1, sim.args.messages)
    sim.speed = 0                                # never sleep - this is a throughput test
    fleet = []
    for i in range(count):
        pump = sim.new_pump(label=f'I6{i:04d}', ward=f'WARD D{5 + i % 2}', facility='PHKL')
        pump.program(drug='Normal Saline', rate=100 + i, vtbi=1000)
        pump.start()
        fleet.append(pump)

    sim.note(f'Soaking: {count} pumps x {messages} messages = {count * messages} total')
    started = time.time()
    for _ in range(messages):
        sim.wait(60)
        for pump in fleet:
            if pump.remaining <= 0:
                pump.infused = 0.0
            sim.status(pump)
    elapsed = max(0.001, time.time() - started)
    if not sim.quiet:
        print(f'  soak: {sim.sent} messages in {elapsed:.1f}s '
              f'({sim.sent / elapsed:.1f} msg/s)')


@scenario('replay', 'Replay the captured real messages', 'instant',
          'Sends every message in tests/samples/ - the genuine B.Braun captures - '
          'so the engine is checked against bytes no simulator wrote.')
def _replay(sim):
    try:
        from tests.sample_utils import load_all_samples
    except ImportError:
        print('  replay needs tests/sample_utils.py - run from the infusion_engine folder')
        return
    samples = load_all_samples()
    sim.note(f'Replaying {len(samples)} captured messages from tests/samples/')
    for name, raw in samples:
        sim.send(raw, 'REPLAY', None, name)
        sim.wait(2)


# ------------------------------------------------------------------------ CLI


def _parse_start_time(value):
    if not value:
        return None
    text = value.strip().replace(' ', 'T').replace('Z', '+00:00')
    try:
        parsed = datetime.fromisoformat(text)
    except ValueError:
        raise argparse.ArgumentTypeError(
            f'--start-time must be ISO 8601 (2026-08-05T09:00:00), got "{value}"')
    return parsed.astimezone(timezone.utc) if parsed.tzinfo else parsed.replace(
        tzinfo=timezone.utc)


def _print_catalogue():
    print('B.Braun HL7 scenarios (python simulator.py <name> [<name> ...])\n')
    width = max(len(key) for key in SCENARIOS)
    for key, item in SCENARIOS.items():
        print(f'  {key:<{width}}  {item.title}  [{item.span}]')
        for line in _wrap(item.detail, 78):
            print(f'  {"":<{width}}  {line}')
        print()
    print('  all' + ' ' * (width - 3) + '  every scenario above except soak, in order\n')
    print('Alarm texts available to --alarm (and used by the scenarios):')
    for line in _wrap(', '.join(sorted(ALARMS)), 78):
        print(f'  {line}')


def _wrap(text, width):
    words, lines, current = text.split(), [], ''
    for word in words:
        if len(current) + len(word) + 1 > width:
            lines.append(current)
            current = word
        else:
            current = f'{current} {word}'.strip()
    if current:
        lines.append(current)
    return lines


def build_parser():
    parser = argparse.ArgumentParser(
        description='B.Braun HL7 scenario simulator for the infusion engine',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='python simulator.py --list   shows every scenario and what it exercises')
    parser.add_argument('scenarios', nargs='*', help='scenario names, or "all"')
    parser.add_argument('--list', action='store_true', help='list the scenarios and exit')

    conn = parser.add_argument_group('connection')
    conn.add_argument('--host', default=DEFAULT_HOST, help=f'engine MLLP host [{DEFAULT_HOST}]')
    conn.add_argument('--port', type=int, default=DEFAULT_PORT,
                      help=f'engine MLLP port [{DEFAULT_PORT}]')
    conn.add_argument('--connection', choices=('persistent', 'per-message'),
                      default='persistent',
                      help='hold one socket open (like a SpaceStation) or reconnect each time')
    conn.add_argument('--dry-run', action='store_true', help='print the HL7 instead of sending')
    conn.add_argument('--out', metavar='FILE', help='also write every message to FILE')

    timing = parser.add_argument_group('timing')
    timing.add_argument('--speed', type=float, default=10.0,
                        help='simulated seconds per wall-clock second; 0 = no waiting [10]')
    timing.add_argument('--interval', type=float, default=60.0,
                        help='seconds between periodic PCD-01 reports [60]')
    timing.add_argument('--start-time', type=_parse_start_time, metavar='ISO',
                        help='backdate the simulated clock, e.g. 2026-08-05T09:00:00')
    timing.add_argument('--repeat', type=int, default=1, help='run the scenarios N times')
    timing.add_argument('--loop', action='store_true', help='repeat until Ctrl+C')

    pump = parser.add_argument_group('pump overrides (applied to the first pump)')
    pump.add_argument('--pump-type', dest='pump_type', choices=('lvp', 'syringe'))
    pump.add_argument('--label', help='pump label, e.g. I51559')
    pump.add_argument('--device-id', dest='device_id', help='MDC_ATTR_SYS_ID value')
    pump.add_argument('--station', dest='station_id', default=STATION_EUI,
                      help=f'SpaceStation EUI-64 in MSH-3 [{STATION_EUI}]')
    pump.add_argument('--ward')
    pump.add_argument('--facility')
    pump.add_argument('--drug')
    pump.add_argument('--rate', type=float)
    pump.add_argument('--vtbi', type=float)
    pump.add_argument('--mrn', help='send an identified PID instead of "Unknown Patient"')
    pump.add_argument('--patient', help='patient name for --mrn, e.g. DOE^JOHN')

    other = parser.add_argument_group('behaviour')
    other.add_argument('--alarm-identity', dest='alarm_identity',
                       choices=('sys-id', 'realistic'), default='sys-id',
                       help='sys-id: alarms carry MDC_ATTR_SYS_ID so they bind to the same '
                            'pump; realistic: omit it, exactly like the B.Braun captures')
    other.add_argument('--alarm', default='occlusion-downstream', metavar='KEY',
                       help='alarm scenario: which alarm to fire [occlusion-downstream]')
    other.add_argument('--pumps', type=int, default=5, help='soak scenario: pump count [5]')
    other.add_argument('--messages', type=int, default=20,
                       help='soak scenario: messages per pump [20]')
    other.add_argument('--seed', type=int, help='seed the random generator for repeatable runs')
    other.add_argument('--quiet', action='store_true', help='only print problems and the summary')
    return parser


def resolve_scenarios(names):
    if not names:
        return None, 'no scenario given - try "normal", or --list to see them all'
    resolved = []
    for name in names:
        if name == 'all':
            resolved += [s for key, s in SCENARIOS.items() if key != 'soak']
        elif name in SCENARIOS:
            resolved.append(SCENARIOS[name])
        else:
            close = [key for key in SCENARIOS if key.startswith(name)]
            if len(close) == 1:
                resolved.append(SCENARIOS[close[0]])
            else:
                return None, f'unknown scenario "{name}" - run --list to see the catalogue'
    return resolved, None


def main(argv=None):
    args = build_parser().parse_args(argv)
    if args.list:
        _print_catalogue()
        return 0
    if args.seed is not None:
        random.seed(args.seed)

    scenarios, error = resolve_scenarios(args.scenarios)
    if error:
        print(f'simulator: {error}', file=sys.stderr)
        return 2

    target = 'dry run (nothing sent)' if args.dry_run else f'{args.host}:{args.port}'
    print(f'B.Braun simulator -> {target}   speed x{args.speed:g}   '
          f'PCD-01 every {args.interval:g}s')

    total_sent = total_ok = total_bad = total_failed = broken = 0
    started = time.time()
    round_no = 0
    try:
        while True:
            round_no += 1
            for item in scenarios:
                print(f'\n=== {item.key}: {item.title} ({item.span}) ===')
                sim = Simulator(args)
                try:
                    item.run(sim)
                except KeyboardInterrupt:
                    sim.close()
                    raise
                except Exception as exc:                    # keep going, report clearly
                    broken += 1
                    print(f'  !! scenario {item.key} failed: {exc}', file=sys.stderr)
                finally:
                    total_sent += sim.sent
                    total_ok += sim.accepted
                    total_bad += sim.unexpected
                    total_failed += sim.failed
                    sim.close()
            if not args.loop and round_no >= max(1, args.repeat):
                break
    except KeyboardInterrupt:
        print('\ninterrupted')

    elapsed = time.time() - started
    print(f'\nSent {total_sent} messages in {elapsed:.1f}s - {total_ok} as expected, '
          f'{total_bad} unexpected ACK, {total_failed} not delivered'
          + (f', {broken} scenario(s) errored' if broken else ''))
    return 0 if not (total_bad or total_failed or broken) else 1


if __name__ == '__main__':
    sys.exit(main())
