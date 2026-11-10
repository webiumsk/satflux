#!/usr/bin/env python3
"""Capture sandbox-only credentials locally without command arguments or echo."""

import getpass
import json
import os
from pathlib import Path
import re
import stat
import sys
import warnings

DIRECTORY = ("storage", "app", "private", "epostman-sandbox")
FILENAME = ".env.credentials.json"


class CaptureError(Exception):
    """A fixed, non-sensitive diagnostic for the operator."""


def private_directory(root: Path) -> int:
    """Traverse directory descriptors, refusing symlinks and unsafe final modes."""
    descriptor = os.open(root, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        for part in DIRECTORY:
            try:
                os.mkdir(part, 0o700, dir_fd=descriptor)
            except FileExistsError:
                pass
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=descriptor)
            os.close(descriptor)
            descriptor = child
        metadata = os.fstat(descriptor)
        if metadata.st_uid != os.geteuid() or stat.S_IMODE(metadata.st_mode) != 0o700:
            raise CaptureError("Sandbox credential directory must be owned by you with mode 0700.")
        return descriptor
    except BaseException:
        os.close(descriptor)
        raise


def capture(root: Path, read_secret=getpass.getpass) -> None:
    """Create one exclusive owner-only file; never replace an existing credential."""
    descriptor = private_directory(root)
    try:
        try:
            os.stat(FILENAME, dir_fd=descriptor, follow_symlinks=False)
        except FileNotFoundError:
            pass
        else:
            raise CaptureError("A sandbox credential file already exists; it was not changed.")

        with warnings.catch_warnings():
            # A TTY may still fail echo control; never allow getpass's fallback reader.
            warnings.simplefilter("error", getpass.GetPassWarning)
            values = {
                "client_id": read_secret("Sandbox SAPI client ID (hidden): ").strip(),
                "client_secret": read_secret("New sandbox SAPI client secret (hidden): ").strip(),
                "participant_id": read_secret("Sandbox Peppol participant ID, 0245: followed by DIČ (hidden): ").strip(),
            }
        if not all(isinstance(value, str) and value and not any(ord(c) < 32 or ord(c) == 127 for c in value) for value in values.values()):
            raise CaptureError("Invalid sandbox input; no credential file was written.")
        if len(values["client_id"]) > 256 or len(values["client_secret"]) > 4096 or not re.fullmatch(r"0245:[0-9]{10}", values["participant_id"]):
            raise CaptureError("Invalid sandbox input; check the SAPI fields and participant format.")
        serialized = json.dumps(values, ensure_ascii=True) + "\n"
        if len(serialized.encode("utf-8")) > 16384:
            raise CaptureError("Sandbox input exceeds the protected credential file size limit.")

        output = os.open(FILENAME, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=descriptor)
        try:
            with os.fdopen(output, "w", encoding="utf-8") as stream:
                os.fchmod(stream.fileno(), 0o600)
                stream.write(serialized)
                stream.flush()
                os.fsync(stream.fileno())
        except BaseException:
            os.unlink(FILENAME, dir_fd=descriptor)
            raise
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def main() -> int:
    if len(sys.argv) != 1:
        print("Run without arguments from the Satflux repository root. Secrets are entered interactively.", file=sys.stderr)
        return 2
    if not sys.stdin.isatty() or not sys.stderr.isatty():
        print("A real interactive terminal is required; refusing echoed or redirected secret input.", file=sys.stderr)
        return 2
    root = Path.cwd()
    if not (root / "artisan").is_file():
        print("Run this command from the Satflux repository root.", file=sys.stderr)
        return 2
    print("Use credentials from testpostman.slovakodata.com only. Nothing is sent to the provider by this command.")
    try:
        capture(root)
    except CaptureError as exception:
        print(str(exception), file=sys.stderr)
        return 1
    except (KeyboardInterrupt, EOFError):
        print("Cancelled; credentials were not printed.", file=sys.stderr)
        return 1
    except Exception:
        print("Unable to save sandbox credentials safely; no exception details or input values were printed.", file=sys.stderr)
        return 1
    print("Sandbox credentials saved in a private 0600 file. Tell Codex 'sandbox ready'; do not paste the keys.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
