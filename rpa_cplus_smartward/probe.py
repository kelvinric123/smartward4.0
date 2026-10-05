"""GET one C+ page or asset with the saved session and save it under scratch\\.

    venv\\Scripts\\python.exe probe.py /PM//assets/global/scripts/bed-management.js
    venv\\Scripts\\python.exe probe.py "/PM/Global/GetRoomsByStyle?location=190&..." --ajax --out rooms_190.html

For exploring the screens. GET only, and it never logs in.
"""

import argparse
import os
import re
import sys

from config import HIS_BASE_URL, SCRATCH_DIR
from core.session import HISSession, SessionError

if sys.stdout.encoding != "utf-8":
    sys.stdout.reconfigure(encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description="GET a C+ path with the saved session (read-only).")
    ap.add_argument("path", help="Path on C+, e.g. /pm/Reservation/BedManagement")
    ap.add_argument("--ajax", action="store_true", help="Send X-Requested-With: XMLHttpRequest")
    ap.add_argument("--out", help="File name under scratch\\ (default: from the path)")
    args = ap.parse_args()

    url = args.path if args.path.startswith("http") else HIS_BASE_URL + "/" + args.path.lstrip("/")
    if not url.startswith(HIS_BASE_URL + "/"):
        print(f"[-] Only {HIS_BASE_URL} is probed.")
        return 1

    headers = {"X-Requested-With": "XMLHttpRequest"} if args.ajax else None
    try:
        resp = HISSession().get(url, headers=headers)
    except SessionError as e:
        print(f"[-] {e}")
        return 2

    name = args.out or re.sub(r"[^A-Za-z0-9._-]+", "_", url.split("?")[0].rsplit("/", 1)[-1]) or "page"
    os.makedirs(SCRATCH_DIR, exist_ok=True)
    out = os.path.join(SCRATCH_DIR, name)
    with open(out, "w", encoding="utf-8") as fh:
        fh.write(resp["stdout"] or "")
    print(f"[+] HTTP {resp['http_code']} {resp['url']}\n    {len(resp['stdout'] or ''):,} bytes -> {os.path.relpath(out)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
