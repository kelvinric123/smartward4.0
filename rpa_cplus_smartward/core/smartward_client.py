"""SmartWard's C+ endpoints, called with the shared token.

Plain urllib: the payload is JSON both ways and SmartWard is on the hospital
network, so none of the cookie handling curl gives C+ is needed here.
"""

import json
import urllib.error
import urllib.request

from config import SMARTWARD_TOKEN, SMARTWARD_URL


class SmartWardError(Exception):
    """SmartWard could not be reached or refused the call."""


class SmartWardClient:
    def __init__(self, base_url=SMARTWARD_URL, token=SMARTWARD_TOKEN, timeout=180):
        if not base_url or not token:
            raise SmartWardError("SMARTWARD_URL and SMARTWARD_TOKEN must be set in .env")
        self.base_url = base_url.rstrip("/")
        self.token = token
        self.timeout = timeout

    def _call(self, method, path, body=None):
        request = urllib.request.Request(
            self.base_url + path,
            data=json.dumps(body).encode("utf-8") if body is not None else None,
            method=method,
            headers={
                "Authorization": f"Bearer {self.token}",
                "Accept": "application/json",
                "Content-Type": "application/json",
            },
        )
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            detail = e.read().decode("utf-8", "replace")
            try:
                parsed = json.loads(detail)
                errors = parsed.get("errors") or {}
                message = "; ".join(m for msgs in errors.values() for m in msgs) or parsed.get("message") or detail
            except ValueError:
                message = detail[:200]
            raise SmartWardError(f"SmartWard refused {method} {path} (HTTP {e.code}): {message[:500]}")
        except urllib.error.URLError as e:
            raise SmartWardError(f"SmartWard at {self.base_url} is unreachable: {e.reason}")
        except ValueError:
            raise SmartWardError(f"SmartWard answered {method} {path} with something other than JSON")

    def ping(self):
        return self._call("GET", "/api/cplus/ping")

    def push(self, payload):
        return self._call("POST", "/api/cplus/bed-sync", payload)
