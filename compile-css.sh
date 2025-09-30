#!/bin/bash

# Kompilierungs-Anweisungen für SCSS-Dateien
# ===========================================

echo "🚀 SCSS Kompilierungs-Anweisungen für Cheercity Symfony-Projekt"
echo ""

# Prüfe ob Sass verfügbar ist
if command -v /tmp/dart-sass/sass &> /dev/null; then
    SASS_CMD="/tmp/dart-sass/sass"
    echo "✅ Sass gefunden: $SASS_CMD"
elif command -v sass &> /dev/null; then
    SASS_CMD="sass"
    echo "✅ Sass gefunden: $SASS_CMD"
else
    echo "❌ Sass nicht gefunden!"
    echo ""
    echo "Installation:"
    echo "1. Node.js/npm: npm install -g sass"
    echo "2. Dart Sass Binary: https://github.com/sass/dart-sass/releases"
    exit 1
fi

echo ""
echo "📁 SCSS-Dateien-Struktur:"
echo "├── public/assets/sass/main.scss (Hauptdatei)"
echo "├── public/assets/sass/abstracts/ (Variablen, Mixins)"
echo "├── public/assets/sass/base/ (Basis-Styles)"
echo "├── public/assets/sass/components/ (UI-Komponenten)"
echo "├── public/assets/sass/layout/ (Layout-Komponenten)"
echo "└── public/assets/sass/vendors/ (Drittanbieter-Styles)"
echo ""

# Kompilierung durchführen
echo "🔄 Kompiliere SCSS zu CSS..."

INPUT="public/assets/sass/main.scss"
OUTPUT="public/assets/css/main.css"

if [ ! -f "$INPUT" ]; then
    echo "❌ SCSS-Eingabedatei nicht gefunden: $INPUT"
    exit 1
fi

# Erstelle Output-Verzeichnis falls es nicht existiert
mkdir -p "$(dirname "$OUTPUT")"

# Kompiliere mit Source Map
$SASS_CMD "$INPUT" "$OUTPUT" --style=expanded --source-map

if [ $? -eq 0 ]; then
    echo "✅ SCSS erfolgreich kompiliert!"
    echo "📄 Ausgabedatei: $OUTPUT"
    echo "🗺️  Source Map: ${OUTPUT}.map"
    
    # Zeige Dateigröße
    if [ -f "$OUTPUT" ]; then
        SIZE=$(du -h "$OUTPUT" | cut -f1)
        echo "📊 Dateigröße: $SIZE"
    fi
    
    echo ""
    echo "🔍 Weitere Kompilierungs-Optionen:"
    echo ""
    echo "Komprimiert (Produktion):"
    echo "$SASS_CMD '$INPUT' '$OUTPUT' --style=compressed --no-source-map"
    echo ""
    echo "Watch-Modus (automatische Neukompilierung):"
    echo "$SASS_CMD --watch '$INPUT':'$OUTPUT'"
    echo ""
    echo "Alle SCSS-Dateien im sass/ Verzeichnis überwachen:"
    echo "$SASS_CMD --watch public/assets/sass:public/assets/css"
    
else
    echo "❌ Fehler beim Kompilieren der SCSS-Dateien"
    exit 1
fi