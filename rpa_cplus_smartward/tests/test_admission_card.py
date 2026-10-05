"""The Isolation box of C+ admission cards (no network). The cards are made up,
shaped like PatientAdmissionCard's Patient Information flags: an icon cell and
a label cell per flag."""

import json
import os
import sys
import tempfile
import unittest
from unittest import mock

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scraper import admission_card  # noqa: E402
from scraper.admission_card import attach_isolation, card_url, due_for_reading, parse_isolation  # noqa: E402

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def card(isolation_icon="fa fa-times", with_isolation=True):
    flags = [("fa fa-times", "Please do not disturb"), ("fa fa-times", "Risk Of Fall")]
    if with_isolation:
        flags.append((isolation_icon, "Isolation"))
    flags.append(("fa fa-check", "Minibar"))
    rows = "".join(f'<tr><td><i class="{icon}"></i></td><td>{label}</td></tr>' for icon, label in flags)
    return ('<form id="frmPatientAdmissionCard"><table><tr><td>Licence Plate</td>'
            f'<td rowspan="5"><table class="table">{rows}</table></td></tr></table></form>')


def ward(*patients):
    return [{"id": "190", "name": "ICU", "beds": [
        {"id": str(12640 + i), "name": f"217-{i}", "patient": p} for i, p in enumerate(patients)
    ] + [{"id": "12699", "name": "217-Z", "patient": None}]}]


def patient(mrn, rn="PHAK26IP00000001", card_link=True):
    return {"mrn": mrn, "rn": rn, "name": "X", "gender": "", "physician": "",
            "card": f"/PM//PatientAdmission/PatientAdmissionCard?patientId={mrn}&bedId=1" if card_link else ""}


class FakeSession:
    """Answers GETs with the card the test gave each MRN; a number is an HTTP error."""

    def __init__(self, pages):
        self.pages = pages
        self.urls = []

    def get(self, url, headers=None, params=None):
        self.urls.append(url)
        page = self.pages[url.split("patientId=")[1].split("&")[0]]
        if isinstance(page, int):
            return {"success": True, "http_code": str(page), "stdout": "", "stderr": ""}
        return {"success": True, "http_code": "200", "stdout": page, "stderr": ""}


class ParseIsolationTest(unittest.TestCase):
    def test_a_cross_is_not_ticked_and_a_check_is_ticked(self):
        self.assertIs(False, parse_isolation(card("fa fa-times")))
        self.assertIs(True, parse_isolation(card("fa fa-check")))
        self.assertIs(True, parse_isolation(card("fa fa-check-square-o")))

    def test_anything_else_is_left_unread(self):
        self.assertIsNone(parse_isolation(card("fa fa-question")))
        self.assertIsNone(parse_isolation(card(with_isolation=False)))
        self.assertIsNone(parse_isolation(""))

    @unittest.skipUnless(os.path.exists(os.path.join(BASE, "scratch", "admission_card.html")),
                         "no saved C+ admission card")
    def test_the_saved_c_plus_card(self):
        with open(os.path.join(BASE, "scratch", "admission_card.html"), encoding="utf-8") as fh:
            self.assertIs(False, parse_isolation(fh.read()))


class ReadingTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.cache_file = os.path.join(self.tmp.name, "isolation_cache.json")
        patcher = mock.patch.object(admission_card, "SCRATCH_DIR", os.path.join(self.tmp.name, "scratch"))
        patcher.start()
        self.addCleanup(patcher.stop)
        self.addCleanup(self.tmp.cleanup)

    def test_never_read_cards_come_first_then_the_oldest_past_the_refresh_time(self):
        a, b, c, d = patient("1"), patient("2"), patient("3"), patient("4", card_link=False)
        cache = {"1|PHAK26IP00000001": {"isolation": True, "read_at": 1000},
                 "2|PHAK26IP00000001": {"isolation": False, "read_at": 5000}}
        now = 1000 + 31 * 60
        self.assertEqual([c, a], due_for_reading([a, b, c, d], cache, now, 30, 60))
        self.assertEqual([c], due_for_reading([a, b, c, d], cache, now, 30, 1))

    def test_each_patient_gets_what_their_card_says_and_the_links_stay_here(self):
        wards = ward(patient("1"), patient("2"), patient("3"), patient("4", card_link=False), patient("5"))
        session = FakeSession({"1": card("fa fa-check"), "2": card("fa fa-times"), "3": card("fa fa-question"), "5": 500})

        counts = attach_isolation(wards, session, self.cache_file, 30, 60, now=10_000)

        people = [b["patient"] for b in wards[0]["beds"] if b["patient"]]
        self.assertEqual([True, False, None, None, None], [p.get("isolation") for p in people])
        self.assertTrue(all("card" not in p for p in people))
        self.assertEqual({"read": 2, "unread": 1, "failed": 1, "ticked": 1, "known": 2, "patients": 5}, counts)
        self.assertEqual(4, len(session.urls))
        self.assertTrue(all(u.startswith(admission_card.HIS_BASE_URL + "/PM//PatientAdmission/") for u in session.urls))
        # The card nobody could read is kept to look at
        self.assertTrue(os.path.exists(os.path.join(self.tmp.name, "scratch", "admission_card_unread.html")))

        with open(self.cache_file, encoding="utf-8") as fh:
            self.assertEqual({"1|PHAK26IP00000001", "2|PHAK26IP00000001"}, set(json.load(fh)))

    def test_within_the_refresh_time_the_cache_answers_and_patients_who_left_are_forgotten(self):
        attach_isolation(ward(patient("1"), patient("2")), FakeSession({"1": card("fa fa-check"), "2": card()}),
                         self.cache_file, 30, 60, now=10_000)

        session = FakeSession({})
        wards = ward(patient("1"))
        attach_isolation(wards, session, self.cache_file, 30, 60, now=10_000 + 29 * 60)

        self.assertEqual([], session.urls)
        self.assertIs(True, wards[0]["beds"][0]["patient"]["isolation"])
        with open(self.cache_file, encoding="utf-8") as fh:
            self.assertEqual({"1|PHAK26IP00000001"}, set(json.load(fh)))

    def test_a_new_stay_is_read_afresh(self):
        attach_isolation(ward(patient("1")), FakeSession({"1": card("fa fa-check")}), self.cache_file, 30, 60, now=10_000)

        session = FakeSession({"1": card("fa fa-times")})
        wards = ward(patient("1", rn="PHAK26IP00000002"))
        attach_isolation(wards, session, self.cache_file, 30, 60, now=10_060)

        self.assertEqual(1, len(session.urls))
        self.assertIs(False, wards[0]["beds"][0]["patient"]["isolation"])

    def test_only_c_plus_pages_are_opened(self):
        path = "/PM//PatientAdmission/PatientAdmissionCard?patientId=1&bedId=2"
        self.assertEqual(admission_card.HIS_BASE_URL + path, card_url(path))
        self.assertEqual(admission_card.HIS_BASE_URL + path, card_url(admission_card.HIS_BASE_URL + path))
        self.assertIsNone(card_url("https://elsewhere.example/PatientAdmissionCard?patientId=1"))
        self.assertIsNone(card_url("PatientAdmissionCard?patientId=1"))


if __name__ == "__main__":
    unittest.main()
