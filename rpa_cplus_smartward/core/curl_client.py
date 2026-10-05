"""curl wrapper used for every C+ call.

Same approach as the other C+ RPAs (rpa_cplus, SmartOT's rpa_cplus_smartOT):
shell out to curl with one cookie jar, so the session login.py makes is reused
by the next script. Each response also carries the HTTP status and the URL it
ended on after redirects, which is how a bounce to the login page is told from
a real page. TLS is verified (no -k): the password goes over this connection.
"""

import json
import os
import subprocess

from config import COOKIE_JAR, DEFAULT_USER_AGENT

# curl -w appends this after the body; it cannot occur in C+ markup.
_META = "\n@@curl-meta@@"


class CurlClient:
    def __init__(self, cookie_jar=COOKIE_JAR):
        self.cookie_jar = cookie_jar
        self.user_agent = DEFAULT_USER_AGENT

    def _command(self, headers=None):
        cmd = [
            "curl", "-s",
            "-L",  # follow redirects
            "-A", self.user_agent,
            "-c", self.cookie_jar,
            "-b", self.cookie_jar,
            "-w", _META + "%{http_code} %{url_effective}",
        ]
        for k, v in (headers or {}).items():
            cmd.extend(["-H", f"{k}: {v}"])
        return cmd

    def _execute(self, cmd):
        try:
            result = subprocess.run(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                encoding="utf-8",
                errors="replace",
                check=False,
            )
        except Exception as e:
            return {"success": False, "stdout": "", "stderr": str(e), "status_code": -1,
                    "http_code": None, "url": None}

        body, marker, meta = result.stdout.rpartition(_META)
        if not marker:
            body, meta = result.stdout, ""
        http_code, _, url = meta.strip().partition(" ")
        return {
            "success": result.returncode == 0,
            "stdout": body,
            "stderr": result.stderr,
            "status_code": result.returncode,
            "http_code": http_code or None,
            "url": url or None,
        }

    def get(self, url, headers=None, params=None):
        """GET. `params` are url-encoded by curl (-G), so values containing '+' survive."""
        cmd = self._command(headers)
        if params:
            cmd.append("-G")
            for k, v in params.items():
                cmd.extend(["--data-urlencode", f"{k}={v}"])
        cmd.append(url)
        return self._execute(cmd)

    def post(self, url, data=None, headers=None, is_json=False):
        """POST a form (a dict, or a list of pairs so repeated keys survive) or JSON."""
        cmd = self._command(headers)
        if is_json:
            cmd.extend(["-H", "Content-Type: application/json"])
            if data:
                cmd.extend(["-d", json.dumps(data)])
        elif data:
            items = data.items() if isinstance(data, dict) else data
            for k, v in items:
                cmd.extend(["--data-urlencode", f"{k}={v}"])
        cmd.append(url)
        return self._execute(cmd)

    def clear_cookies(self):
        if os.path.exists(self.cookie_jar):
            os.remove(self.cookie_jar)
