"""Regression checks for project provenance and bounded checkout validation."""
import copy
import io
import runpy
import subprocess
import sys
import unittest
from contextlib import redirect_stderr
from pathlib import Path
from unittest.mock import patch

import validate
from probe_codex import ROOT, require_project_settings


class ProjectProvenanceTests(unittest.TestCase):
    """Separate matching effective values from trusted checkout-layer provenance."""

    def result(self):
        """Build synthetic config/read metadata from the expected project layer."""
        keys = ('agents.enabled', 'agents.max_concurrent_threads_per_session',
                'features.multi_agent')
        return {'config': {'agents': {'enabled': True, 'max_concurrent_threads_per_session': 3},
                           'features': {'multi_agent': True}},
                'origins': {key: {'name': {'type': 'project', 'dotCodexFolder': str(ROOT / '.codex')}}
                            for key in keys}}

    def test_checkout_project_origin_passes(self):
        """Accept correct values only when all three settings come from this checkout."""
        require_project_settings(self.result(), ROOT)

    def test_other_origins_fail_even_with_matching_values(self):
        """Reject global/session/other-project/missing provenance for each setting."""
        for key in self.result()['origins']:
            for source in ({'type': 'user'}, {'type': 'sessionFlags'}, {},
                           {'type': 'project', 'dotCodexFolder': str(ROOT / 'workspace/.codex')}):
                with self.subTest(key=key, source=source):
                    result = copy.deepcopy(self.result())
                    result['origins'][key]['name'] = source
                    with self.assertRaisesRegex(RuntimeError, 'does not originate'):
                        require_project_settings(result, ROOT)

    def test_bad_values_fail_despite_matching_origin(self):
        """Keep effective-setting validation in addition to provenance checks."""
        result = self.result()
        result['config']['agents']['max_concurrent_threads_per_session'] = 4
        with self.assertRaisesRegex(RuntimeError, 'concurrency'):
            require_project_settings(result, ROOT)


class CheckoutValidationTests(unittest.TestCase):
    """Prevent nested-path acceptance and indefinite Git subprocess waits."""

    def test_nested_directory_is_rejected_before_remote_read(self):
        """A matching parent origin must not validate a selected nested directory."""
        project = {'id': 'satflux', 'repository_url': 'https://github.com/webiumsk/satflux',
                   'local_path': {'environment_variable': 'SATFLUX_ROOT'}}
        with patch.object(validate, 'resolve_project', return_value=ROOT / 'workspace'), \
                patch.object(validate, 'read_git', return_value=str(ROOT)) as git:
            with self.assertRaisesRegex(ValueError, 'not the checkout root'):
                validate.check_projects([project])
            git.assert_called_once_with(ROOT / 'workspace', 'rev-parse', '--show-toplevel')

    def test_git_read_is_bounded(self):
        """Every metadata command uses the timeout and shell-free argument list."""
        with patch.object(validate.subprocess, 'check_output', return_value='main\n') as command:
            self.assertEqual(validate.read_git(ROOT, 'branch', '--show-current'), 'main')
            self.assertEqual(command.call_args.kwargs['timeout'], 10)
            self.assertIsInstance(command.call_args.args[0], list)

    def test_git_timeout_is_cli_failure(self):
        """Surface timeout as exit one with a clear diagnostic, not a traceback/pass."""
        error = subprocess.TimeoutExpired(['git'], 10)
        output = io.StringIO()
        with patch.object(sys, 'argv', ['validate.py', '--check-projects']), \
                patch('subprocess.check_output', side_effect=error), redirect_stderr(output):
            with self.assertRaises(SystemExit) as exit_status:
                runpy.run_path(str(Path(validate.__file__)), run_name='__main__')
        self.assertEqual(exit_status.exception.code, 1)
        self.assertIn('10-second timeout', output.getvalue())


if __name__ == '__main__':
    unittest.main()
