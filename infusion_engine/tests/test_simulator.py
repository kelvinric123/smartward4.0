"""Checks that every simulator scenario emits HL7 the engine actually parses.

The simulator is only useful if what it sends is indistinguishable (to the
parser) from a real B.Braun pump, so these tests run each scenario in dry-run
mode and push every generated message through engine.hl7.

Run from infusion_engine/:  python -m tests.test_simulator
"""

import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import simulator
from engine import hl7
from tests.sample_utils import extract_messages, load_all_samples


def codes_in_real_captures():
    """Every OBX code that appears in tests/samples/ - i.e. that a real pump sends."""
    codes = set()
    for _, raw in load_all_samples():
        for fields in hl7.split_segments(raw):
            if fields[0] == 'OBX' and len(fields) > 3:
                codes.add(fields[3].split('^')[0])
    return codes


def generate(scenario_key, extra_args=None):
    """Run one scenario without sending anything; return its raw messages."""
    with tempfile.TemporaryDirectory() as tmp:
        path = os.path.join(tmp, 'out.hl7')
        argv = [scenario_key, '--dry-run', '--quiet', '--speed', '0', '--out', path]
        args = simulator.build_parser().parse_args(argv + (extra_args or []))
        sim = simulator.Simulator(args)
        try:
            simulator.SCENARIOS[scenario_key].run(sim)
        finally:
            sim.close()
        with open(path, encoding='utf-8') as handle:
            return extract_messages(handle.read())


def parse_all(scenario_key, extra_args=None):
    return [hl7.parse_message(raw) for raw in generate(scenario_key, extra_args)]


class TestEveryScenarioParses(unittest.TestCase):
    """Every scenario except the deliberately broken one must parse cleanly."""

    def test_all_scenarios(self):
        skip = {'malformed', 'replay'}   # broken on purpose / already covered by samples
        for key in simulator.SCENARIOS:
            if key in skip:
                continue
            with self.subTest(scenario=key):
                messages = parse_all(key)
                self.assertTrue(messages, f'{key}: produced no messages')
                for msg in messages:
                    self.assertTrue(msg.message_type, f'{key}: message without a type')
                    self.assertTrue(msg.message_control_id, f'{key}: message without a control id')
                    self.assertTrue(msg.device_id, f'{key}: message without a device id')
                    self.assertIn(msg.trigger_event, ('R01', 'R40', 'R42'))

    def test_no_invented_observation_codes(self):
        """Every OBX code must be one the parser knows or one a real pump sends."""
        allowed = set(hl7.MDC_CODES) | codes_in_real_captures()
        for key in simulator.SCENARIOS:
            if key in ('malformed', 'replay'):
                continue
            for raw in generate(key):
                for fields in hl7.split_segments(raw):
                    if fields[0] != 'OBX' or len(fields) < 4:
                        continue
                    code = fields[3].split('^')[0]
                    self.assertIn(code, allowed,
                                  f'{key}: OBX code {code} ({fields[3]}) is neither in '
                                  'MDC_CODES nor in any captured B.Braun sample')


class TestInfusionLifecycle(unittest.TestCase):
    """normal: idle -> delivery start -> periodic status -> complete + alarm."""

    @classmethod
    def setUpClass(cls):
        cls.messages = parse_all('normal')

    def test_identity_is_stable_across_the_run(self):
        device_ids = {m.device_id for m in self.messages}
        self.assertEqual(len(device_ids), 1, 'all messages must key to one pump record')
        first = self.messages[0]
        self.assertEqual(first.station_id, simulator.STATION_EUI)
        self.assertEqual(first.pump_label, 'I51559')
        self.assertEqual(first.pump_type, 'lvp')
        self.assertEqual(first.ward, 'WARD D5')
        self.assertEqual(first.facility, 'PHKL')
        self.assertEqual(first.values['pump_model'], 'B Braun SpacePlus Infusomat')

    def test_delivery_events_bracket_the_infusion(self):
        events = [m for m in self.messages if m.trigger_event == 'R42']
        self.assertEqual(len(events), 2, 'expected a start and a completion PCD-10')
        self.assertEqual(events[0].pcd_profile, 'IHE_PCD_010')
        self.assertEqual(events[0].values['pump_status'], 'infusing')
        self.assertEqual(events[1].values['pump_status'], 'not-infusing')
        self.assertEqual(events[1].values['volume_remaining'], 0.0)

    def test_volumes_only_ever_move_forward(self):
        readings = [m for m in self.messages
                    if m.trigger_event in ('R01', 'R42') and 'volume_infused' in m.values]
        infused = [m.values['volume_infused'] for m in readings]
        self.assertEqual(infused, sorted(infused), 'volume infused went backwards')
        self.assertEqual(infused[-1], 100.0, 'infusion should finish on its VTBI')
        for msg in readings:
            self.assertAlmostEqual(
                msg.values['vtbi'],
                msg.values['volume_infused'] + msg.values['volume_remaining'], places=1,
                msg='vtbi must equal infused + remaining in every reading')
            self.assertEqual(msg.units['flow_rate'], 'mL/h')
            self.assertEqual(msg.units['vtbi'], 'mL')

    def test_rate_is_zero_whenever_the_pump_is_not_infusing(self):
        for msg in self.messages:
            if msg.values.get('pump_status') == 'not-infusing':
                self.assertEqual(msg.values.get('flow_rate'), 0.0)
                self.assertEqual(msg.values.get('delivery_status'), 'not-delivering')

    def test_near_end_condition_appears_before_completion(self):
        """sample1 carries MDC_ATTR_AL_COND inside PCD-01 as a pre-alarm."""
        near_end = [m for m in self.messages
                    if m.values.get('alarm_condition') == 'MDC_EVT_VOL_INFUS_NEAR_COMP']
        self.assertTrue(near_end, 'PCD-01 should flag the near-end condition, like sample1')
        self.assertLessEqual(near_end[0].values['volume_remaining'],
                             near_end[0].values['vtbi'] * 0.1)

    def test_completion_alarm(self):
        alarms = [m.alarm for m in self.messages if m.trigger_event == 'R40']
        self.assertTrue(alarms)
        self.assertEqual(alarms[0]['alert_text'], 'Infusion Complete')
        self.assertEqual([a['phase'] for a in alarms], ['start', 'update', 'end'])
        self.assertEqual([a['state'] for a in alarms], ['active', 'active', 'inactive'])

    def test_patient_is_unknown_like_a_real_pump(self):
        for msg in self.messages:
            self.assertIsNone(msg.patient_mrn)
            self.assertEqual(msg.patient_name, 'Unknown Patient')


class TestSyringePump(unittest.TestCase):
    """syringe: the Perfusor shape from sample1 - dose, concentration, syringe."""

    @classmethod
    def setUpClass(cls):
        cls.messages = parse_all('syringe')

    def test_syringe_specific_fields(self):
        infusing = [m for m in self.messages if m.values.get('pump_status') == 'infusing']
        self.assertTrue(infusing)
        msg = infusing[0]
        self.assertEqual(msg.pump_type, 'syringe')
        self.assertEqual(msg.values['pump_model'], 'B Braun SpacePlus Perfusor')
        self.assertEqual(msg.medication, 'Dobutamine')
        self.assertEqual(msg.values['drug_name'], 'Dobutamine')
        self.assertEqual(msg.values['drug_concentration'], 5.0)
        self.assertEqual(msg.units['drug_concentration'], 'mg/mL')
        self.assertEqual(msg.values['syringe_size'], 50.0)
        self.assertEqual(msg.values['patient_weight'], 100.0)
        self.assertEqual(msg.values['care_area'], 'Global Surgery')

    def test_dose_tracks_volume_through_the_concentration(self):
        for msg in self.messages:
            if 'dose_delivered' not in msg.values:
                continue
            expected = msg.values['volume_infused'] * 5.0 * 1000
            self.assertAlmostEqual(msg.values['dose_delivered'], expected, places=1)
            self.assertEqual(msg.units['dose_delivered'], 'ug')


class TestAlarmScenarios(unittest.TestCase):
    def test_syringe_holder_open_matches_the_captured_sequence(self):
        alarms = [m.alarm for m in parse_all('syringe-holder-open') if m.trigger_event == 'R40']
        self.assertEqual(len(alarms), 4, 'sample2 is a four-message sequence')
        for alarm in alarms:
            self.assertEqual(alarm['alert_text'], 'Syringe Holder Open')
            self.assertEqual(alarm['priority'], 'PH')
            self.assertEqual(alarm['alert_type'], 'ST')
            self.assertEqual(alarm['event_name'], 'MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT')
            self.assertEqual(alarm['event_code'], '197218')
        self.assertEqual([a['phase'] for a in alarms], ['start', 'update', 'update', 'end'])
        self.assertEqual([a['state'] for a in alarms],
                         ['active', 'active', 'active', 'inactive'])
        self.assertEqual([a['inactivation'] for a in alarms],
                         ['enabled', 'audio-paused', 'audio-paused', 'alarm-off'])

    def test_occlusion_stops_delivery_and_resumes(self):
        messages = parse_all('occlusion')
        reasons = [m.values.get('not_delivering_reason') for m in messages]
        self.assertIn('pump-stopped-alarming', reasons)
        statuses = [m.values.get('pump_status') for m in messages
                    if 'pump_status' in m.values]
        self.assertIn('infusing', statuses[statuses.index('not-infusing'):],
                      'the infusion should resume after the alarm is cleared')

    def test_repeated_alarms_are_separate_occurrences(self):
        alarms = [m.alarm for m in parse_all('air-in-line') if m.trigger_event == 'R40']
        starts = [a for a in alarms if a['phase'] == 'start']
        self.assertEqual(len(starts), 2, 'the same alarm firing twice = two start events')

    def test_alarm_identity_default_binds_to_the_pump(self):
        messages = parse_all('occlusion')
        self.assertEqual(len({m.device_id for m in messages}), 1,
                         'with MDC_ATTR_SYS_ID, alarms land on the same pump record')

    def test_alarm_identity_realistic_reproduces_the_capture_split(self):
        """--alarm-identity realistic = no SYS_ID in PCD-04, exactly like sample2."""
        messages = parse_all('occlusion', ['--alarm-identity', 'realistic'])
        alarms = [m for m in messages if m.trigger_event == 'R40']
        status = [m for m in messages if m.trigger_event != 'R40'][0]
        self.assertTrue(alarms)
        for msg in alarms:
            self.assertNotIn('device_uuid', msg.values)
            self.assertEqual(msg.device_id, f'{simulator.STATION_EUI}:{msg.pump_label}')
            self.assertNotEqual(msg.device_id, status.device_id)


class TestDeviceReports(unittest.TestCase):
    def test_battery_scenario_reports_power_and_charge(self):
        messages = parse_all('battery')
        reports = [m for m in messages if m.pcd_profile == 'IHE_PCD_015']
        self.assertTrue(reports, 'the battery scenario must send PCD-15 device reports')
        self.assertEqual(reports[0].values['power_status'], 'onMains(0)')
        on_battery = [m for m in reports if m.values['power_status'] == 'onBattery(1)']
        self.assertTrue(on_battery)
        charges = [m.values['battery_percent'] for m in on_battery]
        self.assertEqual(charges, sorted(charges, reverse=True), 'battery should only drain')
        for msg in reports:
            self.assertEqual(msg.values['wifi_ssid'], 'BBraunPumps')
            self.assertEqual(msg.values['device_ip'], '10.0.0.13')
            self.assertTrue(msg.values['firmware_version'].startswith('Pump Firmware'))
        texts = [m.alarm['alert_text'] for m in messages if m.trigger_event == 'R40']
        self.assertIn('Battery Low', texts)
        self.assertIn('Battery Empty', texts)

    def test_wifi_drop_backfills_with_original_timestamps(self):
        messages = parse_all('wifi-drop')
        stamps = [m.observed_at for m in messages if m.observed_at]
        self.assertNotEqual(stamps, sorted(stamps),
                            'buffered messages should arrive after newer ones, out of order')

    def test_power_off_uses_the_captured_reason(self):
        reasons = [m.values.get('not_delivering_reason') for m in parse_all('power-off')]
        self.assertIn('pump-stopped-powered-off', reasons)


class TestFleetScenarios(unittest.TestCase):
    def test_multi_pump_keeps_pumps_and_wards_apart(self):
        messages = parse_all('multi-pump')
        by_device = {}
        for msg in messages:
            by_device.setdefault(msg.device_id, msg)
        self.assertEqual(len(by_device), 4)
        self.assertEqual({m.ward for m in by_device.values()}, {'WARD D5', 'ICU 1'})
        self.assertEqual({m.pump_type for m in by_device.values()}, {'lvp', 'syringe'})

    def test_alarm_storm_produces_alarms_on_every_pump(self):
        messages = parse_all('alarm-storm')
        alarmed = {m.device_id for m in messages if m.trigger_event == 'R40'}
        self.assertEqual(len(alarmed), 6)


class TestMalformedScenario(unittest.TestCase):
    """The robustness pack must contain messages the parser genuinely rejects."""

    def test_broken_messages_fail_and_the_rest_survive(self):
        raws = generate('malformed')
        rejected = passed = 0
        for raw in raws:
            try:
                hl7.parse_message(raw)
                passed += 1
            except ValueError:
                rejected += 1
        self.assertTrue(passed, 'some messages should still parse')
        # extract_messages() only yields MSH-led blocks, so the "no MSH" and
        # "not HL7" cases are checked directly here.
        for junk in ('the pump gateway sent us prose\r', 'OBX|1|NM|158014^X^MDC|1|5\r'):
            with self.assertRaises(ValueError):
                hl7.parse_message(junk)


class TestOverridesAndClock(unittest.TestCase):
    def test_cli_overrides_apply_to_the_first_pump(self):
        messages = parse_all('normal', ['--label', 'I99001', '--ward', 'WARD A1',
                                        '--facility', 'HKL', '--drug', 'Ceftriaxone',
                                        '--rate', '50', '--vtbi', '25'])
        first = messages[0]
        self.assertEqual(first.pump_label, 'I99001')
        self.assertEqual(first.ward, 'WARD A1')
        self.assertEqual(first.facility, 'HKL')
        infusing = [m for m in messages if m.values.get('pump_status') == 'infusing']
        self.assertEqual(infusing[0].values['flow_rate'], 50.0)
        self.assertEqual(infusing[0].values['vtbi'], 25.0)
        self.assertEqual(infusing[0].values['drug_name'], 'Ceftriaxone')

    def test_mrn_override_sends_an_identified_pid(self):
        messages = parse_all('idle', ['--mrn', 'MRN12345', '--patient', 'DOE^JOHN'])
        self.assertEqual(messages[0].patient_mrn, 'MRN12345')
        self.assertEqual(messages[0].patient_name, 'JOHN DOE')

    def test_start_time_backdates_every_timestamp(self):
        messages = parse_all('idle', ['--start-time', '2026-08-05T09:00:00'])
        self.assertTrue(messages[0].message_datetime.startswith('2026-08-05T09:00'))
        self.assertTrue(all(m.observed_at.startswith('2026-08-05') for m in messages))

    def test_pump_identity_is_deterministic_between_runs(self):
        first = parse_all('idle')[0].device_id
        second = parse_all('idle')[0].device_id
        self.assertEqual(first, second, 're-running a scenario must update the same pump')


if __name__ == '__main__':
    unittest.main(verbosity=2)
