# Tasks — eLeDia | exam2pdf

## Meta

Dieses Dokument ist das operative Zentrum. Hier starten alle Sessions.

---

## 🆕 New

_(Neue Ideen und Beobachtungen hier eintragen — noch unstrukturiert)_

---

## ❓ Clarification Needed

- **feat08**: Bulk-Download bei >500 TN → synchron oder async? (vorerst synchron, Backlog für async)
- **feat02**: Sollen E-Mail-Body und PDF-Dateiname konfigurierbar sein? (Backlog)

---

## 📋 Tasks

### task01 Plugin-Scaffold: Alle Grunddateien erstellen
Status: done
Feature: feat01, feat02, feat03, feat04, feat05, feat06
Ergebnis: Vollständiger MVP-Scaffold auf GitHub (19 Dateien, 2052 Zeilen)

---

### task02 CI grün: PHPUnit + Behat auf allen 4 Matrix-Zellen
Status: done
Feature: alle
Ergebnis: 38 PHPUnit-Tests, 7 Behat-Szenarien, PHPCS, PHPDoc, Grunt — alles grün (2026-04-12)

Fixes:
- $gradepass als Parameter an render_header() übergeben
- @javascript-Tag für admin_settings Checkbox-Szenario (BrowserKit-Limitation)
- Custom Behat step behat_local_eledia_exam2pdf.php (Observer-Trigger nach Generator-Step)
- submitterid im Event-other-Array
- PSR12 blank-line-after-brace Fix

---

### task09 Plugin-Name auf "eLeDia | exam2pdf" ändern
Status: open
Feature: alle
Priority: hoch

Schritte:
1. `$string['pluginname']` in lang/en auf "eLeDia | exam2pdf" ändern
2. settings.php: Heading-String aktualisieren
3. Alle DevFlow-Dokument-Header aktualisieren (bereits erledigt)
4. README.md aktualisieren

---

### task10 Neue Admin-Settings implementieren
Status: open
Feature: feat01, feat09, feat10, feat08
Priority: hoch

Neue Settings in settings.php:
- `pdfgeneration`: Select (auto/ondemand) — feat01
- `pdfscope`: Select (passed/all) — feat10
- `studentdownload`: Checkbox (default: 1) — feat09
- `bulkformat`: Select (zip/merged) — feat08

Schritte:
1. Strings in lang/en definieren
2. Settings in settings.php registrieren
3. Bestehende PHPUnit/Behat-Tests prüfen und ggf. anpassen
4. CI grün halten

---

### task11 Neue Capabilities registrieren
Status: open
Feature: feat06
Priority: hoch

Neue Capabilities in db/access.php:
- `local/eledia_exam2pdf:downloadall` — Editing Teacher, Manager
- `local/eledia_exam2pdf:generatepdf` — Editing Teacher, Manager

Bestehende prüfen und ggf. anpassen:
- `downloadown` bleibt
- `manage` → durch `downloadall` + `generatepdf` ersetzen? Oder behalten?

---

### task12 helper::is_in_pdf_scope() implementieren
Status: open
Feature: feat10
Priority: hoch

Zentrale Funktion die von Observer, Report-Seite und Student-Hook genutzt wird:
```php
public static function is_in_pdf_scope(\stdClass $attempt, array $config): bool
```
- Prüft ob Versuch `state === 'finished'`
- Wenn `pdfscope === 'passed'`: prüft Bestandsstatus
- Wenn `pdfscope === 'all'`: alle abgeschlossenen Versuche

PHPUnit-Tests für diese Funktion.

---

### task13 Observer auf Auto-Modus beschränken
Status: open
Feature: feat01
Priority: mittel

Observer prüft `get_config('local_eledia_exam2pdf', 'pdfgeneration')`:
- `'auto'` → wie bisher: PDF erzeugen
- `'ondemand'` → return early, keine PDF

Per-Quiz-Override beachten via `helper::get_effective_config()`.

---

### task14 report.php: Teacher Report-Seite
Status: open
Feature: feat07
Priority: hoch (Hauptfeature v2)

Schritte:
1. `report.php` erstellen mit Capability-Check (`downloadall`)
2. Quiz-Versuche laden (wie mod_quiz Grades-Tabelle)
3. Tabelle rendern: Name, E-Mail, Status, Datum, Dauer, Note, PDF-Button
4. PDF-Button: Download oder On-demand-Generierung
5. Navigation-Link via extend_settings_navigation() in lib.php
6. Behat-Test für Teacher-Report-Seite

---

### task15 zip.php: Bulk-Download
Status: open
Feature: feat08
Priority: mittel

Schritte:
1. `zip.php` um Format-Auswahl erweitern (ZIP vs. zusammengefügt)
2. Im On-demand-Modus: fehlende PDFs automatisch erzeugen
3. Nur gefilterte Versuche einschließen (Filter-Parameter aus report.php)
4. Behat-Test für Bulk-Download

---

### task16 Student-Download konfigurierbar machen
Status: open
Feature: feat09
Priority: mittel

Schritte:
1. `quiz_page_callbacks.php`: Config-Check auf `studentdownload`
2. Scope-Check via `helper::is_in_pdf_scope()`
3. On-demand-Modus: PDF bei Klick erzeugen (nicht nur vorhandene anzeigen)
4. Behat-Test anpassen

---

### task03 Behat-Tests: Report-Seite und Bulk-Download
Status: open
Feature: feat07, feat08

Szenarien:
- Teacher sieht Report-Seite mit PDF-Spalte
- Teacher lädt einzelnes PDF über Report-Seite herunter
- Teacher nutzt Bulk-Download
- Student sieht Report-Seite NICHT (fehlende Capability)

---

### task05 Manager-Übersicht → ersetzt durch report.php
Status: done (durch feat07/task14 abgedeckt)

---

### task07 Deutsche Sprachdatei
Status: open
Feature: alle
Priority: niedrig

---

### task08 PHPCS / Precheck vor Release
Status: done (in CI integriert, läuft bei jedem Push)

---

## 🔧 In Progress

_(Aktuell aktive Tasks hier eintragen)_

---

## 🔎 Verify After Deploy

- [ ] DB-Tabellen korrekt angelegt?
- [ ] Event-Observer registriert?
- [ ] Hook registriert? (Download-Button auf Review-Seite)
- [ ] Scheduled Task sichtbar in Admin?
- [ ] Admin-Settings-Seite erreichbar?
- [ ] Capabilities korrekt zugewiesen?
- [ ] Report-Seite erreichbar über Quiz-Navigation?
- [ ] Bulk-Download funktioniert?
- [ ] Student-Download ein/ausschaltbar?

---

## ✅ Done

- task01: Plugin-Scaffold vollständig auf GitHub gepusht (2025-04-09)
- task02: CI grün auf allen 4 Matrix-Zellen (2026-04-12)
- task05: Manager-Übersicht → durch report.php (feat07) ersetzt
- task08: PHPCS/Precheck in CI integriert

---

## Phasenplan

**Phase 1** ✅ CI grün — PHPUnit + Behat bestehen auf allen 4 Zellen.

**Phase 2** (jetzt) — Konzept v2 finalisiert. Plugin-Umbenennung + neue Admin-Settings + Capabilities.

**Phase 3** — report.php (feat07) + Bulk-Download (feat08) implementieren.

**Phase 4** — Student-Download konfigurierbar (feat09) + On-demand-Modus (feat01).

**Phase 5** — Smoketest + Plugin-Directory-Submission.

---

## Rules

- Neue Items immer unter "New" eintragen
- Tasks klein halten (ein klares Ergebnis)
- Abgeschlossene Tasks nach "Done" verschieben (nicht löschen)
- Immer Feature-Referenz angeben
