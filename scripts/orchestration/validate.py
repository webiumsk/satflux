#!/usr/bin/env python3
"""Validate project orchestration without writes, network calls or dependencies."""
import argparse
import json
import os
import re
import subprocess
import sys
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ROLES = {'backend', 'frontend', 'designer', 'security', 'payments', 'wordpress',
         'btcpay-plugins', 'qa', 'devops', 'researcher'}
SKILLS = {'project-resume', 'architecture-review', 'security-review',
          'laravel-development', 'vue-development', 'ux-ui-review',
          'payments-and-entitlements', 'btcpay-plugin-development',
          'woocommerce-integration', 'integration-testing', 'deployment-readiness',
          'github-review-handoff', 'cross-repository-coordination'}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def normalize_remote(value):
    value = re.sub(r'^git@github\.com:', 'https://github.com/', value.strip())
    return value.removesuffix('.git').rstrip('/')


def resolve_project(project):
    spec = project['local_path']
    override = os.environ.get(spec['environment_variable'])
    target = Path(override or spec['fallback']).expanduser()
    return (target if target.is_absolute() else ROOT / target).resolve()


def check_projects(projects):
    for project in projects:
        target = resolve_project(project)
        require(target.is_dir(), f"{project['id']}: unresolved directory; set {project['local_path']['environment_variable']}")
        def git(*args):
            return subprocess.check_output(['git', '-C', str(target), *args],
                                           text=True, stderr=subprocess.PIPE).strip()
        require(normalize_remote(git('remote', 'get-url', 'origin')) == project['repository_url'],
                f"{project['id']}: origin does not match registry; do not use this checkout")
        print(f"RESOLVED {project['id']}: {git('branch', '--show-current')} @ {git('rev-parse', '--short', 'HEAD')}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check-projects', action='store_true',
                        help='Read local registered Git remotes/branches; never fetch or mutate.')
    args = parser.parse_args()
    require(Path.cwd() == ROOT, 'Run from the existing Satflux root; no cwd changes are made.')
    config = tomllib.loads((ROOT / '.codex/config.toml').read_text())
    require(set(config) == {'agents', 'features'}, 'Unexpected project overrides')
    require(config['agents'] == {'enabled': True, 'max_concurrent_threads_per_session': 3},
            'Expected three spawned threads plus main')
    require(config['features'] == {'multi_agent': True}, 'Expected supported multi-agent feature only')
    role_files = sorted((ROOT / '.codex/agents').glob('*.toml'))
    require({p.stem for p in role_files} == ROLES, 'Required role files missing or unexpected')
    names = []
    for path in role_files:
        role = tomllib.loads(path.read_text())
        require(set(role) <= {'name', 'description', 'developer_instructions', 'sandbox_mode'},
                f'{path.name}: unexpected override')
        for key in ('name', 'description', 'developer_instructions'):
            require(isinstance(role.get(key), str) and role[key].strip(), f'{path.name}: missing {key}')
        require(role['name'] == path.stem, f'{path.name}: name/filename mismatch')
        if role['name'] in {'security', 'qa', 'researcher'}:
            require(role.get('sandbox_mode') == 'read-only', f'{path.name}: review/research must default read-only')
        names.append(role['name'])
    require(len(names) == len(set(names)), 'Duplicate role names')
    skill_files = sorted((ROOT / '.agents/skills').glob('*/SKILL.md'))
    require({p.parent.name for p in skill_files} == SKILLS, 'Required skills missing or unexpected')
    for path in skill_files:
        text = path.read_text()
        front = re.match(r'^---\nname: ([a-z0-9-]+)\ndescription: ("[^\n]+")\n---\n', text)
        require(front is not None, f'{path.parent.name}: expected scalar YAML frontmatter')
        require(front[1] == path.parent.name, f'{path.parent.name}: name mismatch')
        description = json.loads(front[2])
        require(0 < len(description) <= 1024, f'{path.parent.name}: invalid description')
        for section in ('Required inputs', 'Repeatable workflow', 'Acceptance criteria',
                        'Expected outputs', 'Relevant tests', 'Security and permission boundaries'):
            require(f'## {section}\n' in text, f'{path.parent.name}: missing {section}')
    # JSON is the documented YAML 1.2 subset used by this portable registry.
    registry = json.loads((ROOT / 'workspace/projects.yaml').read_text())
    require(registry['schema_version'] == 1, 'Unsupported registry schema')
    projects = registry['projects']
    ids = [p['id'] for p in projects]
    require(len(ids) == len(set(ids)), 'Duplicate project IDs')
    require(len(projects) == 7, 'Expected seven discovered registry entries')
    fields = {'id', 'name', 'repository_url', 'kind', 'access_during_bootstrap',
              'local_path', 'technology_stack', 'relationship_to_satflux',
              'responsible_agent', 'key_integration_contracts', 'build_entry_points',
              'test_entry_points', 'deployment_release_dependencies'}
    for project in projects:
        require(set(project) == fields, f"{project.get('id')}: missing/unexpected registry fields")
        require(project['responsible_agent'] in ROLES, f"{project['id']}: unknown specialist")
        require(project['repository_url'].startswith('https://github.com/webiumsk/'),
                f"{project['id']}: canonical owned repository required")
        spec = project['local_path']
        require(not Path(spec['fallback']).is_absolute(), f"{project['id']}: absolute tracked path")
        require(spec['relative_to'] == 'satflux-root' and spec['verify_remote'] is True,
                f"{project['id']}: unsafe path policy")
        require(re.fullmatch(r'SATFLUX_[A-Z_]+', spec['environment_variable']) is not None,
                f"{project['id']}: invalid environment variable")
        expected = 'orchestration-only' if project['id'] in {'satflux', 'satflux-infrastructure'} else 'read-only'
        require(project['access_during_bootstrap'] == expected, f"{project['id']}: unsafe bootstrap access")
    markdown = [ROOT / 'AGENTS.md', *(ROOT / 'docs/development').glob('*.md'), *skill_files]
    require((ROOT / 'AGENTS.md').stat().st_size < 32768, 'Root instructions exceed default discovery budget')
    for path in markdown:
        text = path.read_text()
        require(re.search(r'/home/[^/\s]+/', text) is None and '\u2014' not in text,
                f'{path.relative_to(ROOT)}: user-specific path or forbidden typography')
        for target in re.findall(r'\]\(([^)]+)\)', text):
            if '://' in target or target.startswith('#'):
                continue
            target = target.split('#', 1)[0]
            require((path.parent / target).exists(), f'{path.relative_to(ROOT)}: broken link {target}')
    if args.check_projects:
        check_projects(projects)
    print(f'PASS: config, {len(role_files)} roles, {len(skill_files)} skills, {len(projects)} projects and local documentation links')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, json.JSONDecodeError, subprocess.CalledProcessError) as error:
        print(f'FAIL: {error}', file=sys.stderr)
        sys.exit(1)
