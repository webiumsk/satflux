#!/usr/bin/env python3
"""Probe installed CLI configuration/discovery; no model turn or global edits."""
import hashlib
import json
import os
import selectors
import subprocess
import tempfile
import time
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def require(condition, message):
    """Reject invalid probe evidence even when Python optimization is enabled."""
    if not condition:
        raise RuntimeError(message)


def stop(process):
    """Bound subprocess shutdown and close all transport pipes."""
    if process.poll() is None:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=5)
    for stream in (process.stdin, process.stdout, process.stderr):
        if stream is not None and not stream.closed:
            stream.close()


class Probe:
    """Bounded unbuffered JSON-lines transport; drain diagnostics without exposing them."""
    def __init__(self, command, environment):
        """Start an unbuffered child transport with separate diagnostic draining."""
        self.process = subprocess.Popen(command, cwd=ROOT, env=environment,
                                        stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                        stderr=subprocess.PIPE, bufsize=0)
        self.selector = selectors.DefaultSelector()
        self.selector.register(self.process.stdout, selectors.EVENT_READ, 'stdout')
        self.selector.register(self.process.stderr, selectors.EVENT_READ, 'stderr')
        self.pending = b''

    def send(self, request):
        """Write one JSON-lines request to the existing child process."""
        self.process.stdin.write((json.dumps(request) + '\n').encode())

    def rpc(self, request, timeout=15):
        """Await a matching RPC response within a bounded framing/read deadline."""
        self.send(request)
        deadline = time.monotonic() + timeout
        while time.monotonic() < deadline:
            while b'\n' in self.pending:
                line, self.pending = self.pending.split(b'\n', 1)
                if not line:
                    continue
                value = json.loads(line)
                if value.get('id') == request['id']:
                    require('error' not in value,
                            f"{request['method']}: RPC error; raw configuration omitted")
                    return value['result']
            for key, _ in self.selector.select(timeout=min(1, max(0, deadline - time.monotonic()))):
                chunk = os.read(key.fileobj.fileno(), 65536)
                if not chunk:
                    self.selector.unregister(key.fileobj)
                    continue
                if key.data == 'stdout':
                    self.pending += chunk
                    require(len(self.pending) < 8_000_000, 'Oversized protocol frame')
                # stderr drained and discarded: no credentials/config/prompt logging.
            require(bool(self.selector.get_map()), f"{request['method']}: process ended before response")
        raise RuntimeError(f"{request['method']}: timed out")

    def close(self):
        """Release selector resources and stop the probe subprocess."""
        self.selector.close()
        stop(self.process)


def sensitive_configuration_hashes():
    """Fingerprint existing user settings/auth without exposing their contents."""
    # Never change HOME/CODEX_HOME. Fingerprint existing files without logging contents.
    user_directory = Path(os.environ.get('CODEX_HOME', str(Path.home() / '.codex')))
    return {name: hashlib.sha256((user_directory / name).read_bytes()).hexdigest()
            if (user_directory / name).is_file() else None
            for name in ('config.toml', 'auth.json', 'AGENTS.md', 'AGENTS.override.md')}


def require_project_settings(result, root):
    """Require matching settings originating in this checkout's project layer."""
    config = result['config']
    require(config['agents']['enabled'] is True, 'Agents not enabled')
    require(config['agents']['max_concurrent_threads_per_session'] == 3,
            'Unexpected project concurrency limit')
    require(config['features']['multi_agent'] is True, 'Multi-agent feature not active')
    for key in ('agents.enabled', 'agents.max_concurrent_threads_per_session',
                'features.multi_agent'):
        source = result.get('origins', {}).get(key, {}).get('name', {})
        folder = source.get('dotCodexFolder')
        require(source.get('type') == 'project' and isinstance(folder, str)
                and Path(folder).resolve() == (root / '.codex').resolve(),
                f'{key}: effective setting does not originate in this checkout project layer')


def main():
    """Probe installed configuration/discovery without model turns or global edits."""
    require(Path.cwd() == ROOT, 'Run from existing Satflux root; no cwd changes are made.')
    before = sensitive_configuration_hashes()
    environment = {k: v for k, v in os.environ.items()
                   if k not in {'OPENAI_API_KEY', 'CODEX_API_KEY'}}
    # Keep existing home/trust/auth untouched. Redirect only child diagnostic/state paths.
    with tempfile.TemporaryDirectory(prefix='satflux-codex-probe-') as directory:
        options = ['-c', 'log_dir=' + json.dumps(directory),
                   '-c', 'sqlite_home=' + json.dumps(directory),
                   '-c', 'analytics.enabled=false']
        probe = Probe(['codex', '--strict-config', *options, 'app-server', '--stdio'], environment)
        try:
            probe.rpc({'id': 1, 'method': 'initialize', 'params': {
                'clientInfo': {'name': 'satflux-orchestration-probe', 'version': '1'},
                'capabilities': {'experimentalApi': True}}})
            probe.send({'method': 'initialized', 'params': {}})
            result = probe.rpc({'id': 2, 'method': 'config/read', 'params': {
                'cwd': str(ROOT), 'includeLayers': True}})
            require_project_settings(result, ROOT)
            result = probe.rpc({'id': 3, 'method': 'skills/list', 'params': {
                'cwds': [str(ROOT)], 'forceReload': True}})
            entry = next(x for x in result['data'] if x['cwd'] == str(ROOT))
            expected = {p.parent.name for p in (ROOT / '.agents/skills').glob('*/SKILL.md')}
            actual = {x['name'] for x in entry['skills'] if x.get('enabled', True)}
            require(expected <= actual, f'Missing skills: {sorted(expected - actual)}')
            require(not entry.get('errors'), 'Skill discovery errors; raw paths omitted')
            print(f'PASS: installed CLI strict project config and {len(expected)} enabled project skills')
        finally:
            probe.close()

        negative = subprocess.run(['codex', '--strict-config', *options, '-c',
            'satflux_unknown_schema_probe=true', 'app-server', '--stdio'], cwd=ROOT,
            env=environment, input='', capture_output=True, text=True, timeout=15)
        require(negative.returncode != 0 and 'satflux_unknown_schema_probe' in negative.stderr,
                'Strict parser did not reject unsupported configuration')
        print('PASS: strict parser rejects unsupported configuration key')

        # CLI 0.162.1 debug does not accept --strict-config; config was checked above.
        prompt = subprocess.run(['codex', *options, 'debug', 'prompt-input'], cwd=ROOT,
                                env=environment, capture_output=True, text=True, timeout=30)
        require(prompt.returncode == 0, 'CLI instruction discovery failed; raw prompt omitted')
        require('Satflux development agents' in json.dumps(json.loads(prompt.stdout)),
                'Root AGENTS.md not discovered')
        print('PASS: installed CLI discovers root AGENTS.md without a model request')

        for path in sorted((ROOT / '.codex/agents').glob('*.toml')):
            role = tomllib.loads(path.read_text())
            # name/description are standalone-agent metadata, not root config keys.
            # Validate only supported session settings with the installed strict parser.
            overrides = []
            for key in ('developer_instructions', 'sandbox_mode'):
                if key in role:
                    overrides.extend(['-c', key + '=' + json.dumps(role[key])])
            check = subprocess.run(['codex', '--strict-config', *options, *overrides,
                                    'app-server', '--stdio'], cwd=ROOT, env=environment,
                                   input='', capture_output=True, text=True, timeout=15)
            require(check.returncode == 0, f'{path.stem}: strict CLI rejects session settings')
        print('PASS: installed CLI accepts session settings for all ten role definitions')
        print('NOTE: standalone metadata is statically validated; native named-role routing/hot reload is not exercised.')
    require(before == sensitive_configuration_hashes(), 'User configuration/credentials changed during probe')
    print('PASS: user configuration/credentials unchanged; no model thread created')


if __name__ == '__main__':
    main()
