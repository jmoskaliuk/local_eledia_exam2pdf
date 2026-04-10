# Tasks — local_eledia_exam2pdf

## Meta

Dieses Dokument ist das operative Zentrum. Hier starten alle Sessions.

---

## 🆕 New

_(Neue Ideen und Beobachtungen hier eintragen — noch unstrukturiert)_

---

## ❓ Clarification Needed

- **feat01**: Soll intern auch für nicht bestandene Versuche eine PDF erzeugt werden (nur für Admins sichtbar)? → Noch nicht entschieden
- **feat02**: Sollen E-Mail-Body und PDF-Dateiname konfigurierbar sein?
- **feat04**: Sollen optionale PDF-Felder global oder pro Quiz konfiguriert werden? (Aktuell: global mit per-Quiz-Override)
- **feat06**: Gibt es eine eigene Admin/Trainer-Übersicht über alle PDFs eines Quiz? (Backlog)

---

## 📋 Tasks

### task01 Plugin-Scaffold: Alle Grunddateien erstellen
Status: done
Feature: feat01, feat02, feat03, feat04, feat05, feat06
Ergebnis: Vollständiger MVP-Scaffold auf GitHub (19 Dateien, 2052 Zeilen)

---

### task02 Lokales Deployment und Smoketest
Status: open
Feature: feat01

Schritte:
1. Plugin nach `local/eledia_exam2pdf/` in lokales Moodle-Dev deployen
2. Moodle-Upgrade durchführen: DB-Tabellen prüfen
3. Test-Quiz anlegen mit Bestehensgrenze
4. Versuch durchführen (bestanden)
5. Prüfen: PDF erzeugt? DB-Eintrag vorhanden? Download-Button sichtbar?

Erwartetes Ergebnis: PDF in Moodle File System, Download-Button aktiv auf Review-Seite

---

### task03 Behat-Tests: Happy Path
Status: open
Feature: feat01, feat02

Szenarien:
- Lernender besteht Quiz → PDF-Button ist sichtbar und aktiv
- Lernender besteht Quiz nicht → Button deaktiviert mit Hinweis
- Mehrere bestandene Versuche → je ein Button / je ein PDF

---

### task04 PHPUnit-Tests: generator.php und helper.php
Status: open
Feature: feat01, feat03

Testfälle:
- `generator::generate()` liefert non-empty string (PDF bytes)
- `helper::get_effective_config()` merged korrekt (global vs. per-Quiz-Override)
- `helper::get_effective_config()` mit leerem Override → globaler Default

---

### task05 Manager-Übersicht (manage.php)
Status: open
Feature: feat06

Beschreibung:
- Seite für Trainer/Admin: alle PDFs eines Quiz tabellarisch
- Spalten: Lernender, Versuch, Datum, Ablauf, Download-Button
- Erreichbar über Quiz-Navigation (neben quizsettings.php)

---

### task06 Konfigurierbare E-Mail-Templates
Status: open
Feature: feat02

Beschreibung:
- E-Mail-Body als konfigurierbarer Text (Admin-Setting + per-Quiz-Override)
- Platzhalter: `{fullname}`, `{quizname}`, `{date}`, `{score}`, `{percentage}`
- Dateiname des PDF-Anhangs konfigurierbar

---

### task07 Deutsche Sprachdatei
Status: open
Feature: alle

Beschreibung:
- `lang/de/local_eledia_exam2pdf.php` erstellen
- Alle Strings aus der EN-Datei übersetzen

---

### task08 PHPCS / Precheck vor erstem Release
Status: open
Feature: alle

Schritte:
1. PHPCS mit Moodle-Coding-Standards ausführen
2. PHPDoc auf alle public methods prüfen
3. `grunt` für AMD/CSS (falls JS hinzukommt)
4. Moodle Plugin Precheck Tool ausführen

---

## 🔧 In Progress

_(Aktuell aktive Tasks hier eintragen)_

---

## 🔎 Verify After Deploy

- [ ] DB-Tabellen korrekt angelegt? (`local_eledia_exam2pdf`, `local_eledia_exam2pdf_cfg`)
- [ ] Event-Observer registriert? (`attempt_submitted` → observer)
- [ ] Hook registriert? (Download-Button erscheint auf Review-Seite)
- [ ] Scheduled Task sichtbar in Admin → Scheduled Tasks?
- [ ] Admin-Settings-Seite erreichbar?
- [ ] Capabilities korrekt zugewiesen?

---

## ✅ Done

- task01: Plugin-Scaffold vollständig auf GitHub gepusht (2025-04-09)

---

## Rules

- Neue Items immer unter „New" eintragen
- Tasks klein halten (ein klares Ergebnis)
- Abgeschlossene Tasks nach „Done" verschieben (nicht löschen)
- Immer Feature-Referenz angeben
