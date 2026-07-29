import os
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


# Mantissa the monitor publishes when a parameter is off, still measuring, or
# otherwise unavailable.
INVALID_SENTINEL = 8388607

# Labels the monitor uses for the value taken/confirmed on the monitor itself.
DEFAULT_PRIMARY_TEMP_IDS = "19272,19296,19298,61639"
# IEEE 11073 / Philips SCADA temperature block. Any other label inside this band
# is a probe channel (skin, tympanic, rectal, oral, esophageal, ...).
DEFAULT_TEMP_BAND = (19272, 19420)


def parse_id_list(raw):
    """Parse a comma/semicolon separated list of numeric label IDs."""
    ids = set()
    for part in str(raw or "").replace(";", ",").split(","):
        part = part.strip()
        if not part:
            continue
        try:
            ids.add(int(part))
        except ValueError:
            continue
    return ids


class TempPolicy:
    """Field-tunable rules for turning a raw monitor observation into Celsius.

    Everything here is env-driven because the exact label a given MP5SC uses for
    the probe is site-specific; a wrong assumption is fixed with one line in
    `.env` instead of a code change.
    """

    def __init__(self, settings=None):
        get = (lambda n, d: getattr(settings, n, d)) if settings is not None else (lambda n, d: d)
        self.primary_ids = parse_id_list(get("temp_primary_ids", DEFAULT_PRIMARY_TEMP_IDS))
        self.secondary_ids = parse_id_list(get("temp_secondary_ids", ""))
        self.band_min = int(get("temp_id_band_min", DEFAULT_TEMP_BAND[0]))
        self.band_max = int(get("temp_id_band_max", DEFAULT_TEMP_BAND[1]))
        self.min_c = float(get("temp_min_c", 25.0))
        self.max_c = float(get("temp_max_c", 45.0))
        self.fahrenheit = bool(get("temp_fahrenheit_autoconvert", True))
        self.autoscale = bool(get("temp_autoscale", True))

    def is_temp_label(self, label_id):
        return (
            label_id in self.primary_ids
            or label_id in self.secondary_ids
            or self.band_min <= label_id <= self.band_max
        )

    def bucket(self, label_id):
        """'primary' = value taken on the monitor, 'secondary' = probe."""
        if label_id in self.primary_ids:
            return "primary"
        return "secondary"

    def to_celsius(self, raw):
        """(celsius, note) for a plausible reading, or (None, reason)."""
        try:
            value = float(raw)
        except (TypeError, ValueError):
            return None, "not numeric"
        if value == INVALID_SENTINEL or value <= 0:
            return None, "unavailable"

        notes = []
        if self.autoscale:
            # Some channels publish the value unscaled (365 = 36.5 C,
            # 986 = 98.6 F). Only reachable for labels already identified as
            # temperature, so there is nothing else these ranges could be.
            if 250 <= value <= 450 or 890 <= value <= 1130:
                value /= 10.0
                notes.append("scaled/10")
            elif 2500 <= value <= 4500:
                value /= 100.0
                notes.append("scaled/100")
        if self.fahrenheit and 89.6 <= value <= 113.0:
            value = (value - 32.0) * 5.0 / 9.0
            notes.append("F->C")

        value = round(value, 1)
        if not (self.min_c <= value <= self.max_c):
            return None, f"out of range ({value})"
        return value, (" [" + ", ".join(notes) + "]" if notes else "")


class ReliableIpvDataSource(LegacyIpvDataSource):
    """
    Wraps the legacy Philips parser but fixes the watchdog lifecycle so v2
    does not start duplicate watchdog threads or compare method objects, and
    keeps one temperature slot per monitor label instead of a single
    last-write-wins value.
    """

    def __init__(self, ip, settings=None):
        super().__init__(ip)
        self._watchdog_lock = threading.Lock()
        self._watchdog_started = False

        self.temp_policy = TempPolicy(settings)
        self._temp_lock = threading.Lock()
        # label id -> (celsius, monotonic timestamp, bucket)
        self._temp_slots = {}
        self._temp_events = []
        self._temp_labels_seen = set()
        self._temp_candidates_seen = set()

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

    # --- temperature capture -------------------------------------------------

    def extract_physoi_id(self, p_id, observ_val, handle_id):
        """Keep legacy decoding intact, then run per-label temperature capture.

        The legacy parser collapses every temperature label into one `p_temp`
        field, so whichever label the monitor happened to publish last wins. A
        probe reading followed by an "unavailable" publish on another
        temperature channel therefore left `p_temp` empty and the reading was
        stored as "--". Here each label keeps its own slot and an unavailable
        publish can never overwrite a good reading from another channel.
        """
        super().extract_physoi_id(p_id, observ_val, handle_id)

        if not self.temp_policy.is_temp_label(p_id):
            self._note_temp_candidate(p_id, observ_val)
            return

        celsius, note = self.temp_policy.to_celsius(observ_val)
        if celsius is None:
            return

        bucket = self.temp_policy.bucket(p_id)
        with self._temp_lock:
            self._temp_slots[p_id] = (celsius, time.monotonic(), bucket)
            if p_id not in self._temp_labels_seen:
                self._temp_labels_seen.add(p_id)
                source = "monitor" if bucket == "primary" else "probe"
                self._temp_events.append(
                    f"label {p_id} mapped as {source} source (raw={observ_val} -> {celsius}C{note})"
                )

    def _note_temp_candidate(self, p_id, observ_val):
        """Debug aid: flag an unmapped label carrying a temperature-shaped value.

        This is how the probe's real label is identified on a specific monitor
        without guessing; add it to TEMP_SECONDARY_IDS once confirmed.
        """
        if not self.debug_info or p_id in self._temp_candidates_seen:
            return
        celsius, _ = self.temp_policy.to_celsius(observ_val)
        if celsius is None:
            return
        self._temp_candidates_seen.add(p_id)
        with self._temp_lock:
            self._temp_events.append(
                f"unmapped label {p_id} carries a temperature-shaped value "
                f"({observ_val} -> {celsius}C); add it to TEMP_SECONDARY_IDS if this is the probe"
            )

    def get_temperature_readings(self):
        """Freshest valid reading per source.

        Returns {'primary': entry|None, 'secondary': entry|None} where entry is
        {'value', 'age', 'label'}. Age is in seconds rather than an absolute
        timestamp so the caller does not have to share this class's clock base.
        """
        now = time.monotonic()
        best = {"primary": None, "secondary": None}
        with self._temp_lock:
            slots = dict(self._temp_slots)
        for label, (celsius, captured_at, bucket) in slots.items():
            entry = {"value": celsius, "age": max(now - captured_at, 0.0), "label": label}
            current = best.get(bucket)
            if current is None or entry["age"] < current["age"]:
                best[bucket] = entry
        return best

    def clear_temperatures(self):
        """Drop every captured temperature (used when the patient changes)."""
        with self._temp_lock:
            self._temp_slots.clear()
        self.p_temp = 0

    def drain_temp_events(self):
        """Pop pending one-shot mapping messages so the listener can log them."""
        with self._temp_lock:
            events, self._temp_events = self._temp_events, []
        return events
