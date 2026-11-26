#!/usr/bin/env bash
# Copies the app's Help screenshots (store/help/*.png, made by
# test/store/help_shots_test.dart) into the web repo as 390 px WebP.
# Usage: tool/help_shots.sh ../menu
set -euo pipefail
web="${1:-../menu}"
out="$web/backend/public/help/shots"
mkdir -p "$out"
for f in store/help/*.png; do
  name="$(basename "$f" .png)"
  php -r '$s=imagecreatefrompng($argv[1]);$w=390;$h=(int)round(imagesy($s)*$w/imagesx($s));$d=imagecreatetruecolor($w,$h);imagecopyresampled($d,$s,0,0,0,0,$w,$h,imagesx($s),imagesy($s));imagewebp($d,$argv[2],74);' "$f" "$out/$name.webp"
  echo "$name.webp"
done
