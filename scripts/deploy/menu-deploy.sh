#!/usr/bin/env bash
# Runs ON THE SERVER. Started by scripts/deploy/deploy.ps1 (the VS Code "Deploy" button), which
# uploads it to ~/menu-deploy.sh together with the built admin app (~/menu-app.tgz).
#
#   bash ~/menu-deploy.sh <app folder> <branch> <php> <composer>
#
# The app folder is the git clone (it contains backend/ and frontend/). Keeps .env, storage/,
# public/media/ and the saved menu pages; the site is in maintenance mode only for the few
# seconds of the update and comes back up even when a step fails.
set -euo pipefail

APP_DIR="${1/#\~/$HOME}"
BRANCH="$2"
PHP="$3"
COMPOSER="$4"
ARCHIVE="$HOME/menu-app.tgz"

step() { printf '\n== %s\n' "$*"; }
fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

[ -d "$APP_DIR/.git" ] || fail "No git clone in $APP_DIR. Do the one-time server set-up first (docs/deploy.md, section 3a)."
[ -f "$APP_DIR/backend/.env" ] || fail "No backend/.env in $APP_DIR. Create it first (docs/deploy.md, section 3.4)."
[ -f "$ARCHIVE" ] || fail "The admin app ($ARCHIVE) was not uploaded."

cd "$APP_DIR"
step "Code: $BRANCH from GitHub"
git fetch --quiet origin
git checkout --quiet "$BRANCH"
git pull --ff-only --quiet origin "$BRANCH"
git log --oneline -1

cd backend
step "Maintenance mode on"
"$PHP" artisan down --retry=15 >/dev/null 2>&1 || true
# Whatever happens next, the site comes back up.
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

step "PHP packages"
# shellcheck disable=SC2086 # COMPOSER may be "php /path/composer.phar"
$COMPOSER install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress

step "Admin app"
rm -rf public/app.new public/app.old
mkdir public/app.new
tar -xzf "$ARCHIVE" -C public/app.new --strip-components=1
[ -f public/app.new/index.html ] || fail "The uploaded admin app has no index.html."
if [ -d public/app ]; then mv public/app public/app.old; fi
mv public/app.new public/app
rm -rf public/app.old "$ARCHIVE"

step "Database"
"$PHP" artisan migrate --force

step "Caches"
"$PHP" artisan optimize

step "Menu pages (rebuilt with the new design on the next visit)"
"$PHP" artisan menu:refresh-pages --all

"$PHP" artisan up
trap - EXIT
step "Live: $(git log --oneline -1)"
