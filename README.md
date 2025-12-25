# WSCPluginSWVariantUpdater

[English version](README_EN.md)

## Beschreibung

Ein Shopware 6 Plugin, das automatisch Namen und Artikelnummern von Produktvarianten basierend auf dem Hauptprodukt und den Variantenoptionen aktualisiert.

## Features

- **Automatische Namensgebung**: Erstellt Variantennamen nach dem Schema `{Hauptprodukt Name} {Optionsname(n)}`
- **Automatische Artikelnummern**: Generiert Artikelnummern nach dem Schema `{Hauptnummer}-{option(en)-in-lowercase}`
- **Flexible Optionen**:
  - Nur Namen aktualisieren (`--name-only`)
  - Nur Artikelnummern aktualisieren (`--number-only`)
  - Vorschau-Modus ohne Speichern (`--dry-run`)
- **Batch-Verarbeitung**: Mehrere Hauptprodukte auf einmal verarbeiten
- **Sichere Verarbeitung**: Explizite Produktauswahl erforderlich, keine automatische Massenverarbeitung

## Installation

### 1. Plugin installieren

```bash
# Plugin-Verzeichnis erstellen (falls noch nicht vorhanden)
mkdir -p custom/plugins

# Plugin in custom/plugins ablegen
cd custom/plugins
git clone <repository-url> WSCPluginSWVariantUpdater

# Zurück ins Shopware Root-Verzeichnis
cd ../../

# Plugin installieren und aktivieren
bin/console plugin:refresh
bin/console plugin:install --activate WSCPluginSWVariantUpdater
bin/console cache:clear
```

### 2. Dependencies installieren (für Entwicklung)

```bash
cd custom/plugins/WSCPluginSWVariantUpdater
composer install
```

## Verwendung

### Admin-Interface

Das Plugin bietet eine benutzerfreundliche Oberfläche im Shopware Admin:

**Zugriff:** `Einstellungen → Erweiterungen → Variant Updater`

#### Konfiguration

Vor der ersten Verwendung sollten die Twig-Templates konfiguriert werden:

1. Gehe zu `Einstellungen → System → Plugins`
2. Finde "WSC Variant Updater" und klicke auf `⋮ → Konfigurieren`
3. Konfiguriere die Templates:

**Twig-Template für Produktnamen:**
```twig
{{ parentProduct.translated.name }} {{ options|map(o => o.translated.name)|join(' ') }}
```

**Twig-Template für Produktnummern:**
```twig
{{ parentProduct.productNumber }}-{{ options|map(o => o.translated.name|lower|replace({' ': '-'}))|join('-') }}
```

**Standard-Optionen:**
- Nur Namen aktualisieren
- Nur Nummern aktualisieren
- Initiale Batch-Größe (10-500)

#### Ausführung

1. Gehe zu `Einstellungen → Erweiterungen → Variant Updater`
2. Wähle den Modus:
   - **Produktnummern eingeben**: Komma-getrennte Liste (z.B. `jacket-001, shoes-042`)
   - **Alle Produkte mit Varianten**: Aktualisiert alle Produkte (mit Bestätigung)
3. Aktiviere optional "Dry-Run (Vorschau)" zum Testen
4. Klicke auf "Jetzt ausführen"
5. Beobachte den Fortschritt in der Progress-Bar

**Hinweis:** Updates werden über die Message Queue verarbeitet. Stelle sicher, dass der Message Queue Worker läuft:

```bash
bin/console messenger:consume async -vv
```

### CLI-Verwendung

#### Grundlegende Verwendung

```bash
# Einzelnes Hauptprodukt aktualisieren
bin/console wsc:variant:update --product-numbers="artikelnummer123"

# Mehrere Hauptprodukte auf einmal
bin/console wsc:variant:update --product-numbers="artikel1,artikel2,artikel3"

# Alle Produkte mit Varianten aktualisieren (mit Bestätigung)
bin/console wsc:variant:update --all-products
```

### CLI-Optionen

#### --product-numbers
Eine einzelne Artikelnummer oder kommagetrennte Liste von Artikelnummern der Hauptprodukte.

```bash
bin/console wsc:variant:update --product-numbers="tollesleder"
bin/console wsc:variant:update --product-numbers="leder1,leder2,leder3"
```

#### --all-products
Aktualisiert ALLE Produkte mit Varianten. Zeigt vor der Ausführung eine Bestätigung an.

```bash
bin/console wsc:variant:update --all-products --dry-run
```

**Hinweis:** Entweder `--product-numbers` ODER `--all-products` muss angegeben werden, aber nicht beides gleichzeitig.

#### --dry-run (optional)
Zeigt nur an, was geändert würde, ohne die Datenbank zu aktualisieren.

```bash
bin/console wsc:variant:update --product-numbers="tollesleder" --dry-run
```

#### --name-only (optional)
Aktualisiert nur die Produktnamen, lässt Artikelnummern unverändert.

**Hinweis:** Diese Option kann auch in der Plugin-Konfiguration als Standard gesetzt werden. CLI-Flags überschreiben die Konfiguration.

```bash
bin/console wsc:variant:update --product-numbers="tollesleder" --name-only
```

#### --number-only (optional)
Aktualisiert nur die Artikelnummern, lässt Produktnamen unverändert.

**Hinweis:** Diese Option kann auch in der Plugin-Konfiguration als Standard gesetzt werden. CLI-Flags überschreiben die Konfiguration.

```bash
bin/console wsc:variant:update --product-numbers="tollesleder" --number-only
```

#### --sync (optional)
Führt die Verarbeitung synchron aus (blockierend), statt über die Message Queue.

```bash
bin/console wsc:variant:update --product-numbers="tollesleder" --sync
```

## Beispiele

### Beispiel 1: Einfache Variante

**Hauptprodukt:**
- Name: "Tolles Leder | Rindleder"
- Artikelnummer: "tollesleder"

**Variante mit Option:**
- Option: "rot"

**Ergebnis nach Update:**
- Name: "Tolles Leder | Rindleder rot"
- Artikelnummer: "tollesleder-rot"

### Beispiel 2: Variante mit mehreren Optionen

**Hauptprodukt:**
- Name: "Premium Lederjacke"
- Artikelnummer: "jacke-001"

**Variante mit Optionen:**
- Größe: "XL"
- Farbe: "Schwarz Metallic"

**Ergebnis nach Update:**
- Name: "Premium Lederjacke XL Schwarz Metallic"
- Artikelnummer: "jacke-001-xl-schwarz-metallic"

### Beispiel 3: Umlaute und Sonderzeichen

**Hauptprodukt:**
- Artikelnummer: "schuhe"

**Variante mit Option:**
- Farbe: "Grün"
- Größe: "42"

**Ergebnis nach Update:**
- Artikelnummer: "schuhe-gruen-42"

## Namenskonventionen

### Produktnamen
```
{Name des Hauptprodukts} {Option 1} {Option 2} ...
```

**Regeln:**
- Hauptproduktname wird 1:1 übernommen
- Optionsnamen werden mit Leerzeichen getrennt angehängt
- Bei mehreren Optionen werden diese in der Reihenfolge der Optionsgruppen verknüpft

### Artikelnummern
```
{Hauptprodukt-Nummer}-{option-1}-{option-2}-...
```

**Regeln:**
- Hauptprodukt-Artikelnummer wird 1:1 übernommen (Groß-/Kleinschreibung bleibt erhalten)
- Optionsnamen werden komplett in Kleinbuchstaben umgewandelt
- Leerzeichen werden durch Bindestriche (`-`) ersetzt
- Umlaute werden konvertiert:
  - ä → ae
  - ö → oe
  - ü → ue
  - ß → ss
  - Ä → ae
  - Ö → oe
  - Ü → ue

## Technische Details

### Systemanforderungen

- **Shopware:** 6.5.0 oder höher (getestet mit 6.5, 6.6, 6.7)
- **PHP:** 8.1, 8.2 oder 8.3
- **Extensions:** mbstring, json
- **Message Queue Worker:** Für asynchrone Verarbeitung im Admin

### Technologie-Stack

- **Data Abstraction Layer (DAL)**: Verwendet ausschließlich Shopware's DAL, kein direktes SQL
- **Repository Pattern**: Nutzt `product.repository` für alle Datenbankoperationen
- **Criteria & Filter**: Verwendet Criteria API für sichere und performante Datenbankabfragen
- **Associations**: Lädt Variantenoptionen über DAL-Associations
- **Twig Template Engine**: Flexible Namens- und Nummern-Generierung via Twig-Templates
- **Symfony Messenger**: Asynchrone Verarbeitung über Message Queue mit Progress-Tracking
- **Admin SDK**: Vue.js 3 basiertes Admin-Interface

### Dateistruktur

```
WSCPluginSWVariantUpdater/
├── .github/
│   ├── workflows/
│   │   └── ci.yml                          # CI/CD Pipeline
│   └── dependabot.yml                      # Dependency Updates
├── src/
│   ├── Command/
│   │   ├── UpdateVariantCommand.php        # CLI Command
│   │   └── DebugVariantCommand.php         # Debug Command
│   ├── Controller/
│   │   └── Administration/
│   │       └── VariantUpdateController.php # Admin API Controller
│   ├── Entity/
│   │   ├── VariantUpdateProgress/          # Progress-Tracking Entities
│   │   └── VariantUpdateLog/               # Log Entities
│   ├── Message/
│   │   ├── UpdateVariantsMessage.php       # Main Message
│   │   └── UpdateVariantsBatchMessage.php  # Batch Message
│   ├── MessageHandler/
│   │   ├── UpdateVariantsMessageHandler.php       # Main Handler
│   │   └── UpdateVariantsBatchMessageHandler.php  # Batch Handler
│   ├── Migration/
│   │   ├── Migration1234567890CreateProgressTable.php
│   │   └── Migration1234567891CreateLogTable.php
│   ├── Resources/
│   │   ├── app/
│   │   │   └── administration/
│   │   │       └── src/
│   │   │           └── module/wsc-variant-updater/  # Admin UI
│   │   └── config/
│   │       ├── config.xml                  # Plugin-Konfiguration
│   │       └── services.xml                # Service DI
│   ├── Service/
│   │   ├── BatchSizeCalculator.php         # Adaptive Batch-Sizing
│   │   ├── ProgressTracker.php             # Progress-Tracking
│   │   ├── TwigTemplateRenderer.php        # Twig-Templates
│   │   ├── VariantUpdateConfig.php         # Config Value Object
│   │   ├── VariantUpdateResult.php         # Result Value Object
│   │   └── VariantUpdateService.php        # Core Service
│   └── WSCPluginSWVariantUpdater.php       # Plugin-Basisklasse
├── .php-cs-fixer.dist.php                  # PHP-CS-Fixer Config
├── phpstan.neon                            # PHPStan Config
├── composer.json                           # Composer Config
└── README.md                               # Diese Datei
```

## CI/CD & Qualitätssicherung

Dieses Plugin verwendet eine umfassende CI/CD-Pipeline mit folgenden automatisierten Tests:

### Code-Qualität

- **PHP Syntax Check**: Prüft auf Syntaxfehler in allen PHP-Dateien
- **PHPStan (Level 8)**: Statische Code-Analyse für Type Safety und Best Practices
- **PHP-CS-Fixer**: Überprüfung des Code-Styles (PSR-12 + Symfony Standards)
- **Composer Validation**: Validiert composer.json Struktur

### Sicherheit & Kompatibilität

- **Security Audit**: Prüfung auf bekannte Sicherheitslücken in Dependencies
- **JSON Validation**: Syntax-Prüfung aller JSON-Konfigurationsdateien
- **Plugin Structure Validation**: Prüfung der Plugin-Struktur und services.xml
- **Multi-PHP Testing**: Tests gegen PHP 8.1, 8.2 und 8.3

### Automatisierung

- **Dependabot**: Automatische Dependency Updates (wöchentlich)
- **GitHub Actions**: Automatische Tests bei jedem Push und Pull Request
- **Automated Releases**: Automatische Plugin-ZIP Generierung bei Git Tags

### Übersicht aller Tests

| Test-Kategorie | Was wird geprüft | Tool | Lokal ausführen |
|----------------|------------------|------|-----------------|
| **PHP Syntax** | Syntax-Fehler in allen PHP-Dateien | `php -l` | Automatisch bei `composer test` |
| **Composer Validation** | composer.json Struktur und Korrektheit | `composer validate` | `composer validate --strict` |
| **Code Style (PSR-12)** | PHP Code-Style Standards | PHP-CS-Fixer | `composer cs-check` |
| **Code Style Fix** | Automatische Code-Style Korrektur | PHP-CS-Fixer | `composer cs-fix` |
| **Statische Code-Analyse** | Typ-Fehler, Logic-Fehler, Best Practices | PHPStan (Level 8) | `composer phpstan` |
| **Multi-Version Testing** | Kompatibilität mit PHP 8.1, 8.2, 8.3 | GitHub Actions Matrix | Nur in CI |
| **JSON Validation** | JSON-Syntax (composer.json, services.xml) | `jq`, `xmllint` | `jq empty composer.json` |
| **Dependency Security** | Bekannte Sicherheitslücken | `composer audit` | `composer audit` |
| **Outdated Dependencies** | Veraltete Pakete | `composer outdated` | `composer outdated` |
| **Plugin Structure** | Plugin-Struktur und Dateien | Custom Validation | Nur in CI |

### CI/CD Pipeline (GitHub Actions)

Die Pipeline führt 5 parallele Jobs aus:

#### 1. PHP Quality Checks
- ✅ PHP Syntax Check (alle .php Dateien)
- ✅ Composer Validation (`--strict --no-check-lock`)
- ✅ PHPStan Level 8 (findet Typ-Fehler, undefinierte Variablen, toten Code)
- ✅ PHP-CS-Fixer Dry-Run (PSR-12 + Symfony Standards)
- ✅ Matrix Testing: PHP 8.1, 8.2 und 8.3

#### 2. JSON Validation
- ✅ Validiert composer.json
- ✅ Prüft alle JSON-Dateien im Projekt

#### 3. Security Audit
- ✅ Composer Dependency Security Check
- ✅ Warnung bei veralteten Paketen

#### 4. Plugin Validation
- ✅ Plugin-Struktur-Validierung
- ✅ Prüft Vorhandensein erforderlicher Dateien
- ✅ services.xml XML-Syntax-Validierung

#### 5. Code Style Check
- ✅ PHP-CS-Fixer im Check-Modus
- ✅ Prüft PSR-12 und Symfony Code-Style Standards

### Lokale Tests ausführen

```bash
# Alle Tests ausführen
composer test

# Nur PHPStan
composer phpstan

# Nur Code-Style Check
composer cs-check

# Code-Style automatisch fixen
composer cs-fix

# Composer validieren
composer validate --strict

# Security Audit
composer audit

# Veraltete Dependencies prüfen
composer outdated
```

## Release Management & Paket-Generierung

### Automatische Releases

Das Plugin nutzt GitHub Actions für automatische Release-Generierung. Beim Erstellen eines Git-Tags wird automatisch:

1. Ein optimiertes Plugin-ZIP-Archiv erstellt
2. Ein GitHub Release mit dem ZIP als Download erstellt
3. Development-Dateien automatisch ausgeschlossen (via `.gitattributes`)

### Release erstellen

```bash
# Version Tag erstellen (z.B. v1.0.0)
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0

# GitHub Actions erstellt automatisch:
# - WSCPluginSWVariantUpdater-1.0.0.zip
# - GitHub Release mit Changelog
```

### .gitattributes für Export

Die `.gitattributes` Datei definiert, welche Dateien beim `git archive` bzw. bei der ZIP-Generierung ausgeschlossen werden:

**Ausgeschlossene Dateien/Ordner:**
- `.github/` - CI/CD Workflows
- `.php-cs-fixer.dist.php` - Code-Style Konfiguration
- `phpstan.neon` - PHPStan Konfiguration
- `README*.md` - Dokumentation (optional)
- `tests/` - Test-Dateien
- `var/` - Cache-Verzeichnisse
- `.gitignore`, `.gitattributes` - Git-Konfiguration
- IDE-Konfigurationen (`.idea/`, `.vscode/`)
- OS-Dateien (`.DS_Store`, `Thumbs.db`)

**Im finalen ZIP enthalten:**
- `src/` - Alle PHP-Quelldateien
- `composer.json` - Composer-Konfiguration
- `vendor/` - Production Dependencies (automatisch hinzugefügt)

### Manuelles ZIP erstellen

Falls Sie manuell ein ZIP erstellen möchten:

```bash
# Via git archive (berücksichtigt .gitattributes)
git archive --format=zip --prefix=WSCPluginSWVariantUpdater/ HEAD -o WSCPluginSWVariantUpdater.zip

# Production Dependencies installieren
composer install --no-dev --optimize-autoloader

# Vendor manuell hinzufügen
zip -r WSCPluginSWVariantUpdater.zip vendor/
```

## Entwicklung

### Code-Style

Das Plugin folgt PSR-12 und Symfony Coding Standards. Verwenden Sie PHP-CS-Fixer, um den Code-Style zu überprüfen:

```bash
# Code-Style prüfen
composer cs-check

# Code-Style automatisch korrigieren
composer cs-fix
```

### Statische Code-Analyse

PHPStan wird auf Level 8 ausgeführt:

```bash
composer phpstan
```

### Alle Tests ausführen

```bash
composer test
```

## Fehlerbehebung

### Plugin wird nicht gefunden
```bash
bin/console plugin:refresh
```

### Cache-Probleme
```bash
bin/console cache:clear
```

### Admin-Interface zeigt keine Änderungen
Nach Plugin-Updates müssen die Admin-Assets neu gebaut werden:

```bash
bin/console plugin:update WSCPluginSWVariantUpdater
# Baut automatisch Admin-Assets und leert Cache
```

Manuell:
```bash
bin/build-administration.sh
bin/console cache:clear
```

Dann im Browser: `Ctrl + Shift + R` (Hard-Reload)

### Message Queue Worker läuft nicht
Updates im Admin werden über die Message Queue verarbeitet. Starte den Worker:

```bash
# In separatem Terminal/Screen/tmux
bin/console messenger:consume async -vv

# Oder als Service (empfohlen für Produktion)
# Siehe: https://symfony.com/doc/current/messenger.html#deploying-to-production
```

### Progress-Bar zeigt keinen Fortschritt
Prüfe, ob der Message Queue Worker läuft:

```bash
bin/console messenger:stats
```

### Produkt nicht gefunden
Stellen Sie sicher, dass:
- Die Artikelnummer korrekt ist
- Es sich um ein Hauptprodukt handelt (keine Variante)
- Das Produkt im System existiert

### Keine Varianten gefunden
Das Hauptprodukt muss Varianten haben. Überprüfen Sie im Admin-Panel, ob Varianten für das Produkt angelegt sind.

### Twig-Template Fehler
Prüfe die Syntax deiner Templates in der Plugin-Konfiguration. Verfügbare Variablen:

**Für Namen:**
- `parentProduct.translated.name`
- `options` (Array von Options-Entities)

**Für Nummern:**
- `parentProduct.productNumber`
- `options|map(o => o.translated.name|lower|replace({' ': '-'}))|join('-')`

## Support & Beiträge

### Issues
Bitte melden Sie Bugs und Feature-Requests über GitHub Issues.

### Pull Requests
Beiträge sind willkommen! Bitte stellen Sie sicher, dass:
- Alle Tests durchlaufen (`composer test`)
- Der Code-Style eingehalten wird (`composer cs-fix`)
- PHPStan ohne Fehler durchläuft (`composer phpstan`)

## Lizenz

MIT License - Siehe LICENSE-Datei für Details.

## Changelog

### Version 2.0.0 (2025-12-25)
- ✨ **NEU:** Admin-Interface unter "Einstellungen → Erweiterungen"
- ✨ **NEU:** Twig-Template Support für flexible Namens- und Nummern-Generierung
- ✨ **NEU:** Asynchrone Verarbeitung via Message Queue mit Progress-Tracking
- ✨ **NEU:** `--all-products` Flag für CLI (mit Bestätigung)
- ✨ **NEU:** Automatischer Admin-Assets-Build bei `plugin:update`
- ✨ **NEU:** SystemConfig-Integration für Standard-Optionen
- 🔧 Improved: Service-Layer Architektur mit BatchSizeCalculator und ProgressTracker
- 🔧 Improved: Translation-Support für mehrsprachige Shops
- 🔧 Improved: Duplicate-Check für Produktnummern
- 📝 Improved: Umfangreiche Code-Dokumentation und PHPDoc

### Version 1.0.0
- Initiales Release
- Console Command für Varianten-Updates
- Unterstützung für Namen und Artikelnummern
- Dry-Run Modus
- Selektive Updates (--name-only, --number-only)
- Batch-Verarbeitung mehrerer Produkte
- Umfassende CI/CD Pipeline
- PHPStan Level 8
- PHP-CS-Fixer Integration
- Multi-PHP Version Support (8.1, 8.2, 8.3)
