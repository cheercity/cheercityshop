Repository snapshot (aktualisiert)
--------------------------------
Dies ist eine Symfony-basierte Webanwendung (PHP >= 8.2). Wichtige Artefakte, die ich gefunden habe:

- `composer.json`, `composer.lock`, `bin/console` — klassischer Symfony-Entrypoint.
- `public/index.php` — Web-Frontcontroller. Statische Assets in `public/assets/`.
- `src/` mit Controllern, Services und Twig-Extensions (z. B. `App\\Service\\FileMakerClient`, `NavService`, `App\\Twig\\NavExtension`).
- Twig-Templates in `templates/` (umfangreiche Shop-/Landingpage-Layouts).
- Konfiguration in `config/` (z. B. `services.yaml`, `routes.yaml`) und `compose.yaml` / `compose.override.yaml` für lokale Container.
- `.vscode/sftp.json` (SFTP-Deploy / Editor-Upload) — ggf. aktiviert `uploadOnSave`.

Kurz, prägnant: Projekt-Typ und Architektur
-------------------------------------------
- Framework: Symfony 7.2 (composer require: symfony/* 7.2.*).
- Architektur: klassische MVC mit Twig-Templates, Services (autowired), und Routing per PHP-Attribute (Controller) + YAML routes.
- Wichtige Integrationen: HTTP-Client-basierte FileMaker-API (siehe `FileMakerClient`), PSR-6 Cache in `NavService`, remote FTP/SFTP für Deployment.

Konkrete, repository-spezifische Hinweise für AI-Agenten
-------------------------------------------------------
1) Safety-first
   - In `.vscode/sftp.json` steht ein Remote-Host (FTP, Port 21). `uploadOnSave` kann Live-Änderungen auslösen. Niemals ohne Bestätigung des Menschen Dateien live überschreiben. Wenn du arbeiten willst, bitte den Besitzer, `uploadOnSave` auszuschalten.
   - ENV-Variablen für FileMaker: `FM_HOST`, `FM_DB`, `FM_USER`, `FM_PASS` (verwaltet über `config/services.yaml`). Diese sind sensibel und dürfen nicht geleakt oder in PRs veröffentlicht werden.

2) Wo anfangen (wichtige Dateien)
   - `composer.json` — Abhängigkeiten (PHP >= 8.2, symfony/* 7.2)
   - `bin/console` — CLI; nützlich zum Testen von Commands, Router und Cache
   - `public/index.php` — Web entry; zum schnellen lokalen Start
   - `config/services.yaml` — Service-Konfiguration (FileMakerClient injiziert ENV-Variablen)
   - `src/Service/FileMakerClient.php` — FileMaker-Integration (Token-Management, Fehlerbehandlung)
   - `src/Service/NavService.php` und `src/Twig/NavExtension.php` — Menü-Graphenlogik, nutzt Cache
   - `templates/` — viele Twig-Seiten; Controller rendern oft Variablen `css`/`script` als rohe HTML-Snippets

3) Typische Entwickler-Workflows / schnelle commands
   - Abhängigkeiten installieren:
     composer install
   - App lokal starten (wenn Symfony CLI verfügbar):
     symfony server:start
     oder (fallback) PHP Built-in Server:
     php -S localhost:8000 -t public public/index.php
   - Konfigurations-Variablen: prüfe `.env`, `.env.local` (werden in `.gitignore` empfohlen). Setze `FM_HOST`, `FM_DB`, `FM_USER`, `FM_PASS` vor Laufzeit.
   - Cache / Assets (Composer-Autoscripts laufen bei install): `composer install` ruft `cache:clear` und `assets:install` via Symfony Flex auf.
   - CLI-Tools: `bin/console` (z. B. `bin/console debug:router`, `bin/console cache:clear`, `bin/console doctrine:migrations:status` falls DB genutzt)

4) Projekt-spezifische Konventionen und Patterns
   - Services sind autowired/autoconfigured via `config/services.yaml` (App\\ namespace -> src/)
   - Menü/Navigationsdaten stammen aus FileMaker; `NavService::getMenu()` holt Rohdaten, normalisiert und baut eine verschachtelte Struktur. Beim Ändern: Cache (PSR-6) und Token-Rotation in `FileMakerClient` beachten.
   - Controller geben teilweise rohe HTML-Strings als `css`/`script`-Variablen an Templates (siehe `HomeController`). Veränderungen an Asset-Referenzen sollten vorsichtig geschehen.
   - Templates sind umfangreich und enthalten viele statische Assets unter `public/assets/` — das Projekt hat kein offensichtliches JS-Tooling (kein package.json), Assets sind vorgerendert.

5) Integrationspunkte / externe Abhängigkeiten
   - FileMaker REST API (über `HttpClientInterface`) — Authentifizierung per Basic -> Token. Fehlercodes werden im Client behandelt (z. B. 952 -> erneute Authentifizierung).
   - FTP/SFTP (Editor-Upload) — `.vscode/sftp.json` kann beim Speichern Dateien zum Hosting-Server senden.
   - Optional: Docker-Compose (`compose.yaml`, `compose.override.yaml`) vorhanden; nutze diese für reproduzierbare Dev-Umgebungen, falls gewünscht.

6) Typische Fehlerquellen / Edge-cases beim Arbeiten
   - Fehlende ENV-Variablen führen zu Laufzeitfehlern in `FileMakerClient`/Services — initialisiere `.env.local` mit Test- oder Platzhalter-Values.
   - `uploadOnSave` führt zu unbeabsichtigten Deploys; schalte es aus bevor du große Refactors machst.
   - `NavService` nutzt `uniqid` für fehlende IDs; beim Reproduzieren von Menüstrukturen auf Testdaten aufpassen.

7) Wenn du Änderungen vorschlägst (PR-Checks)
   - Füge eine kurze README-Notiz hinzu, die beschreibt wie man die ENV-Variablen setzt und wie man die App lokal startet.
   - Für Änderungen an der FileMaker-Integration: beschreibe erwartete Feldnamen / Beispielantworten (z. B. `fieldData`, `messages[*].code`) und füge Unit-Tests oder Integrationstests hinzu, wenn möglich.

8) Fragen an den Menschen (Vorlage)
   - "Soll ich `uploadOnSave` in `.vscode/sftp.json` temporär auf `false` setzen?"
   - "Kannst du mir eine `.env.local` (sichere Testwerte) geben oder soll ich mit Platzhaltern arbeiten?"
   - "Welche Umgebung nutzt ihr für Entwicklung: Symfony CLI, Docker-Compose (`compose.yaml`) oder Remote-FTP direkt?"

Kurzzusammenfassung / nächster Schritt
-------------------------------------
Ich habe das Projekt analysiert und die wichtigsten Integrationspunkte (FileMaker, SFTP), Laufzeitdateien (`bin/console`, `public/index.php`) und Konfigurationen (`config/services.yaml`) identifiziert. Soll ich `uploadOnSave` deaktivieren, eine `README.md` mit Startinstruktionen erzeugen oder die Anweisungen weiter konkretisieren (z. B. Composer-/Docker-Befehle)?
