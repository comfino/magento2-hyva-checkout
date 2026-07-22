#!/bin/bash
set -euo pipefail

# Stamps VERSION into source files, then commits the result.
# Called from tag-beta.sh and tag-release.sh before the annotated tag is created,
# so the stamped state ends up in the snapshot.
#
# Usage: bin/stamp-version.sh <version-tag>
#   e.g. bin/stamp-version.sh 1.0.0-beta3
#        bin/stamp-version.sh 1.0.0

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

TAG="${1:-}"
[[ -z "$TAG" ]] && { echo "stamp-version: missing version argument" >&2; exit 1; }

VERSION_PHP="$REPO_ROOT/Model/Version.php"

sed -i "s/^\(    public const VERSION = '\)[^']*';/\1${TAG}';/" "$VERSION_PHP"

grep -q "VERSION = '${TAG}';" "$VERSION_PHP" || { echo "stamp-version: VERSION patch failed" >&2; exit 1; }

git -C "$REPO_ROOT" add Model/Version.php
git -C "$REPO_ROOT" commit -m "Bump version to ${TAG}"

echo "Stamped: VERSION='${TAG}'"