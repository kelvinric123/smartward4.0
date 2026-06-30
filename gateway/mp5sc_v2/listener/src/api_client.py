from typing import Dict, List, Tuple

import requests


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

    def send_vital_signs(self, payload: Dict, event_id: str) -> Tuple[bool, str]:
        headers = {"X-Idempotency-Key": event_id}
        response = self.session.post(
            f"{self.settings.api_base_url}/vital-signs",
            json=payload,
            headers=headers,
            timeout=10,
        )

        if response.status_code in (200, 201):
            return True, response.text[:500]

        response_text = response.text[:500]
        return False, f"HTTP {response.status_code}: {response_text}"
