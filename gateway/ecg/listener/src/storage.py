import json
import os
import sqlite3
import threading
from datetime import datetime, timedelta
from typing import Dict, List, Optional


TIME_FMT = "%Y-%m-%d %H:%M:%S"

# Columns we may need to add to an already-existing DB (self-migration).
_MIGRATIONS = {
    "failure_class": "ALTER TABLE outbox_events ADD COLUMN failure_class TEXT",
    "http_status": "ALTER TABLE outbox_events ADD COLUMN http_status INTEGER",
    "first_attempt_at": "ALTER TABLE outbox_events ADD COLUMN first_attempt_at TEXT",
    "dead_reason": "ALTER TABLE outbox_events ADD COLUMN dead_reason TEXT",
}


def utcnow() -> datetime:
    return datetime.utcnow()


def utcnow_text() -> str:
    return utcnow().strftime(TIME_FMT)


def _parse(ts: Optional[str]) -> Optional[datetime]:
    if not ts:
        return None
    try:
        return datetime.strptime(ts, TIME_FMT)
    except (ValueError, TypeError):
        return None


class OutboxStorage:
    """
    Store-and-forward outbox with a proper row lifecycle:

        pending -> sent  (2xx / idempotent duplicate) -> purged by retention
        pending -> retry (transient / bounded soft failures) -> pending ...
        pending -> dead  (permanent failures / exceeded caps) -> purged by retention

    Retention (FIFO) and a hard size cap keep the SQLite file from ever growing
    without bound on the Pi's SD card. `pending`/`retry` rows are only evicted as
    a last resort, and never silently.
    """

    def __init__(self, db_path: str):
        self.db_path = os.path.abspath(db_path)
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self._lock = threading.Lock()
        self._init_db()

    def _connect(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.db_path, timeout=30, check_same_thread=False)
        conn.row_factory = sqlite3.Row
        # Durability first: low write volume, so FULL sync is affordable and the
        # right call when the Pi can lose power at any moment.
        conn.execute("PRAGMA journal_mode=WAL")
        conn.execute("PRAGMA synchronous=FULL")
        conn.execute("PRAGMA busy_timeout=30000")
        conn.execute("PRAGMA auto_vacuum=INCREMENTAL")
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
                    failure_class TEXT,
                    http_status INTEGER,
                    retry_count INTEGER NOT NULL DEFAULT 0,
                    next_retry_at TEXT NOT NULL,
                    first_attempt_at TEXT,
                    last_error TEXT,
                    dead_reason TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    sent_at TEXT
                )
                """
            )
            self._migrate(conn)
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS counters (
                    name TEXT PRIMARY KEY,
                    value INTEGER NOT NULL DEFAULT 0
                )
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_outbox_due
                ON outbox_events(status, next_retry_at, id)
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_outbox_gc
                ON outbox_events(status, updated_at)
                """
            )

    def _migrate(self, conn: sqlite3.Connection) -> None:
        existing = {row["name"] for row in conn.execute("PRAGMA table_info(outbox_events)")}
        for column, ddl in _MIGRATIONS.items():
            if column not in existing:
                conn.execute(ddl)

    # ---- write path -------------------------------------------------------

    def enqueue(self, event_id: str, payload: Dict, device_name: str, monitor_ip: str) -> bool:
        """Insert a clinical payload. Returns False if event_id already queued."""
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

    def mark_retry(
        self,
        row_id: int,
        retry_count: int,
        delay_seconds: int,
        error: str,
        http_status: Optional[int],
        failure_class: str,
    ) -> None:
        now = utcnow()
        next_retry = now + timedelta(seconds=delay_seconds)
        with self._lock, self._connect() as conn:
            conn.execute(
                """
                UPDATE outbox_events
                SET status = 'retry',
                    retry_count = ?,
                    failure_class = ?,
                    http_status = ?,
                    last_error = ?,
                    next_retry_at = ?,
                    first_attempt_at = COALESCE(first_attempt_at, ?),
                    updated_at = ?
                WHERE id = ?
                """,
                (
                    retry_count,
                    failure_class,
                    http_status,
                    (error or "")[:1000],
                    next_retry.strftime(TIME_FMT),
                    now.strftime(TIME_FMT),
                    now.strftime(TIME_FMT),
                    row_id,
                ),
            )

    def mark_dead(
        self,
        row_id: int,
        reason: str,
        http_status: Optional[int],
        failure_class: str,
        error: str = "",
    ) -> None:
        now = utcnow_text()
        with self._lock, self._connect() as conn:
            conn.execute(
                """
                UPDATE outbox_events
                SET status = 'dead',
                    failure_class = ?,
                    http_status = ?,
                    dead_reason = ?,
                    last_error = ?,
                    updated_at = ?
                WHERE id = ?
                """,
                (failure_class, http_status, reason, (error or "")[:1000], now, row_id),
            )

    # ---- maintenance: retention + FIFO size cap ---------------------------

    def run_maintenance(
        self,
        retention_sent_hours: int,
        retention_dead_days: int,
        max_rows: int,
        max_mb: int,
    ) -> Dict:
        """Purge by age, then enforce a hard size cap by FIFO eviction.

        Eviction order protects unsent data: sent -> dead -> (last resort)
        pending/retry. The number of evicted pending/retry rows is reported so
        the caller can raise an alarm — that is real data loss.
        """
        result = {
            "purged_sent": 0,
            "purged_dead": 0,
            "evicted_sent": 0,
            "evicted_dead": 0,
            "evicted_pending": 0,
        }
        now = utcnow()
        sent_cutoff = (now - timedelta(hours=retention_sent_hours)).strftime(TIME_FMT)
        dead_cutoff = (now - timedelta(days=retention_dead_days)).strftime(TIME_FMT)

        with self._lock, self._connect() as conn:
            result["purged_sent"] = conn.execute(
                "DELETE FROM outbox_events WHERE status = 'sent' AND updated_at < ?",
                (sent_cutoff,),
            ).rowcount
            result["purged_dead"] = conn.execute(
                "DELETE FROM outbox_events WHERE status = 'dead' AND updated_at < ?",
                (dead_cutoff,),
            ).rowcount

            # Hard cap: evict only as many of the oldest low-priority rows as
            # needed to get back under the cap. Expendable first (sent, then
            # dead); unsent pending/retry only as a last resort.
            batch = 500
            passes = 0
            while passes < 200:
                passes += 1
                count = conn.execute("SELECT COUNT(*) FROM outbox_events").fetchone()[0]
                over_rows = count > max_rows
                over_mb = self._db_size_mb() > max_mb
                if not over_rows and not over_mb:
                    break

                # Delete exactly the overflow when we know it (row cap); fall back
                # to a fixed batch when only the byte cap is tripped.
                take = min(count - max_rows, batch) if over_rows else batch
                take = max(take, 1)

                evicted = self._evict_oldest(conn, ("sent",), take)
                if evicted:
                    result["evicted_sent"] += evicted
                    continue
                evicted = self._evict_oldest(conn, ("dead",), take)
                if evicted:
                    result["evicted_dead"] += evicted
                    continue
                # Last resort: dropping unsent clinical data.
                evicted = self._evict_oldest(conn, ("pending", "retry"), take)
                if evicted:
                    result["evicted_pending"] += evicted
                    continue
                break  # nothing left to evict

            # Commit deletes before reclaiming space: wal_checkpoint(TRUNCATE)
            # cannot run inside an open write transaction.
            conn.commit()
            conn.execute("PRAGMA incremental_vacuum")
            conn.execute("PRAGMA wal_checkpoint(TRUNCATE)")

        return result

    def _evict_oldest(self, conn: sqlite3.Connection, statuses, limit: int) -> int:
        placeholders = ",".join("?" for _ in statuses)
        return conn.execute(
            f"""
            DELETE FROM outbox_events
            WHERE id IN (
                SELECT id FROM outbox_events
                WHERE status IN ({placeholders})
                ORDER BY id ASC
                LIMIT ?
            )
            """,
            (*statuses, limit),
        ).rowcount

    def _db_size_mb(self) -> float:
        total = 0
        for suffix in ("", "-wal", "-shm"):
            path = self.db_path + suffix
            if os.path.exists(path):
                total += os.path.getsize(path)
        return total / (1024 * 1024)

    # ---- ECG file store support -------------------------------------------

    def referenced_file_paths(self) -> set:
        """Absolute file paths referenced by ANY outbox row (any status).
        Files on disk that are not in this set belong to purged rows and can
        be swept by the janitor."""
        paths = set()
        with self._lock, self._connect() as conn:
            for row in conn.execute("SELECT payload_json FROM outbox_events"):
                try:
                    meta = json.loads(row["payload_json"])
                except Exception:
                    continue
                for key in ("xml_path", "pdf_path"):
                    value = meta.get(key)
                    if value:
                        paths.add(os.path.abspath(value))
        return paths

    def bump_counter(self, name: str, delta: int = 1) -> None:
        """Persistent lifetime counters (received/sent/failed ...) that survive
        service restarts and row purges — the heartbeat reports these."""
        with self._lock, self._connect() as conn:
            conn.execute(
                """
                INSERT INTO counters (name, value) VALUES (?, ?)
                ON CONFLICT(name) DO UPDATE SET value = value + excluded.value
                """,
                (name, delta),
            )

    def get_counters(self) -> Dict:
        with self._lock, self._connect() as conn:
            return {row["name"]: row["value"] for row in conn.execute("SELECT name, value FROM counters")}

    # ---- read path: stats for logging + heartbeat -------------------------

    def get_stats(self) -> Dict:
        with self._lock, self._connect() as conn:
            counts = {"pending": 0, "retry": 0, "sent": 0, "dead": 0}
            for row in conn.execute(
                "SELECT status, COUNT(*) AS c FROM outbox_events GROUP BY status"
            ):
                counts[row["status"]] = row["c"]

            oldest = conn.execute(
                """
                SELECT created_at FROM outbox_events
                WHERE status IN ('pending', 'retry')
                ORDER BY id ASC LIMIT 1
                """
            ).fetchone()
            last_sent = conn.execute(
                "SELECT MAX(sent_at) AS t FROM outbox_events WHERE status = 'sent'"
            ).fetchone()

        oldest_dt = _parse(oldest[0]) if oldest else None
        oldest_age = int((utcnow() - oldest_dt).total_seconds()) if oldest_dt else 0
        pending_total = counts["pending"] + counts["retry"]

        return {
            "pending_count": pending_total,
            "pending": counts["pending"],
            "retry": counts["retry"],
            "sent": counts["sent"],
            "dead": counts["dead"],
            "oldest_pending_at": oldest[0] if oldest else None,
            "oldest_pending_age_s": oldest_age,
            "last_sent_at": last_sent["t"] if last_sent else None,
            "db_size_mb": round(self._db_size_mb(), 2),
        }

    def get_extended_stats(self) -> Dict:
        """Heavier stats for the low-frequency extended heartbeat: total records,
        the full date range held, and the last sent time."""
        with self._lock, self._connect() as conn:
            total = conn.execute("SELECT COUNT(*) FROM outbox_events").fetchone()[0]
            counts = {"pending": 0, "retry": 0, "sent": 0, "dead": 0}
            for row in conn.execute(
                "SELECT status, COUNT(*) AS c FROM outbox_events GROUP BY status"
            ):
                counts[row["status"]] = row["c"]
            rng = conn.execute(
                "SELECT MIN(created_at), MAX(created_at) FROM outbox_events"
            ).fetchone()
            last_sent = conn.execute(
                "SELECT MAX(sent_at) FROM outbox_events WHERE status = 'sent'"
            ).fetchone()

        return {
            "total": total,
            "pending": counts["pending"],
            "retry": counts["retry"],
            "sent": counts["sent"],
            "dead": counts["dead"],
            "oldest_record_at": rng[0] if rng else None,
            "newest_record_at": rng[1] if rng else None,
            "last_sent_at": last_sent[0] if last_sent else None,
            "db_size_mb": round(self._db_size_mb(), 2),
        }
