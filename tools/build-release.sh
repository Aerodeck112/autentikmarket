#!/usr/bin/env bash
# ============================================================================
# Autentik Market — generare pachete de release (rulat pe mașina de DEZVOLTARE)
#
#   tools/build-release.sh 1.0.0 --install     → pachet de instalare inițială (cPanel)
#   tools/build-release.sh 1.1.0               → pachet de update pentru Update Manager
#   tools/build-release.sh 1.1.0 --sign-key-file ~/.autentik/update-signing.key
#
# Pașii: export curat din git (HEAD) → composer install --no-dev → npm ci + build
#        → php artisan release:build în directorul curat → copiere ZIP + inventar.
# Detalii: docs/RELEASE-PROCESS.md
# ============================================================================
set -euo pipefail

VERSION="${1:?Versiunea lipsește (ex. 1.1.0)}"
shift || true
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$ROOT/releases/build/$VERSION"

if [[ "$(tr -d '[:space:]' < "$ROOT/VERSION")" != "$VERSION" ]]; then
    echo "VERSION conține $(cat "$ROOT/VERSION"); actualizați-l la $VERSION și faceți commit înainte de build." >&2
    exit 1
fi
if [[ -n "$(git -C "$ROOT" status --porcelain -- app bootstrap config database lang public resources routes composer.json composer.lock package.json VERSION)" ]]; then
    echo "Există modificări necomise în cod. Faceți commit înainte de build (pachetul se generează din HEAD)." >&2
    exit 1
fi

echo "→ Export curat din git în $STAGE"
rm -rf "$STAGE" && mkdir -p "$STAGE"
git -C "$ROOT" archive HEAD | tar -x -C "$STAGE"
mkdir -p "$STAGE/releases/manifests"
cp -a "$ROOT/releases/manifests/." "$STAGE/releases/manifests/" 2>/dev/null || true

echo "→ composer install --no-dev"
( cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-progress --no-scripts )
find "$STAGE/vendor" -type d -name .git -prune -exec rm -rf {} +

echo "→ npm ci && npm run build"
( cd "$STAGE" && npm ci --no-audit --no-fund && npm run build && rm -rf node_modules )

echo "→ release:build"
cp "$STAGE/.env.example" "$STAGE/.env"
( cd "$STAGE" && php artisan key:generate --force -q && php artisan package:discover -q && php artisan release:build "$VERSION" "$@" )

mkdir -p "$ROOT/releases/dist"
cp "$STAGE"/releases/dist/*"$VERSION"*.zip "$ROOT/releases/dist/"
cp "$STAGE/releases/manifests/$VERSION.json" "$ROOT/releases/manifests/"
rm -rf "$STAGE"

echo "✔ Gata: releases/dist/ (ZIP) și releases/manifests/$VERSION.json (comiteți inventarul)."
ls -lh "$ROOT/releases/dist/"*"$VERSION"*.zip
