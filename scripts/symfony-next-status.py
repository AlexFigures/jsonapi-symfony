#!/usr/bin/env python3
"""A published stable Symfony 8.2 makes the forward CI lane mandatory."""
import json
import re
from urllib.request import Request, urlopen
# Packagist is Composer's authoritative published-version index; the Symfony
# website can reject automated clients. Failed metadata access is a failure.
request = Request('https://repo.packagist.org/p2/symfony/framework-bundle.json', headers={'User-Agent': 'JsonApiBundle-compatibility-CI/1.0'})
with urlopen(request, timeout=30) as response:
    versions = json.load(response)['packages']['symfony/framework-bundle']
stable = any(re.fullmatch(r'v?8\.2\.\d+', version['version']) for version in versions)
print('stable=' + str(stable).lower())
print('series=' + ('8.2' if stable else '8.2-dev'))
