#!/bin/sh
set -eu
baseline=${BC_BASELINE:-1.0.0}
if ! git rev-parse --verify "$baseline^{commit}" >/dev/null 2>&1; then
    if [ "$baseline" != 1.0.0 ]; then
        echo "Explicit BC baseline does not exist: $baseline" >&2
        exit 1
    fi
    echo 'No 1.0.0 baseline yet: candidate freeze only. BC smoke remains required.'
    exit 0
fi
exec tools/bc/vendor/bin/roave-backward-compatibility-check --from="$baseline" --to="${BC_TARGET:-HEAD}" --install-development-dependencies
