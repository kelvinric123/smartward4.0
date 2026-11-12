"""
GE DinaWin.dll wrapper and getState() XML parser for the DINAMAP V100 gateway.

DinaWin ("DinaWin Software Instructions for Use", GE 2031886-001) hides the
monitor's serial protocol behind four calls. It searches COM1-COM10 for the
monitor itself at 9600 bps, and getState() returns an XML document with the
most recent values. Values older than the DLL load, the last resetMonitor()
call, or 30 minutes are never returned.

Kept Python 2.7 compatible so it also runs on Laragon's 32-bit Python 2.7.
"""

from __future__ import print_function

import ctypes
import os
import re
import struct
from datetime import datetime

PE_MACHINES = {0x014C: "x86 (32-bit)", 0x8664: "x64 (64-bit)", 0xAA64: "ARM64"}

# DinaWin's installer default, so a PC that already has GE's software just works.
DEFAULT_DLL_PATHS = [
    "DinaWin.dll",
    r"C:\Program Files (x86)\Vista\Vitals\Plugins\DinaWin.dll",
    r"C:\Program Files\Vista\Vitals\Plugins\DinaWin.dll",
]


def find_dll(explicit=None):
    candidates = [explicit] if explicit else [os.environ.get("DINAWIN_DLL")] + DEFAULT_DLL_PATHS
    here = os.path.dirname(os.path.abspath(__file__))
    for path in candidates:
        if not path:
            continue
        for full in (path, os.path.join(here, path)):
            if os.path.isfile(full):
                return os.path.abspath(full)
    return None


def dll_machine(path):
    """CPU type from the DLL's PE header (read as a file, nothing is loaded)."""
    with open(path, "rb") as fh:
        head = fh.read(4096)
    if head[:2] != b"MZ":
        raise ValueError("%s is not a Windows DLL" % path)
    pe = struct.unpack_from("<I", head, 0x3C)[0]
    if head[pe:pe + 4] != b"PE\0\0":
        raise ValueError("%s has no PE header" % path)
    machine = struct.unpack_from("<H", head, pe + 4)[0]
    return PE_MACHINES.get(machine, hex(machine))


def python_bits():
    return struct.calcsize("P") * 8


class DinaWin(object):
    """The four DinaWin calls. None of them can start a BP cycle."""

    def __init__(self, path):
        self.path = os.path.abspath(path)
        self.machine = dll_machine(self.path)
        want = "x86" if python_bits() == 32 else "x64"
        if not self.machine.startswith(want):
            raise RuntimeError(
                "DinaWin.dll is %s but this Python is %d-bit; run it with a %s Python "
                "(Laragon's C:\\laragon\\bin\\python\\python2.7\\python.exe is 32-bit)."
                % (self.machine, python_bits(), "32-bit" if self.machine.startswith("x86") else "64-bit"))
        self._dll = ctypes.WinDLL(self.path)
        for name, restype in (("checkReadiness", ctypes.c_int32),
                              ("getBufferLength", ctypes.c_int32),
                              ("getState", ctypes.c_char_p),
                              ("resetMonitor", ctypes.c_int32)):
            fn = getattr(self._dll, name)
            fn.argtypes = []
            fn.restype = restype

    def check_readiness(self):
        """True when DinaWin found the monitor on COM1-COM10 and it answers."""
        return bool(self._dll.checkReadiness())

    def buffer_length(self):
        return int(self._dll.getBufferLength())

    def get_state(self):
        raw = self._dll.getState()
        return (raw or b"").decode("ascii", "replace")

    def reset_monitor(self):
        """Hide values older than now (call between patients). Doesn't clear the displays."""
        return bool(self._dll.resetMonitor())


# getState() XML. Parsed with regexes, not an XML parser: GE's own sample mixes
# <Units>...</units> case and quote styles, which a strict parser rejects.
_RESULT_RE = re.compile(r"<Result\s+name\s*=\s*[\"']([^\"']+)[\"']\s*>(.*?)</Result>", re.I | re.S)
_VALUE_RE = re.compile(r"<Value>\s*([^<]*?)\s*</Value>", re.I)
_UNITS_RE = re.compile(r"<Units\s+name\s*=\s*[\"']([^\"']*)[\"']", re.I)
_METHOD_RE = re.compile(r"<Method\b([^>]*)>\s*([^<]*?)\s*</Method>", re.I)
_STAMP_RE = re.compile(r"<Time_stamp\b([^>]*)>", re.I)
_ATTR_RE = re.compile(r"(\w+)\s*=\s*[\"']([^\"']*)[\"']")
_MODEL_RE = re.compile(r"<Model>\s*([^<]*?)\s*</Model>", re.I)


def parse_state(xml):
    """getState() XML -> {"model": str, "results": {result name: {value, units, method, measured_at}}}.

    Result names (DTD): Systolic_blood_pressure, Diastolic_blood_pressure,
    Mean_arterial_pressure, Oxygen_saturation, Body_temperature, Pulse.
    Parameters that were not measured are absent, never 0.
    """
    results = {}
    for name, body in _RESULT_RE.findall(xml or ""):
        value_m = _VALUE_RE.search(body)
        if not value_m:
            continue
        try:
            value = float(value_m.group(1))
        except ValueError:
            continue
        units_m = _UNITS_RE.search(body)
        method_m = _METHOD_RE.search(body)
        method = ""
        if method_m:
            method = method_m.group(2) or dict(_ATTR_RE.findall(method_m.group(1))).get("name", "")
        measured_at = None
        stamp_m = _STAMP_RE.search(body)
        if stamp_m:
            attrs = dict((k.lower(), v) for k, v in _ATTR_RE.findall(stamp_m.group(1)))
            try:
                measured_at = datetime(*[int(attrs.get(k, 0)) for k in
                                         ("year", "month", "day", "hour", "minute", "second")])
            except ValueError:
                pass
        results[name.strip()] = {
            "value": value,
            "units": units_m.group(1) if units_m else "",
            "method": method.strip(),
            "measured_at": measured_at,
        }
    model_m = _MODEL_RE.search(xml or "")
    return {"model": model_m.group(1) if model_m else "", "results": results}
