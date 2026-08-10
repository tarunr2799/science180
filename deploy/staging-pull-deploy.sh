#!/usr/bin/env bash
set -euo pipefail

REPO_PATH="${REPO_PATH:-/home-1tb/amen123jesus/repositories/science180}"
DEPLOY_PATH="${DEPLOY_PATH:-/home-1tb/amen123jesus/public_html/}"
BRANCH="${BRANCH:-main}"

if [ ! -d "$REPO_PATH/.git" ]; then
  echo "Repository not found at $REPO_PATH"
  echo "Clone it from GitHub first, or create it through cPanel Git Version Control."
  exit 1
fi

cd "$REPO_PATH"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

/bin/bash deploy/deploy.sh "$DEPLOY_PATH"

