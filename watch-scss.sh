#!/bin/bash

# SCSS Watch Script für automatische Kompilierung
# Überwacht Änderungen in public/assets/sass/ und kompiliert automatisch

SASS_DIR="public/assets/sass"
COMPILE_SCRIPT="./compile-scss.sh"

echo "🔍 SCSS Watcher gestartet..."
echo "📁 Überwacht: $SASS_DIR"
echo "🔨 Kompiliert mit: $COMPILE_SCRIPT"
echo ""
echo "Drücken Sie Strg+C zum Beenden."
echo ""

# Initiale Kompilierung
echo "🚀 Initiale Kompilierung..."
$COMPILE_SCRIPT
echo ""

# Watch für Änderungen in SCSS-Dateien
inotifywait -m -r -e modify,create,delete --format '%w%f %e' "$SASS_DIR" |
while read file event; do
    # Nur bei .scss Dateien reagieren
    if [[ "$file" == *.scss ]]; then
        echo "📝 Änderung erkannt: $file ($event)"
        echo "🔄 Kompiliere SCSS..."
        
        # Kompiliere SCSS
        if $COMPILE_SCRIPT; then
            echo "✅ Kompilierung erfolgreich abgeschlossen!"
        else
            echo "❌ Fehler bei der Kompilierung!"
        fi
        echo ""
    fi
done