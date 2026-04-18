# Tasks — eLeDia | exam2pdf

## Meta

Operatives Arbeitsdokument für den aktuellen DevFlow-Stand.

---

## In Progress

_(keine)_

---

## Open

### task20 Tabellen-Alignment in Quiz-Overview stabilisieren
Status: open
Feature: feat07
Priority: mittel

Ziel:
- Werte in Frage-Spalten (`Q.x`) in allen Themes sauber vertikal zentrieren.

Offene Schritte:
1. JS/CSS-Normalisierung gegen Theme Utility-Klassen (`align-top`) robust machen.
2. Gegen Boost + Classic prüfen.
3. Screenshots dokumentieren.

### task22 Optional: Async-Bulk-Download evaluieren
Status: open
Feature: feat08
Priority: niedrig

Ziel:
- Für sehr große Kurse prüfen, ob `zip.php` optional asynchron laufen soll.

### task24 Release-ZIP mit vendor/ bauen und auf moodle.org hochladen
Status: open
Feature: alle
Priority: hoch

Ziel:
- Version bumpen, Tag setzen, ZIP mit `vendor/` (mPDF) bauen, auf moodle.org hochladen.
- Siehe `docs/dev-workflow.md` Abschnitt 7.

---

## Done

### task01 Grundgerüst + Kernfunktionalität
Status: done

### task02 CI-Setup und Basistests
Status: done

### task10 Neue Settings (Generation, Scope, Studentdownload, Bulkformat)
Status: done

### task11 Capabilities einführen und Runtime-Checks anpassen
Status: done

### task12 Scope-Logik zentralisieren (`helper::is_in_pdf_scope`)
Status: done

### task13 Observer auf Auto/On-demand korrekt begrenzen
Status: done

### task14 Separate `report.php` durch native Quiz-Overview-Integration ersetzen
Status: done

### task15 Bulk-Download ZIP/Merged stabilisieren
Status: done

### task16 Student-Download konfigurierbar + Backend-seitig abgesichert
Status: done

### task17 Regenerieren-Endpunkt (`regenerate.php`) ergänzen
Status: done

### task18 Mehrsprachigkeit fürs PDF (`pdflanguage`) ergänzen
Status: done

### task19 PDF-Fußzeile (global + quizlokal) ergänzen
Status: done

### task21 DevFlow-Dokumente nach `docs/` verschoben und aktualisiert
Status: done

### task23 PDF-Generator auf mPDF umschreiben
Status: done

Ergebnis:
- `generator.php` vollständig auf mPDF portiert (zuvor TCPDF).
- `composer.json` / `composer.lock` hinzugefügt (`mpdf/mpdf`).
- `vendor/` in `.gitignore`, muss beim ZIP-Export manuell dazugenommen werden.
- `bin/sync-mirror.sh` aktualisiert (schließt `vendor/` ein).

### task25 precheck.sh — Toolchain-Workarounds ergänzt
Status: done

Ergebnis:
- `mpci_phpdoc()` / `mpci_phpmd()`: laufen auf Temp-Copy ohne `vendor/` (mPDF würde false failures produzieren).
- `mpci_grunt()`: filtert selbstreferenzielle `no-dupe-feature-names`-Meldungen aus (gherkin-lint Toolchain-Bug).
- ANSI-Escape-Codes + CRLF werden vor dem Parsing entfernt.
- Precheck-Ergebnis: PASS:9 WARN:0 FAIL:0 SKIP:1.

---

## Verify After Deploy

- [ ] Actions-Spalte sichtbar und funktional in `mod/quiz/report.php?mode=overview`
- [ ] Download/Regenerieren funktionieren pro Versuch
- [ ] Bulk-Button lädt ZIP/merged gemäß Setting
- [ ] Student-Button erscheint nur wenn erlaubt
- [ ] PDF-Sprache und PDF-Fußzeile greifen korrekt
- [ ] Cleanup-Task löscht abgelaufene Dateien
