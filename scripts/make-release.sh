#!/usr/bin/env bash
# Builds menu-release.tar.gz for scripts/deploy-vps.sh (or to unpack on cPanel):
# the backend with production packages and the built admin app, nothing secret.
# Run on your computer from the repository root: bash scripts/make-release.sh
set -euo pipefail
cd "$(dirname "$0")/.."
root=$(pwd)
out=${1:-$root/menu-release.tar.gz}
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

echo "== Admin app (frontend)"
(cd frontend && pnpm install --frozen-lockfile && pnpm build)

echo "== Backend copy without dev files"
git archive --format=tar HEAD backend | tar -x -C "$work"
cp -a backend/public/app "$work/backend/public/app"
(cd "$work/backend" && composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress)
# A package installed from source brings its .git folder: never ship those.
find "$work/backend/vendor" -name .git -type d -prune -exec rm -rf {} +
rm -rf "$work/backend/tests" "$work/backend/.env" "$work/backend/node_modules" "$work/backend/storage/logs/"*.log

echo "== Pack"
tar -czf "$out" -C "$work/backend" .
echo "$out ($(du -h "$out" | cut -f1))"
