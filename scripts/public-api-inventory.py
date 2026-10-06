#!/usr/bin/env python3
"""Emit an advisory source-symbol inventory without loading PHP/vendor services."""

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', default='reports/public-api-inventory.json')
    args = parser.parse_args()
    symbols = []
    for path in sorted((ROOT / 'src').rglob('*.php')):
        content = path.read_text()
        namespace = re.search(r'^namespace\s+([^;]+);', content, re.MULTILINE)
        declaration = re.search(
            r'^(?:(?:final|abstract|readonly)\s+)*(class|interface|enum|trait)\s+'
            + re.escape(path.stem) + r'\b', content, re.MULTILINE,
        )
        if not namespace or not declaration:
            raise ValueError(f'Source requires inventory review: {path.relative_to(ROOT)}')
        annotations = sorted(set(re.findall(r'@(api|internal)\b', content)))
        symbols.append({
            'symbol': namespace.group(1) + '\\' + path.stem,
            'kind': declaration.group(1),
            'path': path.relative_to(ROOT).as_posix(),
            'annotation_occurrences': annotations,
            'classification': 'REVIEW',
            'public_method_names': re.findall(
                r'^\s*public\s+(?:static\s+)?function\s+(&?\w+)\s*\(',
                content, re.MULTILINE,
            ),
        })
    output = Path(args.output)
    if not output.is_absolute():
        output = ROOT / output
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({
        'schema_version': 1,
        'scope': 'Declared src symbols; lexical advisory inventory, not a frozen API or BC baseline',
        'symbols': symbols,
    }, indent=2) + '\n')
    print(f'API inventory: {len(symbols)} symbols; classification pending. Output: {output}')


if __name__ == '__main__':
    main()
