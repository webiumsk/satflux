"""Exercise protocol framing/failure behavior with synthetic local subprocesses."""
import os
import sys
import time
import unittest

from probe_codex import Probe


class ProbeTransportTests(unittest.TestCase):
    def run_probe(self, source):
        probe = Probe([sys.executable, '-u', '-c', source], os.environ.copy())
        self.addCleanup(probe.close)
        return probe

    def test_fragmented_frames_and_buffered_notifications(self):
        probe = self.run_probe('''
import sys,time
sys.stdin.readline()
sys.stderr.write('synthetic diagnostic\\n'); sys.stderr.flush()
sys.stdout.write('{"method":"notification"}\\n{"id":1,"res'); sys.stdout.flush()
time.sleep(0.03)
sys.stdout.write('ult":{"ok":true}}\\n'); sys.stdout.flush()
sys.stdin.readline()
sys.stdout.write('{"method":"another"}\\n{"id":2,"result":{"next":true}}\\n'); sys.stdout.flush()
time.sleep(1)
''')
        self.assertEqual(probe.rpc({'id': 1, 'method': 'test'}, timeout=2), {'ok': True})
        self.assertEqual(probe.rpc({'id': 2, 'method': 'test'}, timeout=2), {'next': True})

    def test_partial_frame_has_bounded_timeout(self):
        probe = self.run_probe('''
import sys,time
sys.stdin.readline()
sys.stdout.write('{"id":1'); sys.stdout.flush()
time.sleep(5)
''')
        started = time.monotonic()
        with self.assertRaisesRegex(RuntimeError, 'timed out'):
            probe.rpc({'id': 1, 'method': 'test'}, timeout=0.25)
        self.assertLess(time.monotonic() - started, 2)

    def test_rpc_error_is_not_reported_as_pass(self):
        probe = self.run_probe('''
import sys,time
sys.stdin.readline()
sys.stdout.write('{"id":1,"error":{"message":"synthetic"}}\\n'); sys.stdout.flush()
time.sleep(1)
''')
        with self.assertRaisesRegex(RuntimeError, 'RPC error'):
            probe.rpc({'id': 1, 'method': 'test'}, timeout=2)


if __name__ == '__main__':
    unittest.main()
