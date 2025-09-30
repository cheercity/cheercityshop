#!/usr/bin/env bash
# Simple deploy script: rsync compiled CSS to remote host and request acceptance
# Usage: ./scripts/deploy.sh [--dry-run]

set -euo pipefail
DRY=""
if [ "${1:-}" = "--dry-run" ]; then
  DRY="--dry-run"
fi

# Config - override with env vars or edit this file
REMOTE_HOST="your.remote.host"
REMOTE_USER="deployuser"
REMOTE_DIR="/var/www/your-site/public/assets/css"
REMOTE_TMP_DIR="/tmp/cc-deploy-$$"
REMOTE_CONFIRM_ENDPOINT="/usr/local/bin/remote-accept.sh" # server-side script to execute
LOCAL_FILE="public/assets/css/main.css"

if [ ! -f "$LOCAL_FILE" ]; then
  echo "Local file $LOCAL_FILE not found. Build CSS first (npm run build-css)."
  exit 2
fi

echo "-> Starting deploy of $LOCAL_FILE to $REMOTE_HOST:$REMOTE_DIR"

# Create remote temp dir
ssh ${REMOTE_USER}@${REMOTE_HOST} "mkdir -p ${REMOTE_TMP_DIR} && chmod 755 ${REMOTE_TMP_DIR}"

# Upload to temp dir
rsync -avz ${DRY} --checksum --progress "$LOCAL_FILE" ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_TMP_DIR}/

# Ask remote to validate and move into place (the remote script must exist and be secure)
ssh ${REMOTE_USER}@${REMOTE_HOST} "bash ${REMOTE_CONFIRM_ENDPOINT} ${REMOTE_TMP_DIR} $(basename $LOCAL_FILE) ${REMOTE_DIR}"

# Cleanup remote temp dir
ssh ${REMOTE_USER}@${REMOTE_HOST} "rm -rf ${REMOTE_TMP_DIR}"

echo "-> Deploy finished (pending remote confirmation if any)."
