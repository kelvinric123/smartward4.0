from typing import Dict, List, Optional, Tuple
from urllib.parse import quote

import requests


# Send outcome, returned as (ok, failure_class, http_status, detail).
# failure_class is one of: None (success), 'transient', 'soft', 'hard', 'auth'.
SendResult = Tuple[bool, Optional[str], Optional[int], str]

# Patient lookup outcome: (status, data, detail) with status in
# 'found' | 'not_found' | 'error'.
LookupResult = Tuple[str, Optional[Dict], str]


class ApiClient:
    def __init__(self, settings):
        self.settings = settings
        self.session = requests.Session()
        self.session.headers.update(
            {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-Passphrase": self.settings.api_passphrase,
            }
        )

    def fetch_devices(self) -> List[Dict]:
        response = self.session.get(
            f"{self.settings.api_base_url}/monitor-devices",
            timeout=10,
        )
        response.raise_for_status()
        body = response.json()
        return body.get("data", {}).get("devices", []) if isinstance(body, dict) else []

    def update_device_status(self, device_id, status: str, connected: bool) -> None:
        if not device_id:
            return
        self.session.post(
            f"{self.settings.api_base_url}/monitor-devices/{device_id}/status",
            json={"status": status, "connected": connected},
            timeout=5,
        )

    def _auth_fields(self) -> Dict:
        # Credentials are attached at send time and never persisted in the queue.
        return {
            "username": self.settings.api_username,
            "password": self.settings.api_password,
        }

    def lookup_patient(self, patient_code: str) -> LookupResult:
        """GET /api/v1/patients/{code} — the same lookup the ward uses.

        The server matches the code against mrn, rn, visit_number and
        ic_passport, so whatever the nurse typed into the NC5 resolves the same
        way it would anywhere else in SmartWard. 'error' is kept distinct from
        'not_found' because the caller may serve a cached patient when the API
        is unreachable, but must never invent one that the API says is unknown.
        """
        code = (patient_code or "").strip()
        if not code:
            return "not_found", None, "empty patient code"

        url = f"{self.settings.api_base_url}/patients/{quote(code, safe='')}"
        try:
            response = self.session.get(
                url,
                params=self._auth_fields(),
                timeout=self.settings.api_timeout,
            )
        except requests.exceptions.RequestException as exc:
            return "error", None, f"network error: {exc}"

        if response.status_code == 200:
            try:
                body = response.json()
            except ValueError:
                return "error", None, "malformed JSON in lookup response"
            data = body.get("data") if isinstance(body, dict) else None
            if not isinstance(data, dict):
                return "error", None, "lookup response had no data block"
            return "found", data, ""
        if response.status_code == 404:
            return "not_found", None, "patient not found"
        return "error", None, f"HTTP {response.status_code}: {(response.text or '')[:200]}"

    def send_heartbeat(self, payload: Dict) -> Tuple[bool, str]:
        body = {**payload, **self._auth_fields()}
        try:
            response = self.session.post(
                f"{self.settings.api_base_url}/gateway/heartbeat",
                json=body,
                timeout=10,
            )
        except requests.exceptions.RequestException as exc:
            return False, f"network error: {exc}"
        if response.status_code == 200:
            return True, (response.text or "")[:200]
        return False, f"HTTP {response.status_code}: {(response.text or '')[:200]}"

    def send_vital_signs(self, payload: Dict, event_id: str) -> SendResult:
        body = {**payload, **self._auth_fields()}
        headers = {"X-Idempotency-Key": event_id}

        try:
            response = self.session.post(
                f"{self.settings.api_base_url}/vital-signs",
                json=body,
                headers=headers,
                timeout=self.settings.api_timeout,
            )
        except requests.exceptions.RequestException as exc:
            # DNS failure, connection refused, timeout, etc. — the network or
            # server will recover, so this is transient (retry with backoff).
            return False, "transient", None, f"network error: {exc}"

        status = response.status_code
        text = (response.text or "")[:500]

        if status in (200, 201):
            # 200 may be an idempotent replay of an already-recorded reading.
            # Either way the server has it, so treat as success.
            return True, None, status, text

        if status == 404:
            # Patient not found — may just not be admitted yet; retry (bounded).
            return False, "soft", status, f"HTTP 404: {text}"
        if status in (400, 422):
            # Bad/invalid payload — will never succeed on retry.
            return False, "hard", status, f"HTTP {status}: {text}"
        if status in (401, 403):
            # Credentials/passphrase problem — retry a few times then give up.
            return False, "auth", status, f"HTTP {status}: {text}"

        # 5xx, 429, and anything else: transient.
        return False, "transient", status, f"HTTP {status}: {text}"
