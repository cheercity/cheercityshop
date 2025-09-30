# Footer Module Problem - Diagnose und Lösung

## Problem
Das dynamische Modul im Footer, das aus FileMaker kommt, wird nicht mehr angezeigt.

## Analyse des Codes
Das dynamische Footer-Modul wird durch folgende Komponenten realisiert:

1. **Footer Template**: `templates/partials/footer.html.twig` (Zeilen 123-141)
   ```twig
   {% set moduleGroups = footer_modules() %}
   {% for modul, items in moduleGroups %}
       <!-- Dynamische Footer-Inhalte -->
   {% endfor %}
   ```

2. **Twig Extension**: `src/Twig/NavExtension.php`
   - Definiert die `footer_modules()` Funktion
   - Ruft `$this->nav->getFooterModules($ttl)` auf

3. **Service**: `src/Service/NavService.php` 
   - `getFooterModules()` Methode
   - Holt Daten aus FileMaker Layout `sym_Module`
   - Filter: `['Published' => '1', 'Footer_Status' => '1']`
   - Verwendet Cache mit Key `'footer_modules'`

## Erstellte Debug-Tools

### 1. FileMaker Layout Test
**Datei**: `public/filemaker-test.php`
- Testet die Verbindung zu FileMaker
- Überprüft das `sym_Module` Layout
- Testet verschiedene Filter-Kombinationen
- Zeigt alle verfügbaren Felder an

### 2. Footer Debug Tool  
**Datei**: `public/footer-debug.php`
- Testet die komplette Footer-Module Funktionalität
- Umgeht den Cache (TTL=0)
- Zeigt detaillierte Fehlerausgabe

### 3. Cache Clearing Tool
**Datei**: `public/footer-cache-clear.php` 
- Löscht den `footer_modules` Cache
- Löscht auch Navigation-Caches

### 4. Console Command
**Datei**: `src/Command/TestFooterModulesCommand.php`
- Symfony Console Command: `php bin/console test:footer-modules`

## Handlungsschritte

### Schritt 1: FileMaker Layout überprüfen
Rufen Sie auf: `http://ihre-domain.de/filemaker-test.php`

**Mögliche Probleme:**
- Layout `sym_Module` existiert nicht
- Felder fehlen: `Modul`, `titel`, `lnk`, `Sortorder`, `Published`, `Footer_Status`
- Keine Datensätze mit den erforderlichen Filter-Bedingungen

### Schritt 2: Cache löschen
Rufen Sie auf: `http://ihre-domain.de/footer-cache-clear.php`

### Schritt 3: Footer-Module testen
Rufen Sie auf: `http://ihre-domain.de/footer-debug.php`

## Wahrscheinliche Ursachen

1. **Layout/Feld-Problem**: Das häufigste Problem ist, dass das FileMaker Layout `sym_Module` nicht existiert oder die erwarteten Felder fehlen.

2. **Filter-Problem**: Keine Datensätze haben sowohl `Published = '1'` als auch `Footer_Status = '1'`.

3. **FileMaker-Verbindung**: Die Zugangsdaten sind nicht korrekt oder FileMaker-Server ist nicht erreichbar.

4. **Cache-Problem**: Alter Cache wird zurückgegeben.

## Konfiguration

Die FileMaker-Konfiguration befindet sich in `.env`:
```
FM_HOST="https://fms.cheercity-shop.de"
FM_DB="CHEERCITYshop"  
FM_USER="eshop"
FM_PASS="PjZtvq%XNQ@§4_$"
```

## Nächste Schritte

1. Führen Sie die Debug-Tools in der angegebenen Reihenfolge aus
2. Basierend auf den Ergebnissen kann das spezifische Problem identifiziert werden
3. Die Debug-Tools zeigen detaillierte Fehlermeldungen und Lösungsvorschläge

## Sicherheitshinweis

⚠️ **WICHTIG**: Die Debug-Dateien enthalten sensible Informationen. Löschen Sie diese nach der Diagnose:
- `public/filemaker-test.php`
- `public/footer-debug.php` 
- `public/footer-cache-clear.php`