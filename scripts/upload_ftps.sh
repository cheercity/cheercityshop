#!/usr/bin/env sh
# Manual FTPS deploy script using lftp
# Usage: ./scripts/upload_ftps.sh [--dry-run]
# Reads credentials from .vscode/sftp.json (not shipped) and performs a mirror upload.

SETUP_REMOTE_HOST="k05p30.meinserver.io"
SETUP_REMOTE_USER="c713036admin"
SETUP_REMOTE_PORT=21
# lftp will prompt for a password if not provided; avoid hardcoding secrets in scripts.

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then
  DRY_RUN=1
fi

LOCAL_DIR="$(pwd)"
REMOTE_DIR="/web/htdocs"

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp is required. Install it with: brew install lftp or apt-get install lftp"
  exit 2
fi

LFTP_CMD="set ssl:verify-certificate no; set ftp:ssl-force true; set ftp:ssl-protect-data true; open -u ${SETUP_REMOTE_USER} ${SETUP_REMOTE_HOST}:${SETUP_REMOTE_PORT}; mirror -R --delete --verbose ${LOCAL_DIR} ${REMOTE_DIR}"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "DRY RUN: would run lftp with command:" 
  echo "$LFTP_CMD"
  echo "Note: lftp will ask for password interactively." 
  exit 0
fi

# Execute lftp (will prompt for password)
exec lftp -e "$LFTP_CMD; bye"
