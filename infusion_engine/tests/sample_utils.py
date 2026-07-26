"""Extract HL7 messages from the sample files (which mix prose and messages)."""

import os
import re

SAMPLES_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'samples')

_SEGMENT_RE = re.compile(r'^[A-Z][A-Z0-9]{2}\|')


def extract_messages(text):
    """Return a list of raw HL7 messages found in free-form text.

    A message starts at an MSH| line and includes subsequent segment lines.
    Lines that don't look like segments inside a message are treated as
    wrapped continuations of the previous segment line.
    """
    messages = []
    current = None
    for line in text.splitlines():
        stripped = line.strip()
        if stripped.startswith('MSH|'):
            if current:
                messages.append('\r'.join(current))
            current = [stripped]
        elif current is not None:
            if _SEGMENT_RE.match(stripped):
                current.append(stripped)
            elif stripped and not ' ' in stripped[:20]:
                # wrapped continuation of the previous segment line
                current[-1] += stripped
            elif not stripped:
                messages.append('\r'.join(current))
                current = None
            else:
                # prose line - message ended
                messages.append('\r'.join(current))
                current = None
    if current:
        messages.append('\r'.join(current))
    return messages


def load_all_samples():
    """Return list of (filename, raw_message) for every message in every sample."""
    result = []
    for name in sorted(os.listdir(SAMPLES_DIR)):
        if not name.endswith('.txt'):
            continue
        with open(os.path.join(SAMPLES_DIR, name), encoding='utf-8', errors='replace') as f:
            for i, raw in enumerate(extract_messages(f.read())):
                result.append((f'{name}#{i + 1}', raw))
    return result
