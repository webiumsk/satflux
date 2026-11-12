"""Offline safety regressions; every key here is synthetic."""

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

import prepare_enterprise_sandbox as helper


class EnterpriseCaptureTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        self.target = self.root.joinpath(*helper.DIRECTORY, helper.FILENAME)

    def capture(self, value="synthetic-enterprise-key-sentinel"):
        helper.capture(self.root, lambda prompt: value)

    def test_captures_only_key_in_separate_owner_private_file(self):
        self.capture()
        self.assertEqual(json.loads(self.target.read_text()), {"api_key": "synthetic-enterprise-key-sentinel"})
        self.assertEqual(stat.S_IMODE(self.target.stat().st_mode), 0o600)
        self.assertEqual(stat.S_IMODE(self.target.parent.stat().st_mode), 0o700)
        self.assertEqual(self.target.stat().st_uid, os.geteuid())
        self.assertEqual(helper.DIRECTORY[-1], "epostman-enterprise-sandbox")
        self.assertFalse(self.root.joinpath("storage", "app", "private", "epostman-sandbox").exists())
        self.assertEqual(helper.SANDBOX_ORIGIN, "https://testpostman.slovakodata.com")

    def test_new_directory_drops_inherited_setgid_preserving_parent(self):
        parent = self.target.parent.parent
        parent.mkdir(parents=True)
        parent.chmod(0o2700)
        self.capture()
        self.assertEqual(stat.S_IMODE(self.target.parent.stat().st_mode), 0o700)
        self.assertEqual(stat.S_IMODE(parent.stat().st_mode), 0o2700)

    def test_existing_file_is_not_overwritten_or_prompted(self):
        self.capture()
        original = self.target.read_bytes()
        with self.assertRaises(helper.CaptureError):
            helper.capture(self.root, lambda prompt: self.fail("Must reject before prompting"))
        self.assertEqual(self.target.read_bytes(), original)

    def test_symlink_file_is_not_followed(self):
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

    def test_unsafe_existing_directory_is_rejected_not_repaired(self):
        descriptor = helper.private_directory(self.root)
        os.close(descriptor)
        self.target.parent.chmod(0o755)
        with self.assertRaises(helper.CaptureError):
            self.capture()
        self.assertEqual(stat.S_IMODE(self.target.parent.stat().st_mode), 0o755)

    def test_whitespace_controls_empty_nonstring_and_length_are_rejected(self):
        for value in ["", " ", " key", "key ", "key\tvalue", "key\nvalue", "key\r", "key\x00", "key\x7f", "key\u00a0value", "x" * 4097, None, b"bytes"]:
            with self.subTest(value_type=type(value).__name__, length=len(value) if value is not None else 0):
                with self.assertRaises(helper.CaptureError):
                    self.capture(value)
                self.assertFalse(self.target.exists())

    def test_encoded_file_size_is_bounded(self):
        with self.assertRaises(helper.CaptureError):
            self.capture("\u017e" * 4096)
        self.assertFalse(self.target.exists())

    def test_cancellation_creates_no_file(self):
        def cancel(prompt):
            raise KeyboardInterrupt()
        with self.assertRaises(KeyboardInterrupt):
            helper.capture(self.root, cancel)
        self.assertFalse(self.target.exists())

    def test_getpass_echo_fallback_is_fatal(self):
        def fallback(prompt):
            warnings.warn("Synthetic terminal echo failure", helper.getpass.GetPassWarning)
            self.fail("An echoed fallback must never run")
        with self.assertRaises(helper.getpass.GetPassWarning):
            helper.capture(self.root, fallback)
        self.assertFalse(self.target.exists())

    def test_non_tty_rejects_before_capture(self):
        with patch.object(helper.sys, "argv", ["prepare_enterprise_sandbox.py"]), patch.object(helper.sys.stdin, "isatty", return_value=False), patch.object(helper, "capture") as capture, redirect_stderr(StringIO()):
            self.assertEqual(helper.main(), 2)
            capture.assert_not_called()

    def test_arguments_reject_before_capture(self):
        with patch.object(helper.sys, "argv", ["prepare_enterprise_sandbox.py", "synthetic-forbidden-key"]), patch.object(helper, "capture") as capture, redirect_stderr(StringIO()) as output:
            self.assertEqual(helper.main(), 2)
            capture.assert_not_called()
        self.assertNotIn("synthetic-forbidden-key", output.getvalue())

    def test_input_is_never_echoed(self):
        output = StringIO()
        with redirect_stdout(output), redirect_stderr(output):
            self.capture()
        self.assertEqual(output.getvalue(), "")

    def test_failed_file_sync_removes_only_new_file(self):
        with patch.object(helper.os, "fsync", side_effect=OSError("Synthetic disk failure")), self.assertRaises(OSError):
            self.capture()
        self.assertFalse(self.target.exists())

    def test_failed_directory_sync_removes_new_file_and_retry_succeeds(self):
        with patch.object(helper.os, "fsync", side_effect=[None, OSError("Synthetic directory sync failure")]) as sync, self.assertRaises(OSError):
            self.capture()
        self.assertEqual(sync.call_count, 2)
        self.assertFalse(self.target.exists())
        self.capture()
        self.assertTrue(self.target.is_file())

    def test_exclusive_create_race_never_overwrites_competitor(self):
        def competing_writer(prompt):
            self.target.write_text("other-process-key")
            return "synthetic-enterprise-key-sentinel"
        with self.assertRaises(FileExistsError):
            helper.capture(self.root, competing_writer)
        self.assertEqual(self.target.read_text(), "other-process-key")


if __name__ == "__main__":
    unittest.main()
