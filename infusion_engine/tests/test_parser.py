"""Parser unit tests against the real B.Braun sample messages.

Run from infusion_engine/:  python -m tests.test_parser
"""

import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from engine import hl7
from tests.sample_utils import load_all_samples


class TestSampleParsing(unittest.TestCase):
    def test_all_samples_parse(self):
        samples = load_all_samples()
        self.assertGreaterEqual(len(samples), 6)
        for name, raw in samples:
            with self.subTest(sample=name):
                msg = hl7.parse_message(raw)
                self.assertTrue(msg.message_type, f'{name}: no message type')
                self.assertTrue(msg.message_control_id, f'{name}: no control id')

    def test_pcd01_real_lvp_status(self):
        """sample6_real: PCD-01 from a real ward Infusomat, stopped/powered off."""
        raw = dict(load_all_samples())['sample6_real.txt#1']
        msg = hl7.parse_message(raw)
        self.assertEqual(msg.trigger_event, 'R01')
        self.assertEqual(msg.pcd_profile, 'IHE_PCD_001')
        # MSH-3 EUI-64 is the SpaceStation (shared by many pumps); the unique
        # per-pump key comes from MDC_ATTR_SYS_ID
        self.assertEqual(msg.station_id, '0012211839000001')
        self.assertEqual(msg.device_id, '743d27f8-c72b-516b-8622-e7f2df7b6ec5')
        self.assertEqual(msg.pump_type, 'lvp')
        self.assertEqual(msg.pump_label, 'I51559')
        self.assertEqual(msg.ward, 'WARD D5')
        self.assertEqual(msg.facility, 'PHKL')
        self.assertEqual(msg.values['pump_model'], 'B Braun SpacePlus Infusomat')
        self.assertEqual(msg.values['drug_library'], 'PHKL_D5')
        self.assertEqual(msg.values['pump_status'], 'not-infusing')
        self.assertEqual(msg.values['delivery_status'], 'not-delivering')
        self.assertEqual(msg.values['not_delivering_reason'], 'pump-stopped-powered-off')
        self.assertEqual(msg.values['flow_rate'], 0.0)
        self.assertEqual(msg.units['flow_rate'], 'mL/h')
        self.assertIsNone(msg.alarm)

    def test_pcd01_syringe_infusing(self):
        """sample1: PCD-01 during a Dobutamine infusion on a syringe pump."""
        raw = dict(load_all_samples())['sample1.txt#1']
        msg = hl7.parse_message(raw)
        self.assertEqual(msg.trigger_event, 'R01')
        self.assertEqual(msg.pump_type, 'syringe')
        self.assertEqual(msg.medication, 'Dobutamine')
        self.assertIn('pump_status', msg.values)

    def test_pcd04_alarm_sequence(self):
        """sample2: four PCD-04 messages (active, muted, minimized, cleared)."""
        samples = dict(load_all_samples())
        msgs = [hl7.parse_message(samples[f'sample2.txt#{i}']) for i in range(1, 5)]
        for m in msgs:
            self.assertEqual(m.trigger_event, 'R40')
            self.assertIsNotNone(m.alarm, 'PCD-04 should produce an alarm')
            self.assertEqual(m.alarm['alert_text'], 'Syringe Holder Open')
            self.assertEqual(m.alarm['priority'], 'PH')
            self.assertEqual(m.alarm['event_name'], 'MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT')

        self.assertEqual([m.alarm['phase'] for m in msgs], ['start', 'update', 'update', 'end'])
        self.assertEqual([m.alarm['state'] for m in msgs],
                         ['active', 'active', 'active', 'inactive'])
        # No MDC_ATTR_SYS_ID in PCD-04 - identity falls back to station:label
        self.assertEqual(msgs[0].device_id, '0012211839000001:2449')

    def test_pillar_details_spread_ward_format(self):
        """Some gateways send the ward spread over components: '~~~Ward~~~~D6'.
        Built from a production message (id 68120, ward D6 gateway)."""
        raw = (
            'MSH|^~\\&|PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|BBRAUN|Qmed|Qmed|'
            '20260806035600+0000||ORU^R01^ORU_R01|63921585360712943168132|P|2.4|||AL|NE'
            '||ASCII|en^English^ISO639||IHE_PCD_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.1^ISO\r'
            'PID|||Unknown Patient^Unknown Patient||^^^^^^U||||||||||||||||||||||||||Y\r'
            'OBR|1|0^PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|'
            '0^PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|999999^Unknown medication|||'
            '20260806035556+0000\r'
            'OBX|1||70049^MDC_DEV_PUMP_INFUS_LVP_MDS^MDC|1.0.0.0|||||||X|||||||'
            'I51316^^0012210000000000^EUI-64\r'
            'OBX|6|ST|67972^MDC_ATTR_SYS_ID^MDC|1.1.0.9|'
            '46637152-3159-5f31-be9c-86e104aaf384||||||F\r'
            'OBX|7|ST|0^MDC_ATTR_PUMP_PILLAR_DETAILS^MDC|1.1.0.24|0^0||||||F|||||||'
            '~~~Ward~~~~D6^^0012210000000000^EUI-64\r'
            'OBX|9|CWE|184519^MDC_PUMP_INFUSING_STATUS^MDC|1.1.1.1|'
            '^pump-status-not-infusing||||||R\r'
        )
        msg = hl7.parse_message(raw)
        self.assertEqual(msg.pump_label, 'I51316')
        self.assertEqual(msg.device_id, '46637152-3159-5f31-be9c-86e104aaf384')
        self.assertEqual(msg.ward, 'Ward D6')
        self.assertIsNone(msg.facility)
        # The original single-slot format still parses as before
        std = dict(load_all_samples())['sample6_real.txt#1']
        std_msg = hl7.parse_message(std)
        self.assertEqual(std_msg.ward, 'WARD D5')
        self.assertEqual(std_msg.facility, 'PHKL')

    def test_wrapped_msh_line_is_joined(self):
        """sample6_real has its MSH control id wrapped across two lines."""
        raw = dict(load_all_samples())['sample6_real.txt#1']
        msg = hl7.parse_message(raw)
        self.assertEqual(msg.message_control_id, '639070847165381484276804')

    def test_ack_contains_control_id(self):
        raw = dict(load_all_samples())['sample6_real.txt#1']
        msg = hl7.parse_message(raw)
        ack = hl7.build_ack(msg, 'AA')
        self.assertIn('MSA|AA|639070847165381484276804', ack)
        self.assertIn('INFUSION_ENGINE', ack)

    def test_timestamp_parsing(self):
        self.assertEqual(hl7.parse_hl7_timestamp('20260219075836+0000'),
                         '2026-02-19T07:58:36+00:00')
        self.assertEqual(hl7.parse_hl7_timestamp('20221201132051-0600'),
                         '2022-12-01T13:20:51-06:00')
        self.assertIsNone(hl7.parse_hl7_timestamp(''))
        self.assertIsNone(hl7.parse_hl7_timestamp('garbage'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
