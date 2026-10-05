"""Admission dates from the census and reservation requests (no network, made-up rows)."""

import os
import sys
import unittest
from datetime import datetime

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scraper.admissions import apply_census, apply_requests, iso_local, parse_his_time, parse_requests  # noqa: E402


def request_item(mrn, requested):
    title = f"MRN : {mrn}\nPatient Name : TEST PATIENT\nPhysician Name : DR TEST\nLocation : WARD C4\nPlan Date : 29.09.2026"
    return (f'<a class="draggable-reservation-request-item fancy-popup" data-status="1" href="/PM//Reservation/RequestDetail?requestId=1">'
            f'<div><i class="fa fa-info" title="{title}"></i></div>'
            f'<label>TEST PATIENT</label><label>DR TEST</label><label>Request Date: {requested}</label></a>')


class AdmissionDatesTest(unittest.TestCase):
    def test_his_times(self):
        self.assertEqual(datetime(2026, 9, 25, 8, 30), parse_his_time("25.09.2026 08:30:00"))
        self.assertEqual(datetime(2026, 9, 25), parse_his_time("25.09.2026"))
        self.assertIsNone(parse_his_time(""))
        self.assertEqual("2026-09-25T08:30:00+08:00", iso_local(datetime(2026, 9, 25, 8, 30)))

    def test_the_census_row_of_this_stay_wins_else_the_latest_entry(self):
        census = {
            "3200000001": [("PHAK26IP00000009", datetime(2026, 9, 1, 9, 0)), ("PHAK26IP00000001", datetime(2026, 9, 25, 8, 30))],
            "3200000002": [("OTHER-RN", datetime(2026, 9, 20, 7, 0)), ("OTHER-RN-2", datetime(2026, 9, 27, 7, 0))],
        }
        siti = {"mrn": "3200000001", "rn": "PHAK26IP00000009"}
        ali = {"mrn": "3200000002", "rn": "PHAK26IP00000002"}
        nobody = {"mrn": "3200000003", "rn": "PHAK26IP00000003"}

        missing = apply_census([siti, ali, nobody], census)

        self.assertEqual("2026-09-01T09:00:00+08:00", siti["admitted_at"])
        self.assertEqual("census", siti["admitted_at_source"])
        self.assertEqual("2026-09-27T07:00:00+08:00", ali["admitted_at"])
        self.assertEqual([nobody], missing)
        self.assertNotIn("admitted_at", nobody)

    def test_request_dates_fill_in_whoever_the_census_missed(self):
        html = request_item("3200000003", "28.09.2026 16:45:10") + request_item("3200000999", "27.09.2026 10:00:00")
        requested = dict(parse_requests(html))
        self.assertEqual(datetime(2026, 9, 28, 16, 45, 10), requested["3200000003"])

        nobody = {"mrn": "3200000003", "rn": "PHAK26IP00000003"}
        apply_requests([nobody], requested)
        self.assertEqual("2026-09-28T16:45:10+08:00", nobody["admitted_at"])
        self.assertEqual("request", nobody["admitted_at_source"])


if __name__ == "__main__":
    unittest.main()
