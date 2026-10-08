#!/usr/bin/env python3
"""Syntax-check source, runtime config and every fixture, including unused doubles."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import subprocess
import sys

paths = sorted(p for directory in ['src', 'config', 'tests', 'scripts', 'stubs'] for p in Path(directory).rglob('*.php'))
def check(path):
    result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
    return (str(path), result.stdout + result.stderr) if result.returncode else None
with ThreadPoolExecutor(max_workers=4) as executor:
    errors = [result for result in executor.map(check, paths) if result]
for path, output in errors:
    print(path + ': ' + output, file=sys.stderr)
print(f'PHP syntax: {len(paths)} files, {len(errors)} errors.')
sys.exit(bool(errors))
