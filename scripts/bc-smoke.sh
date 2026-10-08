#!/bin/sh
set -eu
smoke_dir=$(mktemp -d)
trap 'rm -rf "$smoke_dir"' EXIT
cp -R src "$smoke_dir/src"
php -r '$c=json_decode(file_get_contents("composer.json"),true,flags:JSON_THROW_ON_ERROR); unset($c["autoload-dev"],$c["extra"]); echo json_encode($c,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);' > "$smoke_dir/composer.json"
git -C "$smoke_dir" init --quiet
git -C "$smoke_dir" -c user.name='BC smoke' -c user.email='bc-smoke@example.invalid' add .
git -C "$smoke_dir" -c user.name='BC smoke' -c user.email='bc-smoke@example.invalid' commit --quiet -m 'Smoke snapshot'
# Compare all source declarations against the identical snapshot: verifies tool execution,
# autoload/reflection and dependency installation, not release BC guarantees.
bc_binary="$PWD/tools/bc/vendor/bin/roave-backward-compatibility-check"
(cd "$smoke_dir" && "$bc_binary" --from=HEAD --to=HEAD --install-development-dependencies)
