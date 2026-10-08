#!/usr/bin/env python3
"""Verify the reviewed 1.0 API classification and emit the source inventory."""

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', default='reports/public-api-inventory.json')
    args = parser.parse_args()
    manifest = json.loads((ROOT / 'docs/api/public-api-manifest.json').read_text())['symbols']
    symbols = []
    seen = set()
    for path in sorted((ROOT / 'src').rglob('*.php')):
        content = path.read_text()
        namespace = re.search(r'^namespace\s+([^;]+);', content, re.MULTILINE)
        declaration = re.search(
            r'^(?:(?:final|abstract|readonly)\s+)*(class|interface|enum|trait)\s+'
            + re.escape(path.stem) + r'\b', content, re.MULTILINE,
        )
        if not namespace or not declaration:
            raise ValueError(f'Source requires inventory review: {path.relative_to(ROOT)}')
        key = path.relative_to(ROOT).as_posix()
        if key not in manifest:
            raise ValueError(f'Unclassified source: {key}')
        seen.add(key)
        decision = manifest[key]
        if decision['classification'] not in ['PUBLIC', 'INTERNAL', 'DEPRECATED_BEFORE_1_0']:
            raise ValueError(f'Invalid classification: {key}')
        declaration_comments = re.findall(r'/\*\*[\s\S]*?\*/', content[:declaration.start()])
        class_comment = declaration_comments[-1] if declaration_comments else ''
        expected_marker = 'internal' if decision['classification'] == 'INTERNAL' else 'api'
        if not re.search(r'@' + expected_marker + r'\b', class_comment):
            raise ValueError(f'Class annotation disagrees with manifest: {key}')
        annotations = sorted(set(re.findall(r'@(api|internal)\b', content)))
        symbols.append({
            'symbol': namespace.group(1) + '\\' + path.stem,
            'kind': declaration.group(1),
            'path': path.relative_to(ROOT).as_posix(),
            'annotation_occurrences': annotations,
            'classification': decision['classification'],
            'rationale': decision['rationale'],
            'public_method_names': re.findall(
                r'^\s*public\s+(?:static\s+)?function\s+(&?\w+)\s*\(',
                content, re.MULTILINE,
            ),
        })
    if set(manifest) != seen:
        raise ValueError(f'Stale inventory entries: {set(manifest) - seen}')
    output = Path(args.output)
    if not output.is_absolute():
        output = ROOT / output
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({
        'schema_version': 1,
        'scope': 'Reviewed src classification; signature compatibility is checked by Roave against an explicit release baseline',
        'symbols': symbols,
    }, indent=2) + '\n')
    print(f'API inventory: {len(symbols)} symbols; all classifications reviewed. Output: {output}')


if __name__ == '__main__':
    main()
