from typing import Dict, Optional, Tuple

import requests


# Send outcome, returned as (ok, failure_class, http_status, detail).
# failure_class is one of: None (success), 'transient', 'hard', 'auth'.
# (No 'soft' class here: the server accepts ECGs without requiring the patient
# to be admitted — matching happens at view time by MRN/RN.)
SendResult = Tuple[bool, Optional[str], Optional[int], str]


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

    def _auth_fields(self) -> Dict:
        # Credentials are attached at send time and never persisted in the queue.
        return {
            "username": self.settings.api_username,
            "password": self.settings.api_password,
        }

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

    def send_ecg(self, payload: Dict, event_id: str) -> SendResult:
        """POST one ECG recording (base64 XML + optional PDF) to SmartWard."""
        body = {**payload, **self._auth_fields()}
        headers = {"X-Idempotency-Key": event_id}

        try:
            response = self.session.post(
                f"{self.settings.api_base_url}/ecg",
                json=body,
                headers=headers,
                timeout=60,  # ECG PDFs are megabytes; allow slow ward Wi-Fi
            )
        except requests.exceptions.RequestException as exc:
            # DNS failure, connection refused, timeout, etc. — the network or
            # server will recover, so this is transient (retry with backoff).
            return False, "transient", None, f"network error: {exc}"

        status = response.status_code
        text = (response.text or "")[:500]

        if status in (200, 201):
            # 200 is an idempotent replay of an already-received recording.
            return True, None, status, text

        if status in (400, 413, 422):
            # Bad/invalid/oversized payload — will never succeed on retry.
            return False, "hard", status, f"HTTP {status}: {text}"
        if status in (401, 403):
            # Credentials/passphrase problem — retry a few times then give up.
            return False, "auth", status, f"HTTP {status}: {text}"

        # 5xx, 429, and anything else: transient.
        return False, "transient", status, f"HTTP {status}: {text}"
