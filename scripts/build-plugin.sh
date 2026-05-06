#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="duo-obituary-kboard"
PLUGIN_DIR="$ROOT_DIR/plugins/$SLUG"
SKIN_SOURCE="$ROOT_DIR/skin/$SLUG"
SKIN_TARGET="$PLUGIN_DIR/skins/$SLUG"
DIST_DIR="$ROOT_DIR/dist"
ZIP_PATH="$DIST_DIR/$SLUG.zip"

if [ ! -d "$SKIN_SOURCE" ]; then
	echo "Missing skin source: $SKIN_SOURCE" >&2
	exit 1
fi

mkdir -p "$PLUGIN_DIR/skins" "$DIST_DIR"
rm -rf "$SKIN_TARGET"
cp -R "$SKIN_SOURCE" "$SKIN_TARGET"
cp "$ROOT_DIR/README.md" "$PLUGIN_DIR/README.md"

rm -f "$ZIP_PATH"
(
	cd "$ROOT_DIR/plugins"
	zip -qr "$ZIP_PATH" "$SLUG"
)

echo "Built $ZIP_PATH"
