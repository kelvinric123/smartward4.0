"""
Write a sample TC35 ECG file, in either layout the TC35 produces:

  philips (default) - what the TC35 saves to a USB stick: Philips native XML
                      (<restingecgdata>, http://www3.medical.philips.com, the
                      patient under patient/generalpatientdata, no PDF);
  study             - what it POSTs over the network: UTF-16LE XML with
                      <PatientID> under <Study> and the report PDF base64 in
                      <StudyData> (the shape gateway/ecg/test_sender.py uploads).

Use it to test the USB gateway without the TC35: plug the Pi Zero 2W's USB
port into a laptop, the laptop shows a drive called SMARTWARD, then

    python make_sample_ecg.py E:\\ 55555        (Windows, drive letter E:)
    python make_sample_ecg.py /media/$USER/SMARTWARD 55555

and watch the Pi:  journalctl -u ecg-usb-gateway -f
(Eject the drive in the laptop's tray first if Windows caches the write.)

Usage:  python make_sample_ecg.py <folder> [patient_id] [--format philips|study]
"""

import base64
import os
import sys
from datetime import datetime
from xml.sax.saxutils import escape

# A tiny but real PDF so extraction and %PDF / %%EOF checks pass.
SAMPLE_PDF = (b"%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
              b"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
              b"3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
              b"xref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n180\n%%EOF\n")


def tc35_xml(patient_id: str, pdf: bytes = SAMPLE_PDF, embed_pdf: bool = True) -> bytes:
    """Network-upload ("Study") layout."""
    study = "  <StudyData>" + base64.b64encode(pdf).decode("ascii") + "</StudyData>\n" if embed_pdf else ""
    text = (
        '<?xml version="1.0" encoding="UTF-16"?>\n'
        "<Study>\n"
        "  <StudyID>1</StudyID>\n"
        + study +
        f"  <PatientID>{patient_id}</PatientID>\n"
        "  <PatientLastName>TEST</PatientLastName>\n"
        "  <PatientFirstName>PATIENT</PatientFirstName>\n"
        f"  <StudyTimestamp>{datetime.now():%Y-%m-%dT%H:%M:%S}</StudyTimestamp>\n"
        "</Study>\n"
    )
    return b"\xff\xfe" + text.encode("utf-16-le")  # UTF-16LE with BOM, like the TC35


def philips_xml(patient_id: str, mrn: str = "", last: str = "TEST", first: str = "PATIENT",
                acquired: datetime = None) -> bytes:
    """USB-export layout: Philips native XML, the element paths of a real TC35
    file (values made up; waveforms are placeholder bytes)."""
    now = acquired or datetime.now()
    waveform = base64.b64encode(bytes(range(256)) * 4).decode("ascii")
    text = (
        '<?xml version="1.0" encoding="utf-8"?>\n'
        '<restingecgdata xmlns="http://www3.medical.philips.com">\n'
        "  <documentinfo><documentname>TC35 sample</documentname><documenttype>PhilipsECG</documenttype>"
        "<documentversion>1.04.01</documentversion></documentinfo>\n"
        f'  <dataacquisition date="{now:%Y-%m-%d}" time="{now:%H:%M:%S}">\n'
        "    <machine>TC35</machine>\n"
        "    <acquirer><departmentname>Ward D5</departmentname><institutionname>PHKL</institutionname></acquirer>\n"
        "  </dataacquisition>\n"
        "  <patient>\n"
        "    <generalpatientdata>\n"
        f"      <patientid>{escape(patient_id)}</patientid>\n"
        "      <uniquepatientid></uniquepatientid>\n"
        f"      <MRN>{escape(mrn)}</MRN>\n"
        f"      <name><lastname>{escape(last)}</lastname><firstname>{escape(first)}</firstname>"
        "<middlename></middlename></name>\n"
        "      <age>45</age>\n"
        "      <sex>Male</sex>\n"
        "    </generalpatientdata>\n"
        "  </patient>\n"
        "  <interpretations><interpretation><severity>NORMAL</severity></interpretation></interpretations>\n"
        f"  <waveforms><parsedwaveforms>{waveform}</parsedwaveforms></waveforms>\n"
        "</restingecgdata>\n"
    )
    return text.encode("utf-8")


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--format")]
    fmt = "philips"
    for i, a in enumerate(sys.argv):
        if a.startswith("--format"):
            fmt = a.split("=", 1)[1] if "=" in a else sys.argv[i + 1]
            if "=" not in a and sys.argv[i + 1] in args:
                args.remove(sys.argv[i + 1])
    if not args:
        print(__doc__)
        sys.exit(2)
    folder = args[0]
    patient_id = args[1] if len(args) > 1 else "55555"
    os.makedirs(folder, exist_ok=True)
    data = philips_xml(patient_id) if fmt == "philips" else tc35_xml(patient_id)
    path = os.path.join(folder, f"TC35_{patient_id}_{datetime.now():%Y%m%d_%H%M%S}.xml")
    with open(path, "wb") as fh:
        fh.write(data)
        fh.flush()
        os.fsync(fh.fileno())
    print(f"wrote {path} ({len(data):,} bytes, {fmt} layout, patient ID {patient_id})")


if __name__ == "__main__":
    main()
