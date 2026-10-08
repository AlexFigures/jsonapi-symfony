#!/usr/bin/env python3
"""Check local Markdown link targets throughout the maintained documentation."""

import argparse
import re
import sys
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('paths', nargs='*', help='Repository-relative Markdown files')
    args = parser.parse_args()
    paths = args.paths or sorted(
        str(page.relative_to(ROOT))
        for page in [*ROOT.glob('*.md'), *(ROOT / 'docs').rglob('*.md')]
    )
    errors = []
    checked = 0
    for relative in paths:
        page = ROOT / relative
        if not page.is_file():
            errors.append(f'{relative}: missing maintained page')
            continue
        fenced = False
        fence_character = None
        for number, line in enumerate(page.read_text().splitlines(), 1):
            fence = re.match(r'^\s*(`{3,}|~{3,})', line)
            if fence:
                character = fence.group(1)[0]
                if not fenced:
                    fenced, fence_character = True, character
                elif character == fence_character:
                    fenced = False
                continue
            if fenced:
                continue
            # Inline Markdown links/images, including angle-bracket destinations.
            for match in re.finditer(r'!?\[[^\]\n]+\]\((<[^>]+>|[^\s)]+)(?:\s+"[^"]*")?\)', line):
                target = match.group(1).strip('<>')
                parsed = urlsplit(target)
                if parsed.scheme or parsed.netloc or not parsed.path:
                    continue
                checked += 1
                destination = ROOT / unquote(parsed.path.lstrip('/')) if parsed.path.startswith('/') else page.parent / unquote(parsed.path)
                if not destination.exists():
                    errors.append(f'{relative}:{number}: missing target {target}')
    if errors:
        print('\n'.join(errors), file=sys.stderr)
        return 1
    print(f'Documentation links: {len(paths)} pages, {checked} local targets checked.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
