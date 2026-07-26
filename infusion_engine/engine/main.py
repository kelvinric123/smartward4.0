"""Infusion engine entry point: MLLP listener + REST API + optional retention purge.

Run with:  python -m engine.main
"""

import logging
import os
import signal
import sys
import threading
import time

from . import __version__, config
from .api import create_server
from .db import Database
from .demo import DemoSimulator
from .mllp_server import MLLPServer


def setup_logging():
    os.makedirs(config.LOG_DIR, exist_ok=True)
    fmt = logging.Formatter(
        '%(asctime)s | %(levelname)-8s | %(name)s | %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S',
    )
    root = logging.getLogger()
    root.setLevel(getattr(logging, config.LOG_LEVEL, logging.INFO))

    console = logging.StreamHandler(sys.stdout)
    console.setFormatter(fmt)
    root.addHandler(console)

    file_handler = logging.FileHandler(
        os.path.join(config.LOG_DIR, 'infusion_engine.log'), encoding='utf-8')
    file_handler.setFormatter(fmt)
    root.addHandler(file_handler)


def retention_loop(db, days, stop_event):
    logger = logging.getLogger('infusion.retention')
    while not stop_event.wait(3600):
        try:
            removed = db.purge_older_than(days)
            if removed:
                logger.info('Purged %d rows older than %d days', removed, days)
        except Exception:
            logger.exception('Retention purge failed')


def main():
    setup_logging()
    logger = logging.getLogger('infusion.main')
    logger.info('=' * 60)
    logger.info('INFUSION ENGINE v%s', __version__)
    logger.info('MLLP in : %s:%d', config.MLLP_HOST, config.MLLP_PORT)
    logger.info('API out : %s:%d (auth: %s)', config.API_HOST, config.API_PORT,
                'API key' if config.API_KEY else 'open')
    logger.info('=' * 60)

    pg = None
    if config.DB_HOST:
        pg = {'host': config.DB_HOST, 'port': config.DB_PORT,
              'database': config.DB_NAME, 'user': config.DB_USER,
              'password': config.DB_PASSWORD}

    db = None
    for attempt in range(1, 31):
        try:
            db = Database(sqlite_path=config.DB_PATH, pg=pg)
            break
        except Exception as e:
            logger.warning('Database not ready (attempt %d/30): %s', attempt, e)
            time.sleep(2)
    if db is None:
        logger.error('Could not connect to the database - exiting')
        sys.exit(1)
    stop_event = threading.Event()

    mllp = MLLPServer(config.MLLP_HOST, config.MLLP_PORT, db)
    mllp_thread = threading.Thread(target=mllp.serve_forever, daemon=True)
    mllp_thread.start()

    if config.RETENTION_DAYS > 0:
        logger.info('Retention: purging data older than %d days', config.RETENTION_DAYS)
        threading.Thread(
            target=retention_loop, args=(db, config.RETENTION_DAYS, stop_event),
            daemon=True,
        ).start()

    demo = None
    if config.DEMO_ENABLED:
        demo = DemoSimulator('127.0.0.1', config.MLLP_PORT)
        logger.info('Demo simulator enabled (control via /api/demo/*)')

    api_server = create_server(config.API_HOST, config.API_PORT, db, config.API_KEY, demo)

    def shutdown(signum, _frame):
        logger.info('Signal %s received, shutting down', signum)
        stop_event.set()
        mllp.stop()
        threading.Thread(target=api_server.shutdown, daemon=True).start()

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    try:
        api_server.serve_forever()
    finally:
        mllp.stop()
        time.sleep(0.2)
        db.close()
        logger.info('Infusion engine stopped')


if __name__ == '__main__':
    main()
