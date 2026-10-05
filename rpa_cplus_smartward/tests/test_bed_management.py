"""Parsing C+ Bed Management rows (no network). The rows are made up, shaped
like GetRoomsByStyle's list view: the "Location / Room No / Type" cell only on
the first bed of each room."""

import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scraper.bed_management import all_locations_url, parse_rows, split_mrn_rn, summarise, to_wards  # noqa: E402


def row(bed_id, bed, location_id, location, room, colour, mrn_rn="-", gender="", name="", physician="", note="", first=False, card=False):
    hidden = "".join(
        f'<input type="hidden" id="{k}" value="{v}" />' for k, v in {
            "dropBedType": "", "dropDoorNo": bed_id, "dropRoomName": room, "dropLocationID": location_id,
            "dropLocationName": location, "dropRoomNo": room, "dropPreReservationStatus": "1",
            "dropPreReservationPatientID": " 0", "dropPreReservationPatientName": " 0", "dropBedName": bed,
        }.items())
    head = f'<td class="room-table-group location-info" rowspan="2">{room} <br/>{location} <br/></td>' if first else ""
    gender_icon = f'<i class="fa fa-{gender.lower()}" title="{gender}"></i>' if gender else ""
    # The menu's link to the admission card, as C+ writes it on an occupied ward bed
    menu = (f'<a class="fancy-popup" data-width="1100" href="/PM//PatientAdmission/PatientAdmissionCard'
            f'?patientId={mrn_rn.split(" ")[0]}&amp;bedId={bed_id}">Patient Admission Card</a>') if card else ""
    return (f"<tr>{head}<td class=\"noprint\"><i class=\"fa fa-stop\" style=\"color:{colour}\"></i></td>"
            f"<td>{bed}</td><td>{mrn_rn}</td><td>{gender_icon} {name}</td><td>{physician}</td>"
            f"<td><label title=\"{note}\">{note}</label></td><td></td><td class=\"noprint\">{menu}{hidden}</td></tr>")


HTML = "<tbody>" + "".join([
    row("12646", "217-A", "190", "INTENSIVE CARE UNIT (ICU)", "217", "#999999",
        "3200000001 - PHAK26IP00000001", "Female", "SITI BINTI ALI", "DR TAN AH KOW", first=True, card=True),
    row("12647", "217-B", "190", "INTENSIVE CARE UNIT (ICU)", "217", "#00FF00"),
    row("12648", "218-A", "190", "INTENSIVE CARE UNIT (ICU)", "218", "#FF3333", note="Maintenance", first=True),
    row("13001", "C401", "699", "WARD C4", "C401", "#FFC914",
        "3200000002 - PHAK26IP00000002", "Male", "ALI BIN ABU", "DR LEE", first=True),
]) + "</tbody><script>var x = 1;</script>"


class ParseRowsTest(unittest.TestCase):
    def setUp(self):
        self.beds = parse_rows(HTML)

    def test_every_bed_is_read_with_or_without_the_location_cell(self):
        self.assertEqual(["217-A", "217-B", "218-A", "C401"], [b["bed_name"] for b in self.beds])
        self.assertEqual(["12646", "12647", "12648", "13001"], [b["bed_id"] for b in self.beds])
        self.assertEqual("190", self.beds[1]["location_id"])
        self.assertEqual("217", self.beds[1]["room_no"])

    def test_status_comes_from_the_patient_or_the_icon_colour(self):
        self.assertEqual(["occupied", "available", "out_of_service", "occupied"], [b["status"] for b in self.beds])
        self.assertEqual("#FFC914", self.beds[3]["colour"])

    def test_the_patient_columns(self):
        siti = self.beds[0]
        self.assertEqual(("3200000001", "PHAK26IP00000001"), (siti["mrn"], siti["rn"]))
        self.assertEqual("Female", siti["gender"])
        self.assertEqual("SITI BINTI ALI", siti["patient_name"])
        self.assertEqual("DR TAN AH KOW", siti["physician"])
        self.assertEqual("", self.beds[1]["mrn"])
        self.assertEqual("Maintenance", self.beds[2]["note"])

    def test_an_occupied_ward_bed_links_its_admission_card(self):
        self.assertEqual("/PM//PatientAdmission/PatientAdmissionCard?patientId=3200000001&bedId=12646",
                         self.beds[0]["card_path"])
        self.assertEqual("", self.beds[3]["card_path"])  # no link in its row
        self.assertEqual(self.beds[0]["card_path"], to_wards(self.beds)[0]["beds"][0]["patient"]["card"])

    def test_to_wards_groups_by_location_and_counts_a_bed_once(self):
        wards = to_wards(self.beds + self.beds)
        self.assertEqual(["190", "699"], [w["id"] for w in wards])
        self.assertEqual(3, len(wards[0]["beds"]))
        self.assertEqual("3200000001", wards[0]["beds"][0]["patient"]["mrn"])
        self.assertIsNone(wards[0]["beds"][1]["patient"])
        self.assertEqual("out_of_service", wards[0]["beds"][2]["status"])

    def test_the_summary_has_no_patient_detail(self):
        text = "\n".join(summarise(to_wards(self.beds)))
        self.assertIn("3 beds", text)
        self.assertNotIn("SITI", text)
        self.assertNotIn("3200000001", text)

    def test_each_ward_carries_its_unit_group_and_er_reads_page_two(self):
        rows = [dict(b, unit_group="3") for b in self.beds]
        self.assertEqual(["3", "3"], [w["unit_group"] for w in to_wards(rows)])
        self.assertIn("unitGroupId=3", all_locations_url("3"))
        self.assertIn("pageId=2", all_locations_url("3"))
        self.assertIn("pageId=1", all_locations_url("2"))
        self.assertIn("location=XXX", all_locations_url("2"))

    def test_split_mrn_rn(self):
        self.assertEqual(("3200112068", "PHAK26IP09000087"), split_mrn_rn("3200112068 - PHAK26IP09000087"))
        self.assertEqual(("", ""), split_mrn_rn("-"))


if __name__ == "__main__":
    unittest.main()
