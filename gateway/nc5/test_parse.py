"""
Offline checks for the NC5 interpreter and query responder — no server, no
network. Run after touching listener/src: `python test_parse.py`.

Every message here is taken from (or modelled on) real NC5 traffic in logs/.
"""

import os
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), "listener", "src"))

from hl7_oru import is_observation, parse_message  # noqa: E402
from hl7_query import build_response, is_query, parse_query  # noqa: E402

CR = "\r"
SETTINGS = SimpleNamespace(
    ack_app="NC5_GATEWAY", ack_facility="SMARTWARD", patient_name_order="full",
)
failures = []


def check(label, actual, expected):
    ok = actual == expected
    print(f"  {'ok  ' if ok else 'FAIL'} {label}: {actual!r}")
    if not ok:
        failures.append(f"{label}: expected {expected!r}, got {actual!r}")


# --- the NC5's patient query (verbatim from logs/hl7-2026-07-23.log) ---------
QRY_R02 = CR.join([
    r"MSH|^~\&||KC575235977G|||20260723224527.3793+0800||QRY^R02|20260723224527.38070003|P|2.6",
    r"QRD|20260723224527.3830+0800|R|I|Q101||||12345|RES",
    r"PID|||12345||UNKNOWN^PATIENT||19000101|M||||||||||12345",
    r"PV1||I|^^&24",
    r"QRF|MON||||3232235520&0^5^1^0^2005&2005&2005&2005&2005",
]) + CR

QRY_A19 = CR.join([
    r"MSH|^~\&|Mindray|Gateway|||20260724134901||QRY^A19|2|P|2.3.1",
    r"QRD|20260724134901000|D|D|1|||1^RD|12345|^DEM|^MindrayGateway",
]) + CR

# What GET /api/v1/patients/{code} returns (VitalSignApiV1Controller::searchPatient)
PATIENT = {
    "patient_code": "MRN00042", "name": "Ahmad Bin Ali", "date_of_birth": "1980-01-15",
    "gender": "Male", "mrn": "MRN00042", "visit_number": "V900", "ic_passport": "800115-10-5533",
    "is_admitted": True, "ward_name": "ICU", "bed_number": "01",
}

print("QRY^R02 -> ORF^R04")
query = parse_query(QRY_R02)
check("is_query", is_query(query["message_type"]), True)
check("patient code from QRD-8", query["patient_code"], "12345")
orf = build_response(query, "found", PATIENT, SETTINGS).split(CR)
check("response type", orf[0].split("|")[8], "ORF^R04")
check("MSA echoes control id", orf[1], "MSA|AA|20260723224527.38070003")
check("QRD echoed", orf[2].startswith("QRD|20260723224527.3830+0800"), True)
check("QRF echoed", orf[3].startswith("QRF|MON"), True)
check("PID", orf[4], "PID|||12345||Ahmad Bin Ali^||19800115|M||||||||||MRN00042")
check("PV1 ward/bed", orf[5], "PV1||I|ICU^^01")

miss = build_response(query, "not_found", None, SETTINGS).split(CR)
check("miss is an application error", miss[1], "MSA|AE|20260723224527.38070003|Patient not found")
outage = build_response(query, "error", None, SETTINGS).split(CR)
check("outage is distinguishable", outage[1].endswith("Patient lookup unavailable"), True)

print("\nQRY^A19 -> ADR^A19")
a19 = parse_query(QRY_A19)
check("patient code from QRD-8", a19["patient_code"], "12345")
adr = build_response(a19, "found", PATIENT, SETTINGS).split(CR)
check("response type", adr[0].split("|")[8], "ADR^A19")
check("MSA text", adr[1], "MSA|AA|2|The Patient is Found")
check("PID", adr[3], "PID|||12345||Ahmad Bin Ali^||19800115|M")
check("PV1 ward&bed", adr[4], "PV1||I|^^ICU&01&0&0&0")
adr_miss = build_response(a19, "not_found", None, SETTINGS).split(CR)
check("miss stays an accept in this dialect", adr_miss[1], "MSA|AA|2|The patient is not found!")

# --- observations, coded the way the NC5 codes them -------------------------
ORU = CR.join([
    r"MSH|^~\&||KC575235977G|||20260726120500.1000+0800||ORU^R01|20260726120500.10010042|P|2.6",
    r"PID|||12345||DOE^JOHN||19800115|M||||||||||12345",
    r"PV1||I|^^&24",
    r"OBR||||1^MDC_OBS^MDC|||20260726120500+0800",
    r"OBX||NM|150017^MDC_PRESS_BLD_NONINV_SYS^MDC|1.1.1|128|266016^MDC_DIM_MMHG^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|150018^MDC_PRESS_BLD_NONINV_DIA^MDC|1.1.2|76|266016^MDC_DIM_MMHG^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|150019^MDC_PRESS_BLD_NONINV_MEAN^MDC|1.1.3|93|266016^MDC_DIM_MMHG^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|147842^MDC_ECG_HEART_RATE^MDC|1.2.1|89|264864^MDC_DIM_BEAT_PER_MIN^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|149530^MDC_PULS_OXIM_PULS_RATE^MDC|1.3.1|88|264864^MDC_DIM_BEAT_PER_MIN^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.3.2|97|262688^MDC_DIM_PERCENT^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|150344^MDC_TEMP^MDC|1.4.1|98.6|268160^MDC_DIM_FAHR^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|151562^MDC_RESP_RATE^MDC|1.5.1|18|264928^MDC_DIM_RESP_PER_MIN^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|188740^MDC_LEN_BODY_ACTUAL^MDC|1.10.1|170|263441^MDC_DIM_CENTI_M^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|188736^MDC_MASS_BODY_ACTUAL^MDC|1.10.2|0|263875^MDC_DIM_KILO_G^MDC|||||F|||20260726120500+0800",
    r"OBX||NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.6.1|8388607|262688^MDC_DIM_PERCENT^MDC|||||X|||20260726120500+0800",
]) + CR

print("\nORU^R01 (MDC-coded)")
reading = parse_message(ORU)
check("is_observation", is_observation(reading["message_type"]), True)
check("patient id", reading["patient_id"], "12345")
check("patient name", reading["patient_name"], "JOHN DOE")
check("bed from PV1-3", reading["bed"], "24")
check("systolic", reading["vitals"].get("blood_pressure_systolic"), 128.0)
check("diastolic", reading["vitals"].get("blood_pressure_diastolic"), 76.0)
check("MAP ignored", "blood_pressure_mean" in reading["vitals"], False)
check("heart rate (ECG)", reading["vitals"].get("heart_rate"), 89.0)
check("pulse rate (SpO2)", reading["vitals"].get("pulse_rate"), 88.0)
check("spo2", reading["vitals"].get("spo2"), 97.0)
check("degF converted to degC", reading["vitals"].get("temperature"), 37.0)
check("respiratory rate", reading["vitals"].get("respiratory_rate"), 18.0)
check("height", reading["vitals"].get("height"), 170.0)
check("weight zero is kept for the caller to drop", reading["vitals"].get("weight"), 0.0)
check("NIBP source tagged", reading["sources"].get("blood_pressure_systolic"), "NIBP")
check("observation time", reading["measured_at"].strftime("%Y-%m-%d %H:%M:%S"), "2026-07-26 12:05:00")

# Arterial-line pressure must still be recognised, and tagged as such.
ART = CR.join([
    r"MSH|^~\&||KC575235977G|||20260726121000||ORU^R01|X1|P|2.6",
    r"PID|||12345",
    r"OBX||NM|150021^MDC_PRESS_BLD_ART_SYS^MDC|1.1.1|132|266016^MDC_DIM_MMHG^MDC|||||F|||20260726121000",
    r"OBX||NM|150022^MDC_PRESS_BLD_ART_DIA^MDC|1.1.2|71|266016^MDC_DIM_MMHG^MDC|||||F|||20260726121000",
]) + CR
art = parse_message(ART)
print("\nORU^R01 (arterial line)")
check("systolic", art["vitals"].get("blood_pressure_systolic"), 132.0)
check("source tagged ART", art["sources"].get("blood_pressure_systolic"), "ART")

# A text-coded (LOINC) sender must still work — that is the bench simulator and
# any third-party device pointed at this port.
LOINC = CR.join([
    r"MSH|^~\&|SIM|WARD|NC5_GATEWAY|SMARTWARD|20260726121500||ORU^R01|X2|P|2.4",
    r"PID|||55555||DOE^JANE",
    r"OBX|1|NM|8480-6^NBP Systolic^LN||120|mmHg|||||F",
    r"OBX|2|NM|8462-4^NBP Diastolic^LN||80|mmHg|||||F",
    r"OBX|3|NM|8867-4^Heart Rate^LN||72|bpm|||||F",
    r"OBX|4|NM|59408-5^SpO2^LN||98|%|||||F",
    r"OBX|5|NM|8310-5^Temperature^LN||98.6|degF|||||F",
]) + CR
loinc = parse_message(LOINC)
print("\nORU^R01 (LOINC-coded)")
check("systolic", loinc["vitals"].get("blood_pressure_systolic"), 120.0)
check("heart rate", loinc["vitals"].get("heart_rate"), 72.0)
check("spo2", loinc["vitals"].get("spo2"), 98.0)
check("degF converted", loinc["vitals"].get("temperature"), 37.0)

# ADT^A08 carries demographics only: no vitals may be manufactured from it.
ADT = CR.join([
    r"MSH|^~\&||KC575235977G|||20260723224831.9290+0800||ADT^A08|20260723224831.92900008|P|2.6",
    r"EVN|A08|20260723224831.9292",
    r"PID|||12345||UNKNOWN^PATIENT||19000101|M||||||||||12345",
    r"PV1||I|^^&24|0",
    r"OBX||NM|188740^MDC_LEN_BODY_ACTUAL^MDC|1.10.1.188740|0|263441^MDC_DIM_CENTI_M^MDC|||||F|||20260723224831+0800",
    r"OBX||NM|2010^COMEN_AGE^99COMEN||126||||||F|||20260723224831+0800",
]) + CR
adt = parse_message(ADT)
print("\nADT^A08")
check("not an observation message", is_observation(adt["message_type"]), False)
check("bed", adt["bed"], "24")
check("COMEN_AGE is not mistaken for a vital", "respiratory_rate" in adt["vitals"], False)

# --- captured verbatim from the NC5 at 192.168.0.155 (2026-07-26) -----------
# This is the spot-check message the monitor really sends. It codes the cuff as
# MDC_PRESS_CUFF_* (1503xx), which an earlier build did not recognise - the BP
# was silently dropped. Keep this test honest to the wire.
NC5_SPOT = CR.join([
    r"MSH|^~\&||KC575235977G|||20260726231332.1106+0800||ORU^R01|20260726231332.11090007|P|2.6",
    r"PID|||MRN12123||Tan Chee Keong^|||M||||||||||MRN12123",
    r"PV1||I|^^&24|0|||99999",
    r"OBR||||2^MON_SPOT_DATA^99COMEN|||20260726231331",
    r"OBX||NM|150301^MDC_PRESS_CUFF_SYS^MDC|1.1.9.150301|120|266016^MDC_DIM_MMHG^MDC|90^160||||F|||20260726231331+0800",
    r"OBX||NM|150303^MDC_PRESS_CUFF_MEAN^MDC|1.1.9.150303|90|266016^MDC_DIM_MMHG^MDC|60^110||||F|||20260726231331+0800",
    r"OBX||NM|150302^MDC_PRESS_CUFF_DIA^MDC|1.1.9.150302|80|266016^MDC_DIM_MMHG^MDC|50^90||||F|||20260726231331+0800",
    r"OBX||NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.3.1.150456|98|262688^MDC_DIM_PERCENT^MDC|85^100||||F|||20260726231331+0800",
    r"OBX||NM|149522^MDC_PULS_RATE^MDC|1.0.0.149522|60|264864^MDC_DIM_BEAT_PER_MIN^MDC|50^120||||F|||20260726231331+0800",
    r"OBX||NM|150344^MDC_TEMP^MDC|1.2.1.150344|39.00|268192^MDC_DIM_DEGC^MDC|36.0^39.0||||F|||20260726231331+0800",
    r"OBX||NM|2279^COMEN_PAIN_LEVEL^99COMEN||Other||||||F|||20260726231331+0800",
    r"OBX||NM|2267^COMEN_ACVPU^99COMEN||Alert||||||F|||20260726231331+0800",
    r"OBX||NM|2269^COMEN_TEMP_POS^99COMEN||Ear||||||F|||20260726231331+0800",
    r"OBX||NM|2270^COMEN_NIBP_POS^99COMEN||Other||||||F|||20260726231331+0800",
    r"OBX||NM|2271^COMEN_O2_SRC^99COMEN||Other||||||F|||20260726231331+0800",
    r"OBX||ST|2275^COMEN_HANDLER^99COMEN||||||||F|||20260726231331+0800",
    r"OBX||NM|188740^MDC_LEN_BODY_ACTUAL^MDC|1.10.1.188740|0|263441^MDC_DIM_CENTI_M^MDC|||||F",
    r"OBX||NM|188736^MDC_MASS_BODY_ACTUAL^MDC|1.10.1.188736|0|263875^MDC_DIM_KILO_G^MDC|||||F",
]) + CR

print("\nORU^R01 (real NC5 spot check)")
spot = parse_message(NC5_SPOT)
check("patient id", spot["patient_id"], "MRN12123")
check("patient name", spot["patient_name"], "Tan Chee Keong")
check("cuff systolic", spot["vitals"].get("blood_pressure_systolic"), 120.0)
check("cuff diastolic", spot["vitals"].get("blood_pressure_diastolic"), 80.0)
check("cuff mean is not charted", "blood_pressure_mean" in spot["vitals"], False)
check("cuff mean not read as systolic", spot["vitals"]["blood_pressure_systolic"] != 90.0, True)
check("BP tagged NIBP", spot["sources"].get("blood_pressure_systolic"), "NIBP")
check("pulse rate", spot["vitals"].get("pulse_rate"), 60.0)
check("spo2", spot["vitals"].get("spo2"), 98.0)
check("temperature", spot["vitals"].get("temperature"), 39.0)
check("ACVPU kept as context", spot["context"].get("ACVPU"), "Alert")
check("temp site kept as context", spot["context"].get("Temp site"), "Ear")
check("'Other' context dropped", "Pain" in spot["context"], False)
check("empty operator dropped", "By" in spot["context"], False)
check("COMEN_TEMP_POS not read as a temperature", spot["vitals"]["temperature"], 39.0)

print()
if failures:
    print(f"{len(failures)} check(s) FAILED:")
    for failure in failures:
        print(f"  - {failure}")
    sys.exit(1)
print("all checks passed")
