"""
Patient lookup for the NC5's HL7 query, backed by the SmartWard API.

The monitor asks "who is MRN 12345?" and blocks its admit dialog until we
answer, so this sits directly in the request path. Two rules shape it:

  * A miss is authoritative. If the API says the patient is unknown we say so,
    never a guess and never a stale entry — admitting the wrong patient at the
    bedside is the failure mode this whole gateway exists to avoid.
  * An outage is not a miss. If SmartWard is unreachable we may re-serve a
    patient the API confirmed recently (PATIENT_CACHE_STALE_TTL), so a Wi-Fi
    blip does not stop the ward from admitting a patient it already looked up.
"""

import threading
import time
from typing import Dict, Optional, Tuple

# (status, patient, detail, stale) with status in 'found' | 'not_found' | 'error'
DirectoryResult = Tuple[str, Optional[Dict], str, bool]


class PatientDirectory:
    def __init__(self, settings, api_client, logger=None):
        self.settings = settings
        self.api_client = api_client
        self._log = logger or (lambda message, level="INFO": None)
        self._lock = threading.Lock()
        self._cache: Dict[str, Dict] = {}  # code -> {"data": ..., "at": ts}
        self.lookups = 0
        self.hits = 0
        self.misses = 0
        self.errors = 0
        self.stale_served = 0

    @staticmethod
    def _key(code: str) -> str:
        return (code or "").strip().upper()

    def _cached(self, key: str, max_age: int) -> Optional[Dict]:
        entry = self._cache.get(key)
        if not entry:
            return None
        if (time.time() - entry["at"]) > max_age:
            return None
        return entry["data"]

    def lookup(self, patient_code: str) -> DirectoryResult:
        key = self._key(patient_code)
        if not key:
            return "not_found", None, "empty patient code", False

        with self._lock:
            self.lookups += 1
            fresh = self._cached(key, self.settings.patient_cache_ttl)
        if fresh is not None:
            return "found", fresh, "cache hit", False

        status, data, detail = self.api_client.lookup_patient(patient_code)

        if status == "found":
            with self._lock:
                self._cache[key] = {"data": data, "at": time.time()}
                self.hits += 1
                # Bound the cache; queries are user-initiated so it stays small.
                if len(self._cache) > 500:
                    oldest = sorted(self._cache.items(), key=lambda kv: kv[1]["at"])
                    for old_key, _ in oldest[:100]:
                        self._cache.pop(old_key, None)
            return "found", data, detail, False

        if status == "not_found":
            with self._lock:
                self.misses += 1
                self._cache.pop(key, None)  # drop a discharged/merged patient
            return "not_found", None, detail, False

        # API unreachable or erroring: fall back to a recently confirmed answer.
        with self._lock:
            self.errors += 1
            stale = self._cached(key, self.settings.patient_cache_stale_ttl)
            if stale is not None:
                self.stale_served += 1
        if stale is not None:
            self._log(f"Patient lookup for {patient_code} served from cache ({detail})", "WARNING")
            return "found", stale, detail, True
        return "error", None, detail, False

    def snapshot(self) -> Dict:
        with self._lock:
            return {
                "lookups": self.lookups,
                "found": self.hits,
                "not_found": self.misses,
                "errors": self.errors,
                "stale_served": self.stale_served,
                "cached": len(self._cache),
            }
