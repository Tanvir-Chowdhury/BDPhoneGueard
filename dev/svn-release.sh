#!/usr/bin/env bash
# Publishes the plugin folder to the WordPress.org SVN repo: syncs trunk and
# .wordpress-org/ (screenshots) into assets, tags the
# version from the plugin header, then commits (SVN prompts for the password).
# Usage: bash dev/svn-release.sh [svn-username]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="nombar-bd-mobile-validator"
USER_NAME="${1:-tanvir11744}"
SVN_DIR="$ROOT/dist/svn"

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$SLUG/$SLUG.php" | head -1 | tr -d '[:space:]')"

# 1. Fresh checkout, or update an existing one.
if [ -d "$SVN_DIR/.svn" ]; then
	svn up "$SVN_DIR"
else
	svn co "https://plugins.svn.wordpress.org/$SLUG/" "$SVN_DIR"
fi

if [ -d "$SVN_DIR/tags/$VERSION" ]; then
	echo "ERROR: tags/$VERSION already exists. Bump Version and Stable tag first." >&2
	exit 1
fi

# 2. Mirror the plugin folder into trunk, scheduling adds and deletes.
rsync -a --delete --exclude '.svn' --exclude '.DS_Store' "$ROOT/$SLUG/" "$SVN_DIR/trunk/"
cd "$SVN_DIR"
svn add --force trunk --quiet
svn status trunk | awk '/^!/ {print $2}' | xargs -r svn rm --quiet

# Directory-page assets (screenshots, banners, icons) live outside trunk.
if [ -d "$ROOT/.wordpress-org" ]; then
	rsync -a --exclude '.svn' "$ROOT/.wordpress-org/" assets/
	svn add --force assets --quiet
	find assets -name '*.png' -exec svn propset --quiet svn:mime-type image/png {} +
fi

# 3. Tag the release from trunk.
svn cp trunk "tags/$VERSION"

svn status
echo
read -r -p "Commit $SLUG $VERSION as $USER_NAME? [y/N] " answer
[ "$answer" = "y" ] || { echo "Aborted; working copy left in dist/svn."; exit 0; }

svn ci -m "Release $VERSION" --username "$USER_NAME"
