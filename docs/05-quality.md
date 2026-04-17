# Quality — eLeDia | exam2pdf

## Meta

Dieses Dokument enthält Qualitätsstatus, bekannte Risiken und Testfokus.

---

## Aktueller Qualitätsstatus

Stand: 2026-04-17

- Kernflows funktionieren lokal:
  - PDF-Erzeugung (auto + on-demand)
  - Download/Regenerieren
  - Bulk-Download (ZIP/merged)
  - Quiz-spezifische Overrides
- Offener Schwerpunkt:
  - konsistente visuelle Ausrichtung in der Quiz-Overview-Tabelle über Themes hinweg

---

## Bekannte Bugs / Risiken

### bug10 Vertikale Ausrichtung in Frage-Spalten variiert nach Theme
Priority: P2
Status: open
Feature: feat07

Symptom:
- Werte wie `10.00` in `Q.x`-Spalten sind nicht in allen Fällen sauber mittig zur Zeile ausgerichtet.

Nächster Schritt:
- zusätzliche CSS/JS-Normalisierung und visuelle Prüfung in Boost/Classic.

---

## Tests — Automatisiert

### PHPUnit
Status: vorhanden

Abgedeckte Bereiche:
- Observer
- Helper
- Cleanup-Task
- Privacy Provider

### Behat
Status: vorhanden

Abgedeckte Bereiche:
- Admin Settings
- Download-Flows

Hinweis:
- Nach größeren UI-Anpassungen ist ein erneuter kompletter CI-Lauf verpflichtend.

---

## Tests — Manuell (Smoke)

- [ ] Teacher: Actions-Spalte Download/Regenerieren
- [ ] Teacher: Bulk ZIP/merged
- [ ] Student: Download-Auswertung (aktiv/deaktiviert)
- [ ] On-demand: Erzeugung beim Klick
- [ ] Manuelles Grading: Regeneration nach Abschluss
- [ ] PDF-Layout (Logo, Header-Tabelle, Navigation, Punkte)

---

## Qualitätsziele

- [ ] CI vollständig grün nach aktuellem Stand
- [ ] Offener UI-Bug `bug10` geschlossen
- [ ] Smoke-Checks für Moodle 4.5/5.x dokumentiert

