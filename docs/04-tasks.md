# Tasks — eLeDia | exam2pdf

## Meta

Operatives Arbeitsdokument für den aktuellen DevFlow-Stand.

---

## In Progress

### task20 Tabellen-Alignment in Quiz-Overview stabilisieren
Status: in progress
Feature: feat07
Priority: hoch

Ziel:
- Werte in Frage-Spalten (`Q.x`) in allen Themes sauber vertikal zentrieren.

Offene Schritte:
1. JS/CSS-Normalisierung gegen Theme Utility-Klassen (`align-top`) robust machen.
2. Gegen Boost + Classic prüfen.
3. Screenshots dokumentieren.

---

## Open

### task21 DevFlow-Dokumente in `/docs` konsolidieren
Status: done
Feature: alle
Priority: hoch

Ergebnis:
- DevFlow-Dateien nach `docs/` verschoben.
- README- und DevFlow-Verweise aktualisiert.
- Kerninhalte (Features, User-Doc, Dev-Doc, Quality) auf aktuellen Stand gebracht.

### task22 Optional: Async-Bulk-Download evaluieren
Status: open
Feature: feat08
Priority: niedrig

Ziel:
- Für sehr große Kurse prüfen, ob `zip.php` optional asynchron laufen soll.

### task23 CI-Lauf nach Doc/UX-Updates verifizieren
Status: open
Feature: alle
Priority: mittel

Ziel:
- GitHub Actions Lauf nach den aktuellen Änderungen vollständig prüfen.

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

---

## Verify After Deploy

- [ ] Actions-Spalte sichtbar und funktional in `mod/quiz/report.php?mode=overview`
- [ ] Download/Regenerieren funktionieren pro Versuch
- [ ] Bulk-Button lädt ZIP/merged gemäß Setting
- [ ] Student-Button erscheint nur wenn erlaubt
- [ ] PDF-Sprache und PDF-Fußzeile greifen korrekt
- [ ] Cleanup-Task löscht abgelaufene Dateien
