import json
import os
import sqlite3
import threading
from datetime import datetime, timedelta
from typing import Dict, List


def utcnow_text() -> str:
    return datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")


class OutboxStorage:
    def __init__(self, db_path: str):
        self.db_path = os.path.abspath(db_path)
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self._lock = threading.Lock()
        self._init_db()

    def _connect(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.db_path, timeout=30, check_same_thread=False)
        conn.row_factory = sqlite3.Row
        conn.execute("PRAGMA journal_mode=WAL")
        conn.execute("PRAGMA synchronous=NORMAL")
        return conn

    def _init_db(self) -> None:
        with self._connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS outbox_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id TEXT NOT NULL UNIQUE,
                    device_name TEXT,
                    monitor_ip TEXT,
                    patient_id TEXT,
                    measured_at TEXT,
                    payload_json TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    retry_count INTEGER NOT NULL DEFAULT 0,
                    next_retry_at TEXT NOT NULL,
                    last_error TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    sent_at TEXT
                )
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_outbox_status_retry
                ON outbox_events(status, next_retry_at, id)
                """
            )

    def enqueue(self, event_id: str, payload: Dict, device_name: str, monitor_ip: str) -> bool:
        now = utcnow_text()
        with self._lock, self._connect() as conn:
            cur = conn.execute(
                """
                INSERT OR IGNORE INTO outbox_events (
                    event_id, device_name, monitor_ip, patient_id, measured_at,
                    payload_json, status, retry_count, next_retry_at,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?, ?)
                """,
                (
                    event_id,
                    device_name,
                    monitor_ip,
                    payload.get("patient_code", ""),
                    payload.get("measured_at", ""),
                    json.dumps(payload, separators=(",", ":"), ensure_ascii=True),
                    now,
                    now,
                    now,
                ),
            )
            return cur.rowcount == 1

    def fetch_due_events(self, limit: int) -> List[Dict]:
        now = utcnow_text()
        with self._lock, self._connect() as conn:
            rows = conn.execute(
                """
                SELECT * FROM outbox_events
                WHERE status IN ('pending', 'retry')
                  AND next_retry_at <= ?
                ORDER BY id ASC
                LIMIT ?
                """,
                (now, limit),
            ).fetchall()
        return [dict(row) for row in rows]

    def mark_sent(self, row_id: int) -> None:
        now = utcnow_text()
        with self._lock, self._connect() as conn:
            conn.execute(
                """
                UPDATE outbox_events
                SET status = 'sent', sent_at = ?, updated_at = ?, last_error = NULL
                WHERE id = ?
                """,
                (now, now, row_id),
            )

    def mark_retry(self, row_id: int, retry_count: int, delay_seconds: int, error: str) -> None:
        now = datetime.utcnow()
        next_retry = now + timedelta(seconds=delay_seconds)
        with self._lock, self._connect() as conn:
            conn.execute(
                """
                UPDATE outbox_events
                SET status = 'retry',
                    retry_count = ?,
                    last_error = ?,
                    next_retry_at = ?,
                    updated_at = ?
                WHERE id = ?
                """,
                (
                    retry_count,
                    error[:1000],
                    next_retry.strftime("%Y-%m-%d %H:%M:%S"),
                    now.strftime("%Y-%m-%d %H:%M:%S"),
                    row_id,
                ),
            )

    def get_stats(self) -> Dict:
        with self._lock, self._connect() as conn:
            pending = conn.execute(
                "SELECT COUNT(*) FROM outbox_events WHERE status IN ('pending', 'retry')"
            ).fetchone()[0]
            oldest = conn.execute(
                """
                SELECT created_at FROM outbox_events
                WHERE status IN ('pending', 'retry')
                ORDER BY id ASC
                LIMIT 1
                """
            ).fetchone()
        return {
            "pending_count": pending,
            "oldest_pending_at": oldest[0] if oldest else None,
        }
