#!/bin/bash

# SCSS Kompilierungs-Skript für das Cheercity Projekt
SASS_BINARY="/tmp/dart-sass/sass"
SASS_INPUT="public/assets/sass/main.scss"
CSS_OUTPUT="public/assets/css/main.css"

echo "Kompiliere SCSS zu CSS..."
echo "Input: $SASS_INPUT"
echo "Output: $CSS_OUTPUT"

# Kompiliere SCSS zu CSS mit Source Map
$SASS_BINARY $SASS_INPUT $CSS_OUTPUT --style=expanded --source-map

if [ $? -eq 0 ]; then
    echo "✅ SCSS erfolgreich kompiliert!"
    echo "📁 Ausgabedatei: $CSS_OUTPUT"
    ls -la $CSS_OUTPUT
else
    echo "❌ Fehler beim Kompilieren der SCSS-Dateien"
    exit 1
fi