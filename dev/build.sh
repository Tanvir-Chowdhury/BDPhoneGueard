#!/usr/bin/env bash
# Runs the PHP test suite and rebuilds the distributable zip.
# Usage: bash dev/build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="bd-phone-guard"

# 1. Test (when PHP is available).
if command -v php >/dev/null 2>&1; then
	php "$ROOT/dev/run-tests.php"
else
	echo "WARNING: php not found — run 'php dev/run-tests.php' on a machine with PHP before submitting." >&2
fi

# 2. Version from the plugin header.
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$SLUG/$SLUG.php" | head -1 | tr -d '[:space:]')"
if [ -z "$VERSION" ]; then
	echo "ERROR: could not read the Version header from $SLUG/$SLUG.php" >&2
	exit 1
fi

# 3. Package: the plugin folder becomes the zip's top-level directory.
mkdir -p "$ROOT/dist"
rm -rf "$ROOT/dist/$SLUG" "$ROOT/dist/$SLUG-$VERSION.zip"
cp -R "$ROOT/$SLUG" "$ROOT/dist/$SLUG"
find "$ROOT/dist/$SLUG" -name '.DS_Store' -delete 2>/dev/null || true

( cd "$ROOT/dist" && zip -rq "$SLUG-$VERSION.zip" "$SLUG" )

echo "Built dist/$SLUG-$VERSION.zip"
unzip -l "$ROOT/dist/$SLUG-$VERSION.zip" | tail -5
