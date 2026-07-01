from typing import Dict, List, Optional, Tuple

import requests


# Send outcome, returned as (ok, failure_class, http_status, detail).
# failure_class is one of: None (success), 'transient', 'soft', 'hard', 'auth'.
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
                timeout=10,
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
