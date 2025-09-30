# SCSS Kompilierung - Cheercity Symfony Projekt

## Übersicht

Dieses Projekt verwendet SCSS (Sass) für die CSS-Erstellung. Die SCSS-Dateien befinden sich in `public/assets/sass/` und müssen zu CSS kompiliert werden.

## Verzeichnisstruktur

```
public/assets/
├── sass/                 # SCSS-Quelldateien
│   ├── main.scss        # Hauptdatei (Einstiegspunkt)
│   ├── abstracts/       # Variablen, Mixins, Funktionen
│   ├── base/           # Basis-Styles, Fonts, Reset
│   ├── components/     # UI-Komponenten (Buttons, Cards, etc.)
│   ├── layout/         # Layout-Komponenten (Header, Footer, Grid)
│   └── vendors/        # Drittanbieter-Styles
└── css/                # Kompilierte CSS-Dateien
    ├── main.css        # Hauptdatei (erweitert, mit Source Map)
    ├── main.min.css    # Komprimierte Version (Produktion)
    └── main.css.map    # Source Map für Debugging
```

## Installation von Sass

### Option 1: Node.js/npm (Empfohlen)
```bash
# Global installieren
npm install -g sass

# Oder lokal im Projekt
npm install sass --save-dev
```

### Option 2: Dart Sass Binary
```bash
# Herunterladen und installieren
curl -fsSL https://github.com/sass/dart-sass/releases/download/1.70.0/dart-sass-1.70.0-linux-x64.tar.gz | tar -xz
sudo cp dart-sass/sass /usr/local/bin/sass
```

## Kompilierungs-Befehle

### Einmalige Kompilierung
```bash
# Entwicklung (erweitert, mit Source Map)
sass public/assets/sass/main.scss public/assets/css/main.css --style=expanded --source-map

# Produktion (komprimiert, ohne Source Map)  
sass public/assets/sass/main.scss public/assets/css/main.min.css --style=compressed --no-source-map
```

### Watch-Modus (Automatische Neukompilierung)
```bash
# Einzelne Datei überwachen
sass --watch public/assets/sass/main.scss:public/assets/css/main.css

# Gesamtes Verzeichnis überwachen
sass --watch public/assets/sass:public/assets/css
```

## Package.json Scripts

Das Projekt enthält bereits eine `package.json` mit praktischen Scripts:

```bash
# CSS kompilieren
npm run build-css

# CSS im Watch-Modus
npm run watch-css

# Komprimiertes CSS für Produktion
npm run build-css-compressed
```

## Integration in Symfony

Die kompilierten CSS-Dateien werden über Twig-Templates eingebunden:

```twig
{# In templates/base.html.twig #}
<link rel="stylesheet" href="{{ asset('assets/css/main.css') }}?v={{ asset_version }}">
```

## Aktuelle kompilierte Dateien

- ✅ `main-compiled.css` (544 KB) - Entwicklungsversion mit Source Map
- ✅ `main-compiled.min.css` (468 KB) - Produktionsversion (komprimiert)
- ✅ `main-compiled.css.map` (94 KB) - Source Map für Debugging

## Workflow-Empfehlung

1. **Entwicklung**: Nutzen Sie den Watch-Modus für automatische Kompilierung
   ```bash
   sass --watch public/assets/sass:public/assets/css
   ```

2. **SCSS-Änderungen**: Bearbeiten Sie nur die `.scss` Dateien in `public/assets/sass/`

3. **Produktion**: Kompilieren Sie komprimierte Versionen für bessere Performance
   ```bash
   sass public/assets/sass/main.scss public/assets/css/main.min.css --style=compressed
   ```

4. **Versionskontrolle**: Committen Sie sowohl SCSS- als auch kompilierte CSS-Dateien

## Häufige Probleme und Lösungen

### Fehler: "File to import not found"
- Prüfen Sie die Pfade in den `@import` Anweisungen
- Stellen Sie sicher, dass alle referenzierten Dateien existieren

### Fehler: "Invalid CSS"
- Überprüfen Sie die SCSS-Syntax
- Nutzen Sie einen SCSS-Validator oder Editor mit Syntax-Highlighting

### Source Maps funktionieren nicht
- Stellen Sie sicher, dass die `.map` Dateien im gleichen Verzeichnis liegen
- Browser Developer Tools müssen Source Maps aktiviert haben

## Weitere Ressourcen

- [Sass Documentation](https://sass-lang.com/documentation)
- [Symfony Asset Component](https://symfony.com/doc/current/frontend.html)
- [Webpack Encore für Symfony](https://symfony.com/doc/current/frontend/encore.html)