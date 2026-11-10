"""Offline safety regressions; every credential here is synthetic."""

from contextlib import redirect_stderr, redirect_stdout
from io import StringIO
import json
import os
from pathlib import Path
import stat
import tempfile
import unittest
import warnings
from unittest.mock import patch

import prepare_sandbox as helper


class CaptureTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        self.target = self.root.joinpath(*helper.DIRECTORY, helper.FILENAME)

    def capture(self, values=None):
        answers = iter(values or ["synthetic-client", "synthetic-secret-sentinel", "0245:1234567890"])
        helper.capture(self.root, lambda prompt: next(answers))

    def test_creates_only_owner_readable_credentials_under_private_directory(self):
        self.capture()
        self.assertEqual(stat.S_IMODE(self.target.stat().st_mode), 0o600)
        self.assertEqual(stat.S_IMODE(self.target.parent.stat().st_mode), 0o700)
        self.assertEqual(set(json.loads(self.target.read_text())), {"client_id", "client_secret", "participant_id"})

    def test_existing_credentials_are_never_overwritten_or_prompted(self):
        self.capture()
        original = self.target.read_bytes()
        with self.assertRaises(helper.CaptureError):
            helper.capture(self.root, lambda prompt: self.fail("Must reject before prompting"))
        self.assertEqual(self.target.read_bytes(), original)

    def test_symlink_target_is_rejected_without_touching_destination(self):
        descriptor = helper.private_directory(self.root)
        os.close(descriptor)
        other = self.root / "other"
        other.write_text("untouched")
        self.target.symlink_to(other)
        with self.assertRaises(helper.CaptureError):
            self.capture()
        self.assertEqual(other.read_text(), "untouched")

    def test_symlink_ancestor_is_not_followed(self):
        other = self.root / "other"
        other.mkdir()
        (self.root / "storage").symlink_to(other, target_is_directory=True)
        with self.assertRaises(OSError):
            self.capture()
        self.assertEqual(list(other.iterdir()), [])

    def test_unsafe_existing_directory_is_not_repaired(self):
        descriptor = helper.private_directory(self.root)
        os.close(descriptor)
        self.target.parent.chmod(0o755)
        with self.assertRaises(helper.CaptureError):
            self.capture()
        self.assertEqual(stat.S_IMODE(self.target.parent.stat().st_mode), 0o755)

    def test_invalid_participant_and_control_characters_do_not_create_file(self):
        for values in (["client", "sentinel", "wrong"], ["client", "sentinel\nunsafe", "0245:1234567890"]):
            with self.assertRaises(helper.CaptureError):
                self.capture(values)
            self.assertFalse(self.target.exists())

    def test_cancellation_creates_no_file(self):
        def cancel(prompt):
            raise KeyboardInterrupt()
        with self.assertRaises(KeyboardInterrupt):
            helper.capture(self.root, cancel)
        self.assertFalse(self.target.exists())

    def test_non_tty_rejected_before_prompt_or_filesystem(self):
        output = StringIO()
        with patch.object(helper.sys, "argv", ["prepare_sandbox.py"]), patch.object(helper.sys.stdin, "isatty", return_value=False), patch.object(helper, "capture") as capture, redirect_stderr(output):
            self.assertEqual(helper.main(), 2)
            capture.assert_not_called()
        self.assertNotIn("synthetic-secret-sentinel", output.getvalue())

    def test_no_input_values_are_echoed(self):
        output = StringIO()
        with redirect_stdout(output), redirect_stderr(output):
            self.capture()
        self.assertEqual(output.getvalue(), "")

    def test_getpass_echo_fallback_is_fatal_before_reading_input(self):
        def fallback(prompt):
            warnings.warn("Synthetic terminal echo failure", helper.getpass.GetPassWarning)
            self.fail("An echoed fallback reader must never run")
        with self.assertRaises(helper.getpass.GetPassWarning):
            helper.capture(self.root, fallback)
        self.assertFalse(self.target.exists())

    def test_encoded_file_size_is_bounded_before_write(self):
        with self.assertRaises(helper.CaptureError):
            self.capture(["client", "ž" * 4096, "0245:1234567890"])
        self.assertFalse(self.target.exists())

    def test_failed_write_removes_only_the_new_partial_file(self):
        with patch.object(helper.os, "fsync", side_effect=OSError("Synthetic disk failure")), self.assertRaises(OSError):
            self.capture()
        self.assertFalse(self.target.exists())


if __name__ == "__main__":
    unittest.main()
