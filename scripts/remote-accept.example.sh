#!/usr/bin/env bash
# Example server-side accept script (install as /usr/local/bin/remote-accept.sh and make executable)
# It receives: tmp_dir filename target_dir
# The script should validate the uploaded file (checksums, signature, mime-type) and then atomically move into place.

set -euo pipefail
TMP_DIR="$1"
FILE_NAME="$2"
TARGET_DIR="$3"

if [ -z "$TMP_DIR" ] || [ -z "$FILE_NAME" ] || [ -z "$TARGET_DIR" ]; then
  echo "Usage: $0 tmp_dir filename target_dir" >&2
  exit 2
fi

UPLOAD="$TMP_DIR/$FILE_NAME"

# Basic validation: ensure file exists and size reasonable
if [ ! -f "$UPLOAD" ]; then
  echo "No uploaded file: $UPLOAD" >&2
  exit 3
fi

# Optional: run a CSS linter or validator here
# e.g., npm install -g stylelint && stylelint "$UPLOAD"

# Use atomic deploy: write to temp then move
mkdir -p "$TARGET_DIR"
TMP_DEST="$TARGET_DIR/.tmp.$FILE_NAME.$(date +%s)"
mv "$UPLOAD" "$TMP_DEST"
chown --reference="$TARGET_DIR" "$TMP_DEST" || true
chmod 644 "$TMP_DEST"

# Optionally backup existing file
if [ -f "$TARGET_DIR/$FILE_NAME" ]; then
  cp -a "$TARGET_DIR/$FILE_NAME" "$TARGET_DIR/$FILE_NAME.bak.$(date +%s)" || true
fi

mv -f "$TMP_DEST" "$TARGET_DIR/$FILE_NAME"

echo "ACCEPTED"
exit 0
