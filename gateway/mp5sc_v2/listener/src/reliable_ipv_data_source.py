import os
import sys
import threading
import time


CURRENT_DIR = os.path.dirname(__file__)
LEGACY_SRC = os.path.abspath(
    os.path.join(CURRENT_DIR, "..", "..", "..", "mp5sc_listener", "listener", "src")
)

if LEGACY_SRC not in sys.path:
    sys.path.insert(0, LEGACY_SRC)

from ipv_data_source import ipv_data_source as LegacyIpvDataSource  # noqa: E402


class ReliableIpvDataSource(LegacyIpvDataSource):
    """
    Wraps the legacy Philips parser but fixes the watchdog lifecycle so v2
    does not start duplicate watchdog threads or compare method objects.
    """

    def __init__(self, ip):
        super().__init__(ip)
        self._watchdog_lock = threading.Lock()
        self._watchdog_started = False

    def start_client(self):
        self.run_loop = True
        self.process = threading.Thread(target=self.do_events, daemon=True)
        self.process.start()
        self.start_watchdog()

    def start_watchdog(self):
        with self._watchdog_lock:
            if self._watchdog_started and hasattr(self, "watchdog_thread") and self.watchdog_thread.is_alive():
                return
            self.run_con_watchdog = True
            self.watchdog_thread = threading.Thread(target=self.con_watchdog, daemon=True)
            self.watchdog_thread.start()
            self._watchdog_started = True

    def con_watchdog(self):
        while self.run_con_watchdog:
            if not self.check_client_is_working_correctly():
                time.sleep(5)
                if not self.check_client_is_working_correctly():
                    self.run_loop = False
                    time.sleep(5)
                    if not hasattr(self, "process") or not self.process.is_alive():
                        self.process = threading.Thread(target=self.do_events, daemon=True)
                        self.run_loop = True
                        self.process.start()
            time.sleep(5)

    def check_client_is_working_correctly(self):
        return self.is_active == self.run_loop

    def halt_client(self):
        self.run_con_watchdog = False
        self.run_loop = False
