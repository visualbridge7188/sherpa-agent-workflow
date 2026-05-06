#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP_PATH="$ROOT_DIR/dist/duo-obituary-kboard.zip"

php "$ROOT_DIR/tests/smoke.php" plugin
php "$ROOT_DIR/tests/smoke.php" plugin-inactive
php "$ROOT_DIR/tests/smoke.php" skin

find "$ROOT_DIR/plugins/duo-obituary-kboard" "$ROOT_DIR/skin/duo-obituary-kboard" -name '*.php' -print0 | while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done

bash -n "$ROOT_DIR/scripts/build-plugin.sh"
"$ROOT_DIR/scripts/build-plugin.sh" >/dev/null

test -f "$ZIP_PATH"
unzip -t "$ZIP_PATH" >/dev/null
ZIP_LIST="$(unzip -Z1 "$ZIP_PATH")"
grep -q 'duo-obituary-kboard/duo-obituary-kboard.php' <<< "$ZIP_LIST"
grep -q 'duo-obituary-kboard/skins/duo-obituary-kboard/functions.php' <<< "$ZIP_LIST"
grep -q 'duo-obituary-kboard/skins/duo-obituary-kboard/list.php' <<< "$ZIP_LIST"
grep -q 'duo-obituary-kboard/skins/duo-obituary-kboard/latest.php' <<< "$ZIP_LIST"
grep -q 'duo-obituary-kboard/skins/duo-obituary-kboard/document.php' <<< "$ZIP_LIST"
grep -q 'duo-obituary-kboard/README.md' <<< "$ZIP_LIST"

echo "Smoke tests passed."
