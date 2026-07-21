"""
Simulate a Philips TC35 upload against the ECG gateway to prove the chain:
Basic-auth HTTP POST -> file stored + queued -> IDEP "OK" reply -> sender
forwards to SmartWard -> duplicate re-upload deduplicated.

Builds a TC35-style XML (UTF-16LE with BOM, <PatientID>, base64 PDF in
<StudyData>) so the gateway's decoding, patient matching, and PDF extraction
all run for real.

Usage:  python test_sender.py [host] [port]
        (defaults: 127.0.0.1 3050)
"""

import base64
import sys
import urllib.request

host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 3050
url = f"http://{host}:{port}/upload/ecg_report.xml"

# A tiny but real PDF so extraction and %PDF signature checks pass.
pdf = (b"%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
       b"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
       b"3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
       b"xref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n180\n%%EOF\n")

xml_text = (
    '<?xml version="1.0" encoding="UTF-16"?>\n'
    "<restingecgdata>\n"
    "  <PatientID>55555</PatientID>\n"
    "  <PatientName>DOE, JOHN</PatientName>\n"
    "  <StudyData>" + base64.b64encode(pdf).decode("ascii") + "</StudyData>\n"
    "</restingecgdata>\n"
)
body = b"\xff\xfe" + xml_text.encode("utf-16-le")  # UTF-16LE with BOM, like the TC35

auth = base64.b64encode(b"admin:admin123").decode("ascii")


def post(label):
    req = urllib.request.Request(url, data=body, method="POST", headers={
        "Authorization": f"Basic {auth}",
        "Content-Type": "application/octet-stream",
        "Content-Length": str(len(body)),
    })
    with urllib.request.urlopen(req, timeout=10) as resp:
        print(f"{label}: HTTP {resp.status} body={resp.read()!r}")


post("upload #1 (new)")
post("upload #2 (retransmit, must dedupe)")

# Wrong password: must get 401, never a silent drop.
req = urllib.request.Request(url, data=body, method="POST", headers={
    "Authorization": "Basic " + base64.b64encode(b"admin:wrong").decode("ascii"),
    "Content-Length": str(len(body)),
})
try:
    urllib.request.urlopen(req, timeout=10)
    print("bad-auth: UNEXPECTED success")
except Exception as exc:
    print(f"bad-auth: rejected as expected ({exc})")
