#!/usr/bin/env bash
# Google Play checks on a built APK (bug hunt 2026-11-17). Usage:
#   tool/play_check.sh build/app/outputs/flutter-apk/app-arm64-v8a-release.apk
# Fails when the merged manifest asks for a permission the app doesn't use
# (Play's photo/video policy rejects READ_MEDIA_* without a core need), locks
# the screen orientation (large screens), or a native library isn't 16 KB aligned.
set -euo pipefail
apk="$1"
bt="${ANDROID_HOME:-${ANDROID_SDK_ROOT:-/usr/local/lib/android/sdk}}/build-tools"
tools="$bt/$(ls "$bt" | sort -V | tail -1)"
fail=0

perms=$("$tools/aapt2" dump permissions "$apk")
for p in READ_MEDIA_IMAGES READ_MEDIA_VIDEO READ_MEDIA_AUDIO READ_EXTERNAL_STORAGE WRITE_EXTERNAL_STORAGE CAMERA \
         com.google.android.gms.permission.AD_ID ACCESS_ADSERVICES_AD_ID ACCESS_ADSERVICES_ATTRIBUTION ACCESS_FINE_LOCATION ACCESS_COARSE_LOCATION; do
  if grep -q "name='[a-z.]*$p'" <<<"$perms"; then echo "✗ permission not used by the app: $p"; fail=1; fi
done

if "$tools/aapt2" dump xmltree --file AndroidManifest.xml "$apk" | grep -q 'screenOrientation'; then
  echo "✗ an activity locks the screen orientation"; fail=1
fi

if ! "$tools/zipalign" -c -P 16 4 "$apk" >/dev/null; then echo "✗ not 16 KB page aligned (zip)"; fail=1; fi
tmp=$(mktemp -d); unzip -q -o "$apk" 'lib/*' -d "$tmp" || true
for so in "$tmp"/lib/arm64-v8a/*.so; do
  [ -f "$so" ] || continue
  align=$(python3 - "$so" <<'PY'
import struct, sys
d = open(sys.argv[1], 'rb').read()
off = struct.unpack_from('<Q', d, 0x20)[0]; size, n = struct.unpack_from('<HH', d, 0x36)
print(min(struct.unpack_from('<Q', d, off + i * size + 0x30)[0] for i in range(n) if struct.unpack_from('<I', d, off + i * size)[0] == 1))
PY
)
  if [ "$align" -lt 16384 ]; then echo "✗ $(basename "$so") is aligned to $align, not 16 KB"; fail=1; fi
done
rm -rf "$tmp"

[ $fail = 0 ] && echo "✓ Play checks passed: permissions, orientation, 16 KB pages"
exit $fail
