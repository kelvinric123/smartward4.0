"""The C+ session for rpa_cplus_smartward.

Same rules as SmartOT's RPA (C:\\laragon\\www\\SmartOT\\rpa_cplus_smartOT), which
took them from the production RPA (rpa_cplus):

- One login attempt. A failed login is never retried: it blocks every later
  login, in this run and the next (his_state.json), until someone checks the
  account and runs `login.py --clear-login-block`. rpa.qmed can be locked.
- Only login.py logs in. Every other script reuses the cookie jar and stops
  with NoSession when the session is gone, instead of logging in.
- The free checks (the login form, the facility list) run before the POST,
  so a doomed login is never sent.
- Two logins are never closer together than HIS_MIN_LOGIN_INTERVAL_MINUTES.
- A jar is only reused for the account and facility it was made for.
- After login C+ lands on /Settings?rp=1, a change-password prompt. It is
  ignored. Nothing here posts to /settings/ChangePassword or
  /settings/ChangeUsername. After the login every call is a GET, except the
  read-only reports in REPORTS, which C+ only serves by POST; post_report()
  refuses any other path.
"""

import json
import os
import time
from datetime import datetime

from bs4 import BeautifulSoup

from config import (
    COOKIE_JAR,
    FACILITY_URL,
    HIS_BASE_URL,
    HIS_FACILITY,
    HIS_FACILITY_ID,
    HIS_MIN_LOGIN_INTERVAL_MINUTES,
    HIS_PASSWORD,
    HIS_STATE_FILE,
    HIS_USERNAME,
    LOGIN_URL,
)
from core.curl_client import CurlClient

# Reports C+ serves by POST although they only read. Nothing else is POSTed
# after the login.
REPORTS = {
    # Inpatient Daily Census (Bed Management -> Reports): who is admitted, since when.
    "/PM/Patient/GetAdmissionVipDailyCensus",
}


class SessionError(Exception):
    """The C+ session could not be established or used."""


class LoginBlocked(SessionError):
    """A failed login blocks all logins until someone clears it."""


class LoginTooSoon(SessionError):
    """Another login would come too soon after the last one."""


class NoSession(SessionError):
    """There is no live session in the jar, and only login.py may log in."""


def looks_like_login_page(html):
    """True when C+ answered with the login form (no session, or it expired)."""
    if not html:
        return False
    low = html.lower()
    return 'action="/account/login"' in low and "password" in low


def page_title(html):
    title = BeautifulSoup(html or "", "html.parser").find("title")
    return title.get_text(strip=True) if title else ""


def is_runtime_error(html):
    title = page_title(html)
    return "Runtime Error" in title or "Çalışma Zamanı Hatası" in title


def login_error_message(html):
    """The message C+ shows on a rejected login, if the page carries one."""
    soup = BeautifulSoup(html or "", "html.parser")
    for el in soup.select(".validation-summary-errors, .field-validation-error, .alert, .error, .text-danger"):
        text = el.get_text(" ", strip=True)
        if text:
            return text[:200]
    return ""


# -- login state kept on disk ------------------------------------------------

def load_state():
    try:
        with open(HIS_STATE_FILE, encoding="utf-8") as fh:
            state = json.load(fh)
        return state if isinstance(state, dict) else {}
    except (OSError, ValueError):
        return {}


def save_state(state):
    tmp = HIS_STATE_FILE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(state, fh, indent=1)
    os.replace(tmp, HIS_STATE_FILE)


def clear_login_block():
    """Allow logins again after a failure. Returns the block that was lifted."""
    state = load_state()
    blocked = state.pop("blocked", None)
    save_state(state)
    return blocked


def session_account():
    """Who and where a session is for, e.g. "rpa.qmed@26". One account has
    several facilities, and a session is only good for the one it logged in to."""
    return f"{HIS_USERNAME}@{HIS_FACILITY_ID}"


class HISSession:
    """One C+ session in this RPA's own cookie jar."""

    def __init__(self, cookie_jar=COOKIE_JAR):
        os.makedirs(os.path.dirname(os.path.abspath(cookie_jar)), exist_ok=True)
        self.client = CurlClient(cookie_jar=cookie_jar)
        self.logged_in = False
        self.facility_id = None
        self.facility_name = None
        # Where the login POST landed: {"url", "http_code", "title"}.
        self.landing = None

    # -- free pre-flight checks (no login attempt spent) --------------------

    def check_login_form(self):
        """GET the login page (which also seeds the cookies) and confirm the form
        still has the three fields this login sends."""
        resp = self.client.get(LOGIN_URL)
        if not resp["success"] or resp["http_code"] != "200":
            raise SessionError(
                f"Login page unreachable (HTTP {resp['http_code']}): {resp['stderr'].strip()}")

        page = resp["stdout"]
        form = BeautifulSoup(page, "html.parser").find(
            "form", action=lambda a: a and a.lower().rstrip("/") == "/account/login")
        if form is None:
            raise SessionError("The login page has no /account/login form any more; not logging in.")

        names = {el.get("name") for el in form.find_all(["input", "select"]) if el.get("name")}
        missing = {"username", "password", "facility"} - names
        if missing:
            raise SessionError(
                f"The login form no longer has {sorted(missing)} (it has {sorted(names)}); not logging in.")
        if "captcha" in page.lower():
            raise SessionError("The login page now shows a captcha; not logging in.")

    def resolve_facility(self):
        """(id, name) of the facility HIS_FACILITY_ID names. A GET, free.

        Picked by id, never the first option (PHAK is listed first). If
        HIS_FACILITY is set too, the chosen option's name has to contain it.
        """
        if not HIS_FACILITY_ID:
            raise SessionError("HIS_FACILITY_ID is not set in .env (PHAK = 26, PHKL = 33); not logging in.")

        resp = self.client.get(
            FACILITY_URL,
            params={"username": HIS_USERNAME},
            headers={"Content-Type": "application/json; charset=UTF-8"},
        )
        try:
            html = json.loads(resp["stdout"] or "")["data"]["ResultData"]["FACILITY"] or ""
        except (ValueError, KeyError, TypeError):
            raise SessionError(
                f"The facility lookup did not return the usual JSON (HTTP {resp['http_code']}); not logging in.")

        options = [
            (opt.get("value"), opt.get_text(strip=True))
            for opt in BeautifulSoup(html, "html.parser").find_all("option")
            if opt.get("value") not in (None, "", "0")
        ]
        for value, name in options:
            if value == HIS_FACILITY_ID:
                if HIS_FACILITY and HIS_FACILITY.upper() not in name.upper():
                    raise SessionError(
                        f"Facility id {HIS_FACILITY_ID} is {name!r}, not {HIS_FACILITY!r}; "
                        f"fix HIS_FACILITY / HIS_FACILITY_ID in .env. Not logging in.")
                return value, name
        raise SessionError(
            f"Facility id {HIS_FACILITY_ID} is not offered to {HIS_USERNAME} "
            f"(offered: {options or 'none'}); not logging in.")

    def preflight(self):
        """Every check that costs no login. Returns the (id, name) to log in to."""
        if not HIS_USERNAME:
            raise SessionError("HIS_USERNAME is not set in .env")
        self.check_login_form()
        return self.resolve_facility()

    # -- authentication ----------------------------------------------------

    def login(self):
        """Authenticate once. A failure blocks all later logins."""
        state = load_state()
        if state.get("blocked"):
            blocked = state["blocked"]
            raise LoginBlocked(
                f"Logins are blocked since {blocked.get('at')}: {blocked.get('reason')}. "
                f"Check the account, then run: venv\\Scripts\\python.exe login.py --clear-login-block")

        waited = (time.time() - state.get("last_login_at", 0)) / 60
        if waited < HIS_MIN_LOGIN_INTERVAL_MINUTES:
            raise LoginTooSoon(
                f"The last login was {waited:.0f} min ago; the next one waits until "
                f"{HIS_MIN_LOGIN_INTERVAL_MINUTES:g} min have passed.")

        if not HIS_PASSWORD:
            raise SessionError("HIS_PASSWORD is not set in .env")

        self.facility_id, self.facility_name = self.preflight()

        # Recorded before the POST, so even a crash half-way counts.
        state["last_login_at"] = time.time()
        save_state(state)

        resp = self.client.post(
            LOGIN_URL,
            data={"username": HIS_USERNAME, "password": HIS_PASSWORD, "facility": self.facility_id},
        )
        body = resp["stdout"]
        self.landing = {"url": resp["url"], "http_code": resp["http_code"], "title": page_title(body)}

        failure = None
        if not resp["success"]:
            failure = f"the login request failed ({resp['stderr'].strip() or 'curl error'})"
        elif looks_like_login_page(body):
            message = login_error_message(body)
            failure = "C+ sent the login form back" + (f": {message}" if message else "")
        elif is_runtime_error(body):
            failure = "C+ returned a runtime error during login"

        if failure:
            state["blocked"] = {"at": datetime.now().isoformat(timespec="seconds"), "reason": failure}
            save_state(state)
            raise SessionError(f"Login failed: {failure}. Not retrying; logins are now blocked.")

        state["session_account"] = session_account()
        save_state(state)
        self.logged_in = True
        return True

    def adopt_existing(self):
        """Reuse the jar left by an earlier run instead of spending a login. A GET, free.

        Only a jar made for this account and facility counts: after a switch
        (say PHAK to PHKL) the old session would quietly read the old facility.
        """
        if not os.path.exists(self.client.cookie_jar):
            return False
        if load_state().get("session_account") != session_account():
            self.client.clear_cookies()
            return False
        resp = self.client.get(f"{HIS_BASE_URL}/")
        if resp["success"] and resp["http_code"] == "200" and resp["stdout"] \
                and not looks_like_login_page(resp["stdout"]):
            self.logged_in = True
            return True
        return False

    # -- requests ------------------------------------------------------------

    def get(self, url, headers=None, params=None):
        """GET with the live session. Never logs in: a dropped session raises NoSession."""
        self._require_session()
        return self._checked(self.client.get(url, headers=headers, params=params))

    def post_report(self, path, data):
        """POST one of the read-only REPORTS as JSON. Never logs in, like get()."""
        if path not in REPORTS:
            raise SessionError(f"{path} is not a known read-only report; not posting to it.")
        self._require_session()
        return self._checked(self.client.post(
            HIS_BASE_URL + path, data=data, headers={"X-Requested-With": "XMLHttpRequest"}, is_json=True))

    def _require_session(self):
        if not self.logged_in and not self.adopt_existing():
            raise NoSession(
                "No live C+ session in the cookie jar. Logging in is the user's call: "
                "venv\\Scripts\\python.exe login.py (one attempt).")

    def _checked(self, resp):
        if resp["success"] and looks_like_login_page(resp["stdout"]):
            self.logged_in = False
            raise NoSession(
                "C+ has dropped the session. Logging in again is the user's call: "
                "venv\\Scripts\\python.exe login.py (one attempt).")
        return resp

    def status(self):
        state = load_state()
        last = state.get("last_login_at")
        return {
            "account": session_account(),
            "session_account": state.get("session_account"),
            "last_login_at": datetime.fromtimestamp(last).isoformat(timespec="seconds") if last else None,
            "blocked": state.get("blocked"),
            "cookie_jar": os.path.basename(self.client.cookie_jar),
            "cookie_jar_exists": os.path.exists(self.client.cookie_jar),
        }
