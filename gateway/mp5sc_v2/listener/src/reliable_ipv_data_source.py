import os
import struct
import sys
import threading
import time


CURRENT_DIR = os.path.dirname(__file__)
LEGACY_SRC = os.path.abspath(
    os.path.join(CURRENT_DIR, "..", "..", "..", "mp5sc_listener", "listener", "src")
)

if LEGACY_SRC not in sys.path:
    sys.path.insert(0, LEGACY_SRC)

from ipv_data_source import ipv_data_source as LegacyIpvDataSource  # noqa: E402


# IntelliVue attribute ids that carry numeric observations.
ATTR_NU_VAL_OBS = 2384        # NOM_ATTR_NU_VAL_OBS       - one observation
ATTR_NU_CMPD_VAL_OBS = 2379   # NOM_ATTR_NU_CMPD_VAL_OBS  - list of observations
# NuObsValue layout: physio-id(2) | measurement-state(2) | unit-code(2) | float(4)
NU_OBS_LEN = 10

# IEEE 11073 FLOAT-Type reserved mantissas: NaN, NRes, +INF, -INF. The monitor
# sends these whenever a numeric is not measurable, at any exponent, so testing
# the mantissa is safer than the legacy "== 8388607" check.
SPECIAL_MANTISSAS = {0x7FFFFF, 0x800000, 0x7FFFFE, 0x800002}

# Physio ids are 16-bit on the wire; anything larger can never match.
MAX_PHYSIO_ID = 0xFFFF

# Receive buffer the legacy client uses; a datagram this size was probably cut.
RECV_BUFSIZE = 8192

# Attributes the legacy parser understands. Anything else that turns up in a
# poll result is carrying data nobody decodes.
DECODED_ATTRS = {
    2337, 2343, 2351, 2379, 2384, 2390, 2392, 2394, 2396, 2397,
    2401, 2447, 2448, 2520, 2524, 2527,
}

# Temperature reaches the monitor either from a probe or as a value keyed in on
# the monitor itself, and the two arrive under different physio ids. Same for
# respiratory rate. Both id sets are polled; the most recently changed one wins.
# 19272=MDC_TEMP 19296=BODY 19298=CORE 19328=SKIN 19330=RECT 19360=TYMP
# 19394=ESOPH 61639=Philips private
DEFAULT_TEMP_IDS = "19272,19296,19298,19328,19330,19360,19394,61639"
# 20480=impedance 20482=MDC_RESP 20490=MDC_RESP_RATE 20498=AWAY_RESP_RATE
# 20514=CO2_RESP_RATE 53250,63528=Philips private
DEFAULT_RR_IDS = "20480,20482,20490,20498,20514,53250,63528"

TEMP_MIN, TEMP_MAX = 20.0, 50.0
RR_MIN, RR_MAX = 0.0, 100.0

# Which source wins when a probe and a keyed-in value are both live:
#   latest - whichever changed most recently (default)
#   manual - a keyed-in value always beats a probe while it is on screen
#   probe  - the opposite
PREFERENCES = ("latest", "manual", "probe")


def _env_ids(name, default):
    ids = []
    for chunk in os.getenv(name, default).split(","):
        chunk = chunk.strip()
        if not chunk:
            continue
        try:
            value = int(chunk, 0)
        except ValueError:
            continue
        if 0 < value <= MAX_PHYSIO_ID:
            ids.append(value)
    return ids


def _env_int(name, default):
    try:
        return int(os.getenv(name, str(default)), 0)
    except ValueError:
        return default


def _env_bool(name, default="false"):
    return os.getenv(name, default).strip().lower() == "true"


class ReliableIpvDataSource(LegacyIpvDataSource):
    """
    Wraps the legacy Philips parser and:
      - fixes the watchdog lifecycle so v2 does not start duplicate watchdog
        threads or compare method objects;
      - records every numeric observation (physio id, measurement state, unit,
        value) instead of letting the last matching id win, so a probe reading
        and a value keyed in on the monitor cannot overwrite each other;
      - resolves Temp / Resp_rate from those samples with a TTL, so a spot value
        that the monitor has stopped reporting stops riding along on later BPs.
    """

    def __init__(self, ip, logger=None):
        super().__init__(ip)
        self._watchdog_lock = threading.Lock()
        self._watchdog_started = False
        self._log = logger or (lambda message, level="INFO": print(f"[{level}] {message}"))

        self._samples_lock = threading.Lock()
        self._samples = {}
        self._poll_cycle = 0
        self._temp_ids = _env_ids("TEMP_PHYSIO_IDS", DEFAULT_TEMP_IDS)
        self._rr_ids = _env_ids("RR_PHYSIO_IDS", DEFAULT_RR_IDS)
        self._temp_manual_ids = _env_ids("TEMP_MANUAL_PHYSIO_IDS", "")
        self._rr_manual_ids = _env_ids("RR_MANUAL_PHYSIO_IDS", "")
        self._preference = os.getenv("PHYSIO_SOURCE_PREFERENCE", "latest").strip().lower()
        if self._preference not in PREFERENCES:
            self._preference = "latest"
        # A source that stops appearing in the poll (value cleared or deleted on
        # the monitor) is dropped after this many poll cycles. Small grace so a
        # demographics poll or a lost UDP packet does not look like a clear.
        self._missing_cycles = _env_int("PHYSIO_MISSING_CYCLES", 3)
        self._temp_ttl = _env_int("TEMP_TTL_SECONDS", 600)
        self._rr_ttl = _env_int("RR_TTL_SECONDS", 600)
        # Measurement-state bits that make a sample unusable. 0x8000 = invalid.
        # Widen this only after confirming the bits your monitor actually sets
        # (PHYSIO_DISCOVERY logs them).
        self._reject_mask = _env_int("PHYSIO_STATE_REJECT_MASK", 0x8000)
        self._discovery = _env_bool("PHYSIO_DISCOVERY")

        # Which id currently supplies each metric, for logging and diagnostics.
        self.last_temp_id = None
        self.last_rr_id = None

        # Census: what the monitor actually sends, and what we lose on the way.
        # Answers "are we seeing every numeric, or only the ones we listen for?"
        self._attr_census = {}
        self._stats = {
            "poll_cycles": 0,
            "parses": 0,
            "observations": 0,
            "length_mismatch": 0,   # whole poll result discarded by the parser
            "maybe_truncated": 0,   # datagram filled the 8 KB receive buffer
            "linked_gaps": 0,       # linked package missing from a poll result
        }

    # ------------------------------------------------------------------
    # Connection lifecycle
    # ------------------------------------------------------------------

    def start_client(self):
        self.run_loop = True
        self.process = threading.Thread(target=self.do_events, daemon=True)
        self.process.start()
        self.start_watchdog()

    def start_watchdog(self):
        with self._watchdog_lock:
            if self._watchdog_started and hasattr(self, "watchdog_thread") and self.watchdog_thread.is_alive():
                return
            self.run_con_watchdog = True
            self.watchdog_thread = threading.Thread(target=self.con_watchdog, daemon=True)
            self.watchdog_thread.start()
            self._watchdog_started = True

    def con_watchdog(self):
        while self.run_con_watchdog:
            if not self.check_client_is_working_correctly():
                time.sleep(5)
                if not self.check_client_is_working_correctly():
                    self.run_loop = False
                    time.sleep(5)
                    if not hasattr(self, "process") or not self.process.is_alive():
                        self.process = threading.Thread(target=self.do_events, daemon=True)
                        self.run_loop = True
                        self.process.start()
            time.sleep(5)

    def check_client_is_working_correctly(self):
        return self.is_active == self.run_loop

    def halt_client(self):
        self.run_con_watchdog = False
        self.run_loop = False

    # ------------------------------------------------------------------
    # Observation capture
    # ------------------------------------------------------------------

    def single_poll_request(self, sid, n, nr, p_count):
        """Sent exactly once per poll cycle - the marker used to tell a value
        that is still on screen from one the monitor has stopped reporting."""
        self._poll_cycle += 1
        self._stats["poll_cycles"] += 1
        return super().single_poll_request(sid, n, nr, p_count)

    def poll_single_parse(self, b):
        """Count poll results the legacy parser throws away whole: it bails out
        when the declared list length does not match the datagram, which is what
        a truncated 8 KB receive looks like - and it does so silently."""
        self._stats["parses"] += 1
        if len(b) >= RECV_BUFSIZE:
            self._stats["maybe_truncated"] += 1
            self._log(
                f"poll result filled the {RECV_BUFSIZE} byte receive buffer - "
                f"metrics after the cut-off are lost this cycle",
                "WARNING",
            )
        try:
            declared = struct.unpack("!H", bytes(b[48:50]))[0]
            if declared != len(b[50:]):
                self._stats["length_mismatch"] += 1
                self._log(
                    f"poll result discarded: declared {declared} bytes, got {len(b[50:])} "
                    f"- every numeric in this poll is lost",
                    "WARNING",
                )
        except Exception:
            pass
        return super().poll_single_parse(b)

    def linked_data_parse(self, b):
        """A poll result split across packages is collected on a socket timeout;
        a lost package is invisible to the legacy code. Flag gaps so a missing
        Temp/RR can be traced to lost packets rather than a wrong physio id."""
        try:
            numbers = sorted({pkg[0] for pkg in b if pkg[2] == 5})
            if len(numbers) >= 2 and numbers != list(range(numbers[0], numbers[-1] + 1)):
                missing = sorted(set(range(numbers[0], numbers[-1] + 1)) - set(numbers))
                self._stats["linked_gaps"] += 1
                self._log(
                    f"incomplete poll result: linked package(s) {missing} missing "
                    f"of {numbers[0]}..{numbers[-1]} - metrics in them are lost this cycle",
                    "WARNING",
                )
        except Exception:
            pass
        return super().linked_data_parse(b)

    def check_id(self, objid, b, handle_id=0):
        """Record numeric observations, then hand off to the legacy parser so
        NBP, SpO2, patient demographics and timestamps behave exactly as before."""
        entry = self._attr_census.get(objid)
        if entry is None:
            self._attr_census[objid] = {"count": 1, "decoded": objid in DECODED_ATTRS}
        else:
            entry["count"] += 1
        try:
            if objid == ATTR_NU_VAL_OBS:
                self._record_observation(b, handle_id)
            elif objid == ATTR_NU_CMPD_VAL_OBS and len(b) >= 4:
                count = struct.unpack("!H", b[:2])[0]
                data = b[4:]
                for _ in range(count):
                    if len(data) < NU_OBS_LEN:
                        break
                    self._record_observation(data[:NU_OBS_LEN], handle_id)
                    data = data[NU_OBS_LEN:]
        except Exception as exc:
            if self.debug_info or self._discovery:
                self._log(f"physio capture error on attr {objid}: {exc}", "DEBUG")

        return super().check_id(objid, b, handle_id)

    def _record_observation(self, raw, handle_id):
        if len(raw) < NU_OBS_LEN:
            return
        physio_id, state, unit = struct.unpack("!HHH", raw[:6])
        value = self.decode_float(raw[6:10])
        mantissa = struct.unpack("!I", b"\x00" + bytes(raw[7:10]))[0]
        special = mantissa in SPECIAL_MANTISSAS
        now = time.time()

        self._stats["observations"] += 1

        with self._samples_lock:
            prev = self._samples.get(physio_id)
            changed = prev is None or prev["value"] != value or prev["state"] != state
            self._samples[physio_id] = {
                "physio_id": physio_id,
                "state": state,
                "unit": unit,
                "value": value,
                "special": special,
                "handle": handle_id,
                "at": now,
                "changed_at": now if changed else prev["changed_at"],
                "cycle": self._poll_cycle,
            }

        if self._discovery and changed:
            self._log(
                f"physio id={physio_id} (0x{physio_id:04X}) value={value} "
                f"state=0x{state:04X} unit={unit} handle={handle_id}"
                f"{' SPECIAL/no-value' if special else ''}",
                "DEBUG",
            )

    def _unusable_reason(self, sample, low, high, ttl, now):
        """Why a sample cannot be reported. An unusable sample never wins and
        never overwrites another source - a blank or cleared entry on the
        monitor must not wipe out a good reading from the other source."""
        if sample["special"]:
            return "no value (NaN/NRes/INF)"
        if sample["state"] & self._reject_mask:
            return f"state 0x{sample['state']:04X} rejected"
        if not (low < sample["value"] < high):
            return f"value {sample['value']} out of range"
        if self._missing_cycles >= 0 and (self._poll_cycle - sample["cycle"]) > self._missing_cycles:
            return "no longer reported (cleared on monitor)"
        if ttl > 0 and (now - sample["at"]) > ttl:
            return "aged out"
        return None

    def _resolve(self, physio_ids, manual_ids, low, high, ttl):
        """Pick a winner among the candidate ids. Only usable samples compete,
        so probe and keyed-in values coexist: clearing one falls back to the
        other instead of blanking the metric."""
        now = time.time()
        with self._samples_lock:
            candidates = [
                sample
                for sample in (self._samples.get(pid) for pid in physio_ids)
                if sample is not None and self._unusable_reason(sample, low, high, ttl, now) is None
            ]

        if not candidates:
            return None

        # Optional tie-break when both sources are live at the same time.
        if self._preference == "manual":
            preferred = [c for c in candidates if c["physio_id"] in manual_ids]
        elif self._preference == "probe":
            preferred = [c for c in candidates if c["physio_id"] not in manual_ids]
        else:
            preferred = []
        if preferred:
            candidates = preferred

        # Otherwise the most recently changed value wins; an unchanged repeat of
        # an older reading does not displace a newer one from the other source.
        return max(candidates, key=lambda s: (s["changed_at"], s["at"]))

    def _log_dropped(self, label, previous_id, low, high, ttl):
        with self._samples_lock:
            sample = self._samples.get(previous_id)
        reason = "never seen"
        if sample is not None:
            reason = self._unusable_reason(sample, low, high, ttl, time.time()) or "superseded"
        self._log(
            f"{label}: dropped source id={previous_id} (0x{previous_id:04X}) - {reason}; "
            f"no other usable source",
            "WARNING",
        )

    def _apply(self, vitals, label, sample, attr, low, high, ttl):
        for entry in vitals:
            if entry[0] == label:
                entry[1] = sample["value"] if sample else 0
                break
        physio_id = sample["physio_id"] if sample else None
        previous_id = getattr(self, attr)
        if physio_id == previous_id:
            return
        setattr(self, attr, physio_id)
        if physio_id is None:
            if previous_id is not None:
                self._log_dropped(label, previous_id, low, high, ttl)
        else:
            origin = "keyed-in" if physio_id in self._manual_ids_for(attr) else "device"
            self._log(
                f"{label}: source physio id={physio_id} (0x{physio_id:04X}) [{origin}] "
                f"state=0x{sample['state']:04X} value={sample['value']}",
                "INFO",
            )

    def _manual_ids_for(self, attr):
        return self._temp_manual_ids if attr == "last_temp_id" else self._rr_manual_ids

    def get_vital_signs(self):
        vitals = super().get_vital_signs()
        temp = self._resolve(self._temp_ids, self._temp_manual_ids, TEMP_MIN, TEMP_MAX, self._temp_ttl)
        rr = self._resolve(self._rr_ids, self._rr_manual_ids, RR_MIN, RR_MAX, self._rr_ttl)
        self._apply(vitals, "Temp", temp, "last_temp_id", TEMP_MIN, TEMP_MAX, self._temp_ttl)
        self._apply(vitals, "Resp_rate", rr, "last_rr_id", RR_MIN, RR_MAX, self._rr_ttl)
        return vitals

    def census_report(self):
        """Everything the monitor has sent since connect, split into what we act
        on and what we ignore - so an unlisted Temp/RR id shows up as a numeric
        we are receiving but not selecting."""
        lines = []
        s = self._stats
        lines.append(
            f"physio census: {s['poll_cycles']} poll cycles, {s['parses']} parsed results, "
            f"{s['observations']} observations"
        )
        losses = []
        if s["length_mismatch"]:
            losses.append(f"{s['length_mismatch']} poll result(s) discarded on length mismatch")
        if s["maybe_truncated"]:
            losses.append(f"{s['maybe_truncated']} datagram(s) hit the {RECV_BUFSIZE}B buffer")
        if s["linked_gaps"]:
            losses.append(f"{s['linked_gaps']} poll result(s) missing a linked package")
        lines.append("  losses: " + ("; ".join(losses) if losses else "none detected"))

        undecoded = sorted(k for k, v in self._attr_census.items() if not v["decoded"])
        if undecoded:
            lines.append(
                "  attributes seen but not decoded: "
                + ", ".join(f"{a} (x{self._attr_census[a]['count']})" for a in undecoded)
            )

        selected = set(self._temp_ids) | set(self._rr_ids)
        for sample in self.physio_snapshot():
            physio_id = sample["physio_id"]
            if physio_id in self._temp_ids:
                role = "TEMP candidate"
            elif physio_id in self._rr_ids:
                role = "RR candidate"
            else:
                role = "not selected"
            if physio_id == self.last_temp_id:
                role = "TEMP -> reported"
            elif physio_id == self.last_rr_id:
                role = "RR -> reported"
            flags = " NO-VALUE" if sample["special"] else ""
            lines.append(
                f"  id={physio_id} (0x{physio_id:04X}) value={sample['value']} "
                f"state=0x{sample['state']:04X} unit={sample['unit']} handle={sample['handle']} "
                f"age={sample['age_s']}s [{role}]{flags}"
            )
        if not selected:
            lines.append("  WARNING: no temp/RR physio ids configured")
        return "\n".join(lines)

    def physio_snapshot(self):
        """All observations seen since connect - for diagnostics/logging."""
        now = time.time()
        with self._samples_lock:
            return sorted(
                (
                    {
                        "physio_id": s["physio_id"],
                        "value": s["value"],
                        "state": s["state"],
                        "unit": s["unit"],
                        "handle": s["handle"],
                        "age_s": round(now - s["at"], 1),
                        "cycles_ago": self._poll_cycle - s["cycle"],
                        "special": s["special"],
                    }
                    for s in self._samples.values()
                ),
                key=lambda s: s["physio_id"],
            )
