#!/usr/bin/env python3
"""Pin Symfony application components, excluding contracts/polyfills and tool-only dependencies."""
import argparse
import json
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('series', choices=['7.4', '8.1', '8.2-dev', '8.2'])
args = parser.parse_args()
p = Path('composer.json')
c = json.loads(p.read_text())
version = '8.2.x-dev' if args.series == '8.2-dev' else args.series + '.*'
components = {'cache', 'clock', 'config', 'console', 'dependency-injection', 'error-handler', 'event-dispatcher', 'filesystem', 'finder', 'framework-bundle', 'http-foundation', 'http-kernel', 'phpunit-bridge', 'process', 'property-access', 'property-info', 'routing', 'serializer', 'string', 'type-info', 'uid', 'validator', 'var-dumper', 'var-exporter', 'yaml'}
for section in ['require', 'require-dev']:
    for name in c[section]:
        if name.startswith('symfony/') and name.split('/')[1] in components:
            c[section][name] = version
for component in sorted(components):
    name = 'symfony/' + component
    if name not in c['require']:
        c['require-dev'][name] = version
if args.series.startswith('8.'):
    c['require-dev']['doctrine/dbal'] = '^4.3'
else:
    c['require-dev']['doctrine/dbal'] = '^3.8'
if args.series == '8.2-dev':
    # Contracts may release independently of the components during forward development.
    for package in ['cache', 'event-dispatcher', 'service', 'translation', 'deprecation']:
        c['require-dev']['symfony/' + package + '-contracts'] = '^3.0@dev'
c['require-dev'] = dict(sorted(c['require-dev'].items()))
p.write_text(json.dumps(c, indent=2) + '\n')
print('Compatibility fixture pinned to Symfony ' + version + '; PHP platform remains real.')
