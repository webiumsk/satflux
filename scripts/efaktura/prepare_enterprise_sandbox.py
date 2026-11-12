#!/usr/bin/env python3
"""Capture one TEST Enterprise API key locally without arguments, echo or network."""

import getpass
import json
import os
from pathlib import Path
import re
import stat
import sys
import warnings

DIRECTORY = ("storage", "app", "private", "epostman-enterprise-sandbox")
FILENAME = ".env.credentials.json"
SANDBOX_ORIGIN = "https://testpostman.slovakodata.com"


class CaptureError(Exception):
    """A fixed, non-sensitive diagnostic for the operator."""


def private_directory(root: Path) -> int:
    """Traverse directory descriptors, refusing symlinks and unsafe final modes."""
    descriptor = os.open(root, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        for index, part in enumerate(DIRECTORY):
            created = False
            try:
                os.mkdir(part, 0o700, dir_fd=descriptor)
                created = True
            except FileExistsError:
                pass
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=descriptor)
            os.close(descriptor)
            descriptor = child
            if created and index == len(DIRECTORY) - 1:
                if os.fstat(descriptor).st_uid != os.geteuid():
                    raise CaptureError("New sandbox credential directory must be owned by you.")
                # mkdir inherits setgid from the parent even with mode 0700.
                # Tighten only our new leaf; existing directories still fail closed.
                os.fchmod(descriptor, 0o700)
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
            api_key = read_secret("TEST Enterprise API key (hidden): ")
        if not isinstance(api_key, str) or not api_key or len(api_key) > 4096:
            raise CaptureError("Invalid TEST Enterprise API key; no credential file was written.")
        if any(character.isspace() or ord(character) < 32 or ord(character) == 127 for character in api_key):
            raise CaptureError("The TEST Enterprise API key must not contain whitespace or control characters.")
        values = {"api_key": api_key}
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
            os.fsync(descriptor)
        except BaseException:
            os.unlink(FILENAME, dir_fd=descriptor)
            raise
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
    print("Use the Enterprise API key from testpostman.slovakodata.com only. Nothing is sent to the provider by this command.")
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
    print("TEST Enterprise key saved in a separate private 0600 file. Tell Codex 'enterprise sandbox ready'; do not paste the key.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
