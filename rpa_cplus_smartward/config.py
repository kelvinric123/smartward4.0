import os
import re

from dotenv import load_dotenv

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
# Explicit path, so a script started from another directory still finds it.
# Variables already set (e.g. by Docker's env_file) win over the file.
load_dotenv(os.path.join(BASE_DIR, ".env"))

# Where the state that must outlive a restart lives: login state, cookie jar,
# logs and scratch. This folder by default; a volume (/data) in Docker.
DATA_DIR = os.getenv("RPA_DATA_DIR", "").strip() or BASE_DIR

# C+ (Cerebral HIS). A pasted login URL is accepted as the base URL; its path is dropped.
HIS_BASE_URL = re.sub(
    r"(?i)/account/login/?$", "",
    os.getenv("HIS_BASE_URL", "https://cerebral.ihhmy.com").strip().rstrip("/"),
).rstrip("/")
LOGIN_URL = f"{HIS_BASE_URL}/Account/Login"
FACILITY_URL = f"{HIS_BASE_URL}/account/GetUserFacility"
BED_MANAGEMENT_URL = f"{HIS_BASE_URL}/pm/Reservation/BedManagement"
WARD_LIST_URL = f"{HIS_BASE_URL}/PM/Global/RetrieveDropDownByJson"

# Credentials come from .env (gitignored). A stray space silently breaks the
# facility lookup and wastes the login attempt, so both values are stripped.
HIS_USERNAME = os.getenv("HIS_USERNAME", "").strip()
HIS_PASSWORD = os.getenv("HIS_PASSWORD", "").strip()

# The login facility, picked by id. rpa.qmed has PHAK = 26 and PHKL = 33, and
# PHAK is listed first, so the first option is never taken. HIS_FACILITY, if
# set, must match the name of the option with that id.
HIS_FACILITY = os.getenv("HIS_FACILITY", "").strip()
HIS_FACILITY_ID = os.getenv("HIS_FACILITY_ID", "").strip()

# One failed login blocks every later one, in this run and the next, until it
# is cleared by hand (login.py --clear-login-block). The block and the time of
# the last login live in this file so a restart cannot forget them.
HIS_STATE_FILE = os.path.join(DATA_DIR, os.getenv("HIS_STATE_FILE", "").strip() or "his_state.json")

# Never two logins closer together than this.
HIS_MIN_LOGIN_INTERVAL_MINUTES = float(os.getenv("HIS_MIN_LOGIN_INTERVAL_MINUTES", "").strip() or "15")

# Own jar, never shared with the other C+ RPAs (rpa_cplus, SmartOT), so none
# of them can clear or overwrite another's session.
COOKIE_JAR = os.path.join(DATA_DIR, os.getenv("HIS_COOKIE_JAR", "").strip() or "cookies_smartward.txt")

# --- SmartWard (where Bed Management is pushed) ------------------------------

# Base URL of SmartWard, e.g. http://10.14.16.100:18080, and the API token of
# one of its Integration Users (Users > Edit > Generate token).
SMARTWARD_URL = os.getenv("SMARTWARD_URL", "").strip().rstrip("/")
SMARTWARD_TOKEN = os.getenv("SMARTWARD_TOKEN", "").strip()

# Bed Management unit groups read with "All Locations": 2 = Ward (IP), 6 = Day Care Unit.
SYNC_UNIT_GROUPS = [g.strip() for g in (os.getenv("SYNC_UNIT_GROUPS", "").strip() or "2").split(",") if g.strip()]
SYNC_INTERVAL_MINUTES = float(os.getenv("SYNC_INTERVAL_MINUTES", "").strip() or "5")

# ER Management too (unit group 3): the ED's zones, which SmartWard tags as
# Emergency wards for Command Center V2 (ED). On unless set to false/0/no.
SYNC_ER = (os.getenv("SYNC_ER", "").strip().lower() or "true") not in ("0", "false", "no", "off")

# Admission dates: the Inpatient Daily Census of these unit groups gives the
# Entry Date. A patient in a ward bed can be listed under Day Care (6), so both
# are read. Whoever the census misses gets the Request Date of a reservation
# request with a plan date in the last REQUEST_LOOKBACK_DAYS days.
CENSUS_UNIT_GROUPS = [g.strip() for g in (os.getenv("CENSUS_UNIT_GROUPS", "").strip() or "2,6").split(",") if g.strip()]
REQUEST_LOOKBACK_DAYS = int(os.getenv("REQUEST_LOOKBACK_DAYS", "").strip() or "7")

# Isolation: the Isolation box on each ward patient's admission card, one C+
# page per patient (scraper/admission_card.py). A card is read again after
# ISOLATION_REFRESH_MINUTES, at most ISOLATION_MAX_READS per sync, never-read
# patients first. On unless SYNC_ISOLATION is set to false/0/no.
SYNC_ISOLATION = (os.getenv("SYNC_ISOLATION", "").strip().lower() or "true") not in ("0", "false", "no", "off")
ISOLATION_REFRESH_MINUTES = float(os.getenv("ISOLATION_REFRESH_MINUTES", "").strip() or "30")
ISOLATION_MAX_READS = int(os.getenv("ISOLATION_MAX_READS", "").strip() or "60")
# What was read, by MRN and RN. It is patient data: gitignored, and on the /data volume in Docker.
ISOLATION_CACHE_FILE = os.path.join(DATA_DIR, os.getenv("ISOLATION_CACHE_FILE", "").strip() or "isolation_cache.json")

# C+ times are Malaysia local time (no daylight saving); sent to SmartWard with this offset.
HIS_UTC_OFFSET_HOURS = float(os.getenv("HIS_UTC_OFFSET_HOURS", "").strip() or "8")

LOG_FILE = os.path.join(DATA_DIR, "logs", "sync.log")

# Raw pages saved for study. Gitignored: they carry patient data.
SCRATCH_DIR = os.path.join(DATA_DIR, "scratch")

DEFAULT_USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
