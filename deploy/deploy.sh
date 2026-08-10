#!/usr/bin/env bash
set -euo pipefail

DEPLOY_PATH="${1:-}"

if [ -z "$DEPLOY_PATH" ]; then
  echo "Missing deploy path. Usage: deploy/deploy.sh /home-1tb/user/public_html/"
  exit 1
fi

case "$DEPLOY_PATH" in
  "/"|"/home"|"/home/"|"/home-1tb"|"/home-1tb/"|"public_html"|"/public_html")
    echo "Refusing unsafe deploy path: $DEPLOY_PATH"
    exit 1
    ;;
esac

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SOURCE_WP_CONTENT="$REPO_ROOT/wp-content"
TARGET_WP_CONTENT="${DEPLOY_PATH%/}/wp-content"

if [ ! -d "$SOURCE_WP_CONTENT" ]; then
  echo "Expected wp-content directory at $SOURCE_WP_CONTENT"
  exit 1
fi

mkdir -p "$TARGET_WP_CONTENT"

echo "Deploying wp-content to $TARGET_WP_CONTENT"

if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete \
    --exclude='uploads/' \
    --exclude='cache/' \
    --exclude='upgrade/' \
    --exclude='upgrade-temp-backup/' \
    --exclude='maintenance/' \
    --exclude='maintenance.php' \
    --exclude='mu-plugins/wp-toolkit.php' \
    --exclude='mu-plugins/wp-toolkit/' \
    --exclude='plugins/wp-file-manager/' \
    --exclude='aiowps_backups/' \
    --exclude='backup*/' \
    --exclude='backups/' \
    --exclude='ai1wm-backups/' \
    --exclude='wflogs/' \
    --exclude='debug.log' \
    "$SOURCE_WP_CONTENT/" "$TARGET_WP_CONTENT/"
else
  echo "rsync not available; falling back to cp -R without delete cleanup."
  cp -R "$SOURCE_WP_CONTENT/." "$TARGET_WP_CONTENT/"
fi

echo "Deployment complete."
