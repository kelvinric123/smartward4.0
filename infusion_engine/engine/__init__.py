"""Infusion Engine - standalone HL7 infusion data service.

Receives HL7 v2.x infusion messages (B.Braun SpacePlus / IHE PCD) over MLLP,
stores every raw message, parses the infusion data (rate, volumes, drug,
status, alarms) into SQLite, and serves it over a REST API for SmartWard.
"""

__version__ = '1.3.0'
