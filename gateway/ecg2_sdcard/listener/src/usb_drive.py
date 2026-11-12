"""
The virtual USB drive the TC35 saves its ECGs to.

On the Pi Zero 2W the drive is a FAT32 disk image (USB_IMAGE_PATH) that the
kernel's mass-storage gadget presents to the TC35 over the Pi's USB OTG port
(deploy_pi/usb_gadget.sh sets it up at boot). While plugged in, the TC35 owns
that filesystem, so the gateway never writes to it. It only:

  * watches the image file's mtime - every block the TC35 writes lands in the
    backing file, so a change means "the machine is writing";
  * once the writes go quiet, mounts the image READ-ONLY on a fresh loop
    device and reads the files. A fresh mount has no stale FAT cache, and the
    loop device reads through the same page cache the gadget writes into, so
    it sees exactly what the TC35 wrote;
  * for cleanup only: unplugs the gadget (the TC35 sees the stick removed),
    mounts read-write, deletes recordings the server already has, plugs back.

DirDrive is the same interface over a plain folder, for local testing.
"""

import os
import subprocess
import time
from collections import namedtuple
from contextlib import contextmanager


class DriveError(Exception):
    pass


FileEntry = namedtuple("FileEntry", "relpath abspath size mtime_ns")

# Folders hosts (Windows / macOS laptops used for testing) drop on USB sticks.
SKIP_DIRS = {"system volume information", "$recycle.bin", ".trashes", ".trash",
             ".spotlight-v100", ".fseventsd", ".temporaryitems"}

# MBR partition types a FAT/exFAT USB stick uses.
FAT_PARTITION_TYPES = {0x01, 0x04, 0x06, 0x07, 0x0B, 0x0C, 0x0E}


def path_key(relpath: str) -> str:
    """FAT is case-insensitive: one key per file regardless of case/separator."""
    return relpath.replace("\\", "/").lower()


def walk(root: str):
    """Every file under root as FileEntry, skipping host system folders."""
    entries = []
    for dirpath, dirnames, filenames in os.walk(root, onerror=lambda e: None):
        dirnames[:] = [d for d in dirnames if d.lower() not in SKIP_DIRS]
        for name in filenames:
            if name.startswith("._"):  # macOS resource forks
                continue
            abspath = os.path.join(dirpath, name)
            try:
                st = os.stat(abspath)
            except OSError:
                continue
            relpath = os.path.relpath(abspath, root).replace("\\", "/")
            entries.append(FileEntry(relpath, abspath, st.st_size, st.st_mtime_ns))
    return entries


def looks_like_boot_sector(sector: bytes) -> bool:
    return sector[0] in (0xEB, 0xE9) and (
        sector[54:57] == b"FAT" or sector[82:87] == b"FAT32" or sector[3:11] == b"EXFAT   "
    )


def find_fs_offset(sector0: bytes) -> int:
    """Byte offset of the filesystem inside the drive image.

    setup.sh creates an MBR with one FAT32 partition at 1 MiB, but the TC35
    (or a laptop) may reformat the stick - possibly without a partition table
    - so this is worked out from sector 0 on every mount."""
    if len(sector0) < 512 or sector0[510:512] != b"\x55\xaa":
        raise DriveError("no boot signature in sector 0 (drive not formatted?)")
    if looks_like_boot_sector(sector0):
        return 0  # "superfloppy": filesystem without a partition table
    for i in range(4):
        entry = sector0[446 + 16 * i: 462 + 16 * i]
        ptype = entry[4]
        start = int.from_bytes(entry[8:12], "little")
        count = int.from_bytes(entry[12:16], "little")
        if ptype == 0xEE:
            raise DriveError("GPT-partitioned drive is not supported")
        if ptype in FAT_PARTITION_TYPES and start and count:
            return start * 512
    raise DriveError("no FAT partition in the partition table")


class Gadget:
    """The configfs mass-storage gadget created by usb_gadget.sh. Binding it
    to the Pi's USB controller is "plugging the stick into the TC35"."""

    CONFIGFS = "/sys/kernel/config/usb_gadget"
    UDC_CLASS = "/sys/class/udc"

    def __init__(self, name: str):
        self.path = os.path.join(self.CONFIGFS, name)

    @staticmethod
    def _read(path: str) -> str:
        try:
            with open(path, "r") as fh:
                return fh.read().strip()
        except OSError:
            return ""

    def controller(self) -> str:
        try:
            names = sorted(os.listdir(self.UDC_CLASS))
        except OSError:
            return ""
        return names[0] if names else ""

    def bound_udc(self) -> str:
        return self._read(os.path.join(self.path, "UDC"))

    def state(self) -> str:
        """'absent' (no gadget), 'unbound', or the controller's USB state:
        'not attached' (no host / cable), 'configured' (the TC35 has
        enumerated the stick), 'suspended' (host bus idle), ..."""
        if not os.path.isdir(self.path):
            return "absent"
        udc = self.bound_udc()
        if not udc:
            return "unbound"
        return self._read(os.path.join(self.UDC_CLASS, udc, "state")) or "unknown"

    @staticmethod
    def host_connected(state: str) -> bool:
        return state in ("configured", "suspended")

    def unbind(self) -> None:
        if not self.bound_udc():
            return
        with open(os.path.join(self.path, "UDC"), "w") as fh:
            fh.write("\n")
        if self.bound_udc():
            raise DriveError("gadget did not unbind")

    def bind(self) -> None:
        if self.bound_udc():
            return
        udc = self.controller()
        if not udc:
            raise DriveError("no USB device controller (dwc2 not loaded?)")
        with open(os.path.join(self.path, "UDC"), "w") as fh:
            fh.write(udc + "\n")


class ImageDrive:
    kind = "image"

    def __init__(self, image_path: str, mount_dir: str, gadget: Gadget, replug_wait: float = 2.0):
        self.image_path = image_path
        self.mount_dir = mount_dir
        self.gadget = gadget
        self.replug_wait = replug_wait

    def available(self) -> bool:
        return os.path.isfile(self.image_path)

    def activity_signature(self):
        try:
            st = os.stat(self.image_path)
        except OSError:
            return None
        return (st.st_mtime_ns, st.st_size)

    def fs_offset(self) -> int:
        with open(self.image_path, "rb") as fh:
            return find_fs_offset(fh.read(512))

    @staticmethod
    def _run(cmd, timeout=30):
        return subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                              universal_newlines=True, timeout=timeout)

    def _umount(self) -> None:
        for _ in range(3):
            if not os.path.ismount(self.mount_dir):
                return
            if self._run(["umount", self.mount_dir]).returncode == 0:
                return
            time.sleep(1)
        self._run(["umount", "-l", self.mount_dir])

    @contextmanager
    def open_volume(self, writable: bool = False):
        """Mount the drive's filesystem and yield its root path."""
        if not self.available():
            raise DriveError(f"drive image missing: {self.image_path}")
        self._umount()  # leftover from a crash
        os.makedirs(self.mount_dir, exist_ok=True)
        opts = "{},loop,offset={},noexec,nosuid,nodev".format(
            "rw" if writable else "ro", self.fs_offset())
        error = ""
        for args in (["-t", "vfat", "-o", opts + ",utf8"], ["-o", opts]):
            result = self._run(["mount", *args, self.image_path, self.mount_dir])
            if result.returncode == 0:
                break
            error = (result.stdout or "").strip()
        else:
            raise DriveError(f"mount failed: {error}")
        try:
            yield self.mount_dir
        finally:
            self._umount()

    @contextmanager
    def unplugged(self):
        """Disconnect the stick from the TC35 for the duration (cleanup only)."""
        was_bound = bool(self.gadget.bound_udc())
        if was_bound:
            self.gadget.unbind()
            time.sleep(self.replug_wait)  # let the TC35 notice the removal
        try:
            yield
        finally:
            if was_bound:
                self.gadget.bind()

    def status(self):
        state = self.gadget.state()
        return {
            "mode": self.kind,
            "image": self.image_path,
            "image_ok": self.available(),
            "gadget_state": state,
            "host_connected": Gadget.host_connected(state),
        }


class DirDrive:
    """A plain folder standing in for the drive (local testing)."""

    kind = "dir"

    def __init__(self, path: str):
        self.path = path

    def available(self) -> bool:
        return os.path.isdir(self.path)

    def activity_signature(self):
        if not self.available():
            return None
        entries = walk(self.path)
        return (len(entries), sum(e.size for e in entries), max((e.mtime_ns for e in entries), default=0))

    @contextmanager
    def open_volume(self, writable: bool = False):
        if not self.available():
            raise DriveError(f"watch folder missing: {self.path}")
        yield self.path

    @contextmanager
    def unplugged(self):
        yield

    def status(self):
        return {
            "mode": self.kind,
            "image": self.path,
            "image_ok": self.available(),
            "gadget_state": "n/a",
            "host_connected": None,
        }
