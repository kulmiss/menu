#!/usr/bin/env bash
# Menu on a small VPS (Ubuntu 24.04), from zero (owner, 2026-11-19, QUESTIONS #233).
# Nginx + PHP 8.3-FPM + MySQL 8, one folder per release, atomic switch, rollback.
#
#   Build the release on your computer:   scripts/make-release.sh         → menu-release.tar.gz
#   Copy it and this script to the VPS:   scp menu-release.tar.gz scripts/deploy-vps.sh root@VPS:
#   First time:   sudo DOMAIN=menu.example.com EMAIL=you@example.com bash deploy-vps.sh install menu-release.tar.gz
#   New version:  sudo bash deploy-vps.sh update menu-release.tar.gz
#   Go back:      sudo bash deploy-vps.sh rollback
#   Check:        sudo bash deploy-vps.sh doctor
#
# Options (environment): DOMAIN (required for install), EMAIL (for the HTTPS certificate),
# NO_TLS=1 (no certificate, e.g. a test box), KEEP=3 (releases kept), APP_DIR=/var/www/menu.
# Never stores a password in this script; generated secrets go into /var/www/menu/shared/.env
# (mode 640) and are printed once at the end of "install" for your password manager.
set -euo pipefail
umask 022   # files 644, folders 755, whatever the login shell had

APP_DIR=${APP_DIR:-/var/www/menu}
KEEP=${KEEP:-3}
PHP=php8.3
WEB_USER=www-data
DB_NAME=${DB_NAME:-menu}
DB_USER=${DB_USER:-menu}

say() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
die() { printf '\033[31mError: %s\033[0m\n' "$*" >&2; exit 1; }
svc() { if [ -d /run/systemd/system ]; then systemctl "$1" "$2"; else service "$2" "$1"; fi; }
artisan() { (cd "$APP_DIR/current" && runuser -u "$WEB_USER" -- php artisan "$@"); }
secret() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | cut -c1-"${1:-32}"; }

[ "$(id -u)" = 0 ] || die "run as root (sudo bash $0 …)"

unpack() { # unpack <tar.gz> → prints the new release folder
  local tarball=$1 dir
  [ -f "$tarball" ] || die "release file not found: $tarball"
  dir="$APP_DIR/releases/$(date +%Y%m%d-%H%M%S)"
  mkdir -p "$dir"
  tar -xzf "$tarball" -C "$dir"
  chmod -R go-w "$dir"   # the code is read-only for everyone but root
  [ -f "$dir/artisan" ] && [ -d "$dir/vendor" ] && [ -f "$dir/public/app/index.html" ] \
    || die "not a release (needs artisan, vendor/ and public/app/): build it with scripts/make-release.sh"
  # Shared between releases: settings, uploads, logs, sessions, backups.
  rm -rf "$dir/storage" "$dir/public/media" "$dir/.env"
  ln -s "$APP_DIR/shared/storage" "$dir/storage"
  ln -s "$APP_DIR/shared/media" "$dir/public/media"
  ln -s "$APP_DIR/shared/.env" "$dir/.env"
  mkdir -p "$dir/public/menu-cache" "$dir/bootstrap/cache"
  chown -R "$WEB_USER:$WEB_USER" "$dir/bootstrap/cache" "$dir/public/menu-cache"
  echo "$dir"
}

switch_to() { # atomic: a new symlink renamed over the old one
  ln -sfn "$1" "$APP_DIR/current.new" && mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"
  svc reload "$PHP-fpm" || true
}

prune() {
  ls -1dt "$APP_DIR"/releases/* 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
    [ "$(readlink -f "$APP_DIR/current")" = "$(readlink -f "$old")" ] || rm -rf "$old"
  done
}

nginx_site() {
  local v6=''; [ -s /proc/net/if_inet6 ] && v6='listen [::]:80;'   # only where the server has IPv6
  cat > /etc/nginx/sites-available/menu <<NGINX
# Menu (scripts/deploy-vps.sh). Root follows the "current" release symlink.
server {
    listen 80;
    ${v6}
    server_name ${DOMAIN};
    root ${APP_DIR}/current/public;
    index index.php;
    client_max_body_size 12m;
    server_tokens off;

    gzip on;
    gzip_comp_level 6;
    gzip_min_length 512;
    gzip_types text/plain text/css text/javascript application/javascript application/json image/svg+xml;

    location ~ ^/(app/assets|media|menu-assets)/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        include snippets/menu-headers.conf;
        try_files \$uri =404;
    }
    rewrite "^/[qQ]/([A-Za-z0-9]{8})\$" /qr.php?c=\$1 last;
    location ~ "^/([a-z0-9-]+)/([a-z0-9-]+)\$" {
        if (\$args != "") { rewrite ^ /index.php last; }
        gzip_static on;
        default_type text/html;
        charset utf-8;
        add_header Cache-Control "public, max-age=0, must-revalidate";
        add_header Content-Security-Policy "object-src 'none'; base-uri 'none'" always;
        add_header Cross-Origin-Opener-Policy "same-origin" always;
        include snippets/menu-headers.conf;
        try_files /menu-cache/\$1/\$2/index.html /index.php?\$query_string;
    }
    location ~ "^/([a-z0-9-]+)\$" {
        if (\$args != "") { rewrite ^ /index.php last; }
        gzip_static on;
        default_type text/html;
        charset utf-8;
        add_header Cache-Control "public, max-age=0, must-revalidate";
        add_header Content-Security-Policy "object-src 'none'; base-uri 'none'" always;
        add_header Cross-Origin-Opener-Policy "same-origin" always;
        include snippets/menu-headers.conf;
        try_files /menu-cache/\$1/index.html /index.php?\$query_string;
    }
    location = /sw.js {
        add_header Cache-Control "public, max-age=0, must-revalidate";
        include snippets/menu-headers.conf;
        try_files \$uri =404;
    }
    location / {
        include snippets/menu-headers.conf;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ ^/(index|qr)\.php\$ {
        fastcgi_pass unix:/run/php/${PHP}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        include fastcgi_params;
    }
    location ~ \.(php\d?|phtml|phar)\$ { return 404; }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
  cat > /etc/nginx/snippets/menu-headers.conf <<'NGINX'
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()" always;
NGINX
  ln -sfn /etc/nginx/sites-available/menu /etc/nginx/sites-enabled/menu
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
}

install() {
  local tarball=${1:-}
  [ -n "${DOMAIN:-}" ] || die "set DOMAIN, e.g. DOMAIN=menu.example.com"
  [ -n "$tarball" ] || die "usage: $0 install menu-release.tar.gz"
  [ ! -e "$APP_DIR/current" ] || die "already installed in $APP_DIR: use update"

  say "1/8 Packages (Nginx, PHP 8.3, MySQL 8, cron)"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -q
  apt-get install -y -q procps
  apt-get install -y -q nginx mysql-server cron unzip curl ca-certificates openssl \
    $PHP-fpm $PHP-cli $PHP-mysql $PHP-mbstring $PHP-xml $PHP-intl $PHP-gd $PHP-zip $PHP-curl $PHP-bcmath $PHP-opcache
  [ -n "${NO_TLS:-}" ] || apt-get install -y -q certbot python3-certbot-nginx

  say "2/8 PHP settings (uploads, memory, OPcache)"
  cat > /etc/php/8.3/fpm/conf.d/90-menu.ini <<'INI'
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
INI
  cp /etc/php/8.3/fpm/conf.d/90-menu.ini /etc/php/8.3/cli/conf.d/90-menu.ini
  sed -i 's/^opcache.validate_timestamps = 0/opcache.validate_timestamps = 1/' /etc/php/8.3/cli/conf.d/90-menu.ini

  say "3/8 Database"
  svc start mysql
  local dbpass; dbpass=$(secret 32)
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${dbpass}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${dbpass}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

  say "4/8 Folders and settings (.env)"
  mkdir -p "$APP_DIR/releases" "$APP_DIR/shared/media"
  mkdir -p "$APP_DIR"/shared/storage/{app/backups,app/private,app/public,framework/cache,framework/sessions,framework/views,logs}
  local release; release=$(unpack "$tarball")
  local scheme=https; [ -z "${NO_TLS:-}" ] || scheme=http
  local adminpath; adminpath="office-$(secret 10 | tr 'A-Z' 'a-z')"
  cp "$release/.env.example" "$APP_DIR/shared/.env.new"
  local env="$APP_DIR/shared/.env.new"
  set_env() { if grep -q "^$1=" "$env"; then sed -i "s#^$1=.*#$1=$2#" "$env"; else echo "$1=$2" >> "$env"; fi; }
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "$scheme://$DOMAIN"
  set_env LOG_STACK daily
  set_env LOG_LEVEL warning
  set_env DB_DATABASE "$DB_NAME"
  set_env DB_USERNAME "$DB_USER"
  set_env DB_PASSWORD "$dbpass"
  set_env SESSION_SECURE_COOKIE "$([ "$scheme" = https ] && echo true || echo false)"
  set_env QUEUE_CONNECTION database
  set_env SUPER_ADMIN_PATH "$adminpath"
  set_env MAIL_FROM_ADDRESS "\"hello@$DOMAIN\""
  mv "$env" "$APP_DIR/shared/.env"
  chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/shared"
  chmod 640 "$APP_DIR/shared/.env"
  switch_to "$release"
  artisan key:generate --force
  artisan backup:key --write >/dev/null

  say "5/8 Tables and caches"
  artisan migrate --force
  artisan platform:aggregate
  artisan optimize

  say "6/8 Web server"
  nginx_site
  svc restart "$PHP-fpm"
  svc restart nginx
  if [ -z "${NO_TLS:-}" ]; then
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "${EMAIL:?set EMAIL for the HTTPS certificate}" --redirect
    sed -i 's|^add_header Referrer|add_header Strict-Transport-Security "max-age=31536000" always;\nadd_header Referrer|' /etc/nginx/snippets/menu-headers.conf
    nginx -t && svc reload nginx
  fi

  say "7/8 Scheduler (cron, every minute) and firewall"
  echo "* * * * * $WEB_USER cd $APP_DIR/current && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/menu
  chmod 644 /etc/cron.d/menu
  svc start cron || true
  if command -v ufw >/dev/null && [ -z "${NO_FIREWALL:-}" ]; then ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable; fi

  say "8/8 Check"
  artisan menu:doctor --production || true
  cat <<DONE

Installed. Save these in your password manager NOW (they are also in $APP_DIR/shared/.env):
  Database password:  $dbpass
  APP_KEY / BACKUP_KEY: see $APP_DIR/shared/.env (keep a copy of the whole file)

Next:
  1. Create your Super Admin:   cd $APP_DIR/current && sudo -u $WEB_USER php artisan super:create
  2. Sign in at:                $scheme://$DOMAIN/$adminpath/sign-in  (set up two-factor first)
  3. Settings > Email (SMTP) > Send test email;  Backups > Back up now
  4. Run the check again:       sudo bash $0 doctor   (red lines must be gone)
DONE
}

update() {
  local tarball=${1:-}
  [ -n "$tarball" ] || die "usage: $0 update menu-release.tar.gz"
  [ -e "$APP_DIR/current" ] || die "not installed yet: use install"
  say "Backup before the update"
  artisan backup:run || die "backup failed: nothing changed"
  local release; release=$(unpack "$tarball")
  say "Maintenance mode, migrations, caches"
  artisan down --retry=30 || true
  (cd "$release" && runuser -u "$WEB_USER" -- php artisan migrate --force && runuser -u "$WEB_USER" -- php artisan optimize) \
    || { artisan up; rm -rf "$release"; die "migration failed: still on the old version (restore the backup if tables changed)"; }
  echo "$(readlink -f "$APP_DIR/current")" > "$APP_DIR/previous"
  switch_to "$release"
  artisan menu:refresh-pages --all
  artisan up
  prune
  say "Updated to $(basename "$release")"
  artisan menu:doctor --production || true
}

rollback() {
  local prev; prev=$(cat "$APP_DIR/previous" 2>/dev/null || true)
  [ -n "$prev" ] && [ -d "$prev" ] || prev=$(ls -1dt "$APP_DIR"/releases/* | sed -n 2p)
  [ -n "$prev" ] && [ -d "$prev" ] || die "no earlier release to go back to"
  say "Back to $(basename "$prev")"
  echo "$(readlink -f "$APP_DIR/current")" > "$APP_DIR/previous"
  switch_to "$prev"
  artisan optimize
  artisan menu:refresh-pages --all
  echo "Done. Database changes of the newer version are NOT undone: if it added tables or columns, the older code ignores them. To undo data changes, restore the backup taken before the update (docs/deploy.md section 10)."
}

case "${1:-}" in
  install) install "${2:-}" ;;
  update) update "${2:-}" ;;
  rollback) rollback ;;
  doctor) artisan menu:doctor --production ;;
  *) sed -n '2,15p' "$0"; exit 1 ;;
esac
