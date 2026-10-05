"""Log in to C+ once and keep the session in cookies_smartward.txt.

    venv\\Scripts\\python.exe login.py --check              free checks only, no login
    venv\\Scripts\\python.exe login.py                      the one login attempt
    venv\\Scripts\\python.exe login.py --status             what his_state.json says
    venv\\Scripts\\python.exe login.py --clear-login-block  after a failed login, once the account is checked

A live session in the jar is reused, so running this again costs nothing while
the session lasts. A failed login is not retried and blocks later logins until
cleared. C+ lands on its change-password prompt (/Settings?rp=1) after login;
that is left alone and the password is never changed.
"""

import argparse
import json
import sys

from config import HIS_BASE_URL
from core.session import (
    HISSession,
    LoginBlocked,
    LoginTooSoon,
    SessionError,
    clear_login_block,
    session_account,
)

if sys.stdout.encoding != "utf-8":
    sys.stdout.reconfigure(encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description="Log in to C+ once (read-only RPA).")
    group = ap.add_mutually_exclusive_group()
    group.add_argument("--check", action="store_true",
                       help="Run the free checks (login form, facility) and stop before the login.")
    group.add_argument("--status", action="store_true", help="Show the login state and exit.")
    group.add_argument("--clear-login-block", action="store_true",
                       help="Allow logins again after a failed one. Check the account first.")
    args = ap.parse_args()

    session = HISSession()

    if args.status:
        print(json.dumps(session.status(), indent=1))
        return 0

    if args.clear_login_block:
        lifted = clear_login_block()
        print(f"[+] login block cleared: {lifted}" if lifted else "[=] logins were not blocked")
        return 0

    try:
        if session.adopt_existing():
            print(f"[=] The session in {session.status()['cookie_jar']} is live "
                  f"({session_account()}); no login spent.")
            return 0

        if args.check:
            facility_id, facility_name = session.preflight()
            print(f"[+] Login form OK; facility {facility_name} ({facility_id}) is offered to this account.")
            print("[=] Stopped before the login. Run login.py without --check for the one attempt.")
            return 0

        session.login()
    except LoginBlocked as e:
        print(f"[-] {e}")
        return 2
    except LoginTooSoon as e:
        print(f"[-] {e}")
        return 2
    except SessionError as e:
        print(f"[-] {e}")
        return 1

    landing = session.landing
    print(f"[+] Logged in to {session.facility_name} ({session.facility_id}) as {session_account()}.")
    print(f"    landed on {landing['url']} (HTTP {landing['http_code']}, {landing['title']!r})")
    if "/settings" in (landing["url"] or "").lower():
        print("    That is C+'s change-password prompt. It was left alone; the password is unchanged.")
    print(f"    Next: venv\\Scripts\\python.exe bed_management.py   ({HIS_BASE_URL})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
