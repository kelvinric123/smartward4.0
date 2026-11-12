import json
import urllib.error
import urllib.request
from typing import Dict, Optional, Tuple


# Send outcome, returned as (ok, failure_class, http_status, detail).
# failure_class is one of: None (success), 'transient', 'hard', 'auth'.
# (No 'soft' class here: the server accepts ECGs without requiring the patient
# to be admitted - matching happens at view time by MRN/RN.)
SendResult = Tuple[bool, Optional[str], Optional[int], str]


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    """Surface a redirect as an error instead of following it: urllib would
    silently turn the POST into a GET without the ECG. (PHKL's port 80
    redirects IP access to the HTTPS domain - the gateway must use :88.)"""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class ApiClient:
    """SmartWard API client - same calls and outcome classes as
    gateway/ecg/listener/src/api_client.py, but stdlib-only, so the Pi needs no
    pip packages and the first-boot setup works on Wi-Fi without internet."""

    def __init__(self, settings):
        self.settings = settings
        self.headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Passphrase": self.settings.api_passphrase,
        }
        self.opener = urllib.request.build_opener(_NoRedirect)

    def _auth_fields(self) -> Dict:
        # Credentials are attached at send time and never persisted in the queue.
        return {
            "username": self.settings.api_username,
            "password": self.settings.api_password,
        }

    def _post(self, path: str, body: Dict, extra_headers: Optional[Dict] = None,
              timeout: int = 10) -> Tuple[Optional[int], str]:
        """POST JSON. Returns (status, text); status is None when no HTTP
        response arrived (DNS failure, refused, timeout) and text says why."""
        request = urllib.request.Request(
            f"{self.settings.api_base_url}{path}",
            data=json.dumps(body).encode("utf-8"),
            headers={**self.headers, **(extra_headers or {})},
            method="POST",
        )
        try:
            with self.opener.open(request, timeout=timeout) as response:
                return response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as exc:
            try:
                text = exc.read().decode("utf-8", "replace")
            except Exception:
                text = ""
            if 300 <= exc.code < 400:
                text = f"redirected to {exc.headers.get('Location')} - check API_BASE_URL"
            return exc.code, text
        except (urllib.error.URLError, OSError, ValueError) as exc:
            return None, str(getattr(exc, "reason", exc))

    def send_heartbeat(self, payload: Dict) -> Tuple[bool, str]:
        status, text = self._post("/gateway/heartbeat", {**payload, **self._auth_fields()})
        if status is None:
            return False, f"network error: {text}"
        if status == 200:
            return True, text[:200]
        return False, f"HTTP {status}: {text[:200]}"

    def send_ecg(self, payload: Dict, event_id: str) -> SendResult:
        """POST one ECG recording (base64 XML + optional PDF) to SmartWard."""
        status, text = self._post(
            "/ecg",
            {**payload, **self._auth_fields()},
            {"X-Idempotency-Key": event_id},
            timeout=60,  # ECG PDFs are megabytes; allow slow ward Wi-Fi
        )
        if status is None:
            # DNS failure, connection refused, timeout, etc. - the network or
            # server will recover, so this is transient (retry with backoff).
            return False, "transient", None, f"network error: {text}"

        text = text[:500]
        if status in (200, 201):
            # 200 is an idempotent replay of an already-received recording.
            return True, None, status, text

        if status in (400, 413, 422):
            # Bad/invalid/oversized payload - will never succeed on retry.
            return False, "hard", status, f"HTTP {status}: {text}"
        if status in (401, 403):
            # Credentials/passphrase problem - retry a few times then give up.
            return False, "auth", status, f"HTTP {status}: {text}"

        # 5xx (e.g. the server's ECG store still read-only), 3xx, 429: transient.
        return False, "transient", status, f"HTTP {status}: {text}"
