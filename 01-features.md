# Features — local_eledia_exam2pdf

## Product Overview

### Purpose
Compliance-relevante Moodle-Quizze erfordern einen nachvollziehbaren, exportierbaren Nachweis über bestandene Versuche. Dieses Plugin erzeugt automatisch ein PDF-Dokument nach jedem bestandenen Quizversuch — mit verbindlichen Nachweisdaten und vollständiger Frage/Antwort-Dokumentation.

### Core Concepts
- **PDF-Erzeugung** ausgelöst durch Quiz-Event (attempt_submitted)
- **Moodle File API** für sichere, kontextgebundene Dateispeicherung
- **Konfigurierbare Ausgabe**: Download, E-Mail oder beides
- **Aufbewahrungsfrist** mit automatischer Bereinigung

### Key Features
- feat01: PDF-Erzeugung nach bestandenem Versuch
- feat02: Konfigurierbare Ausgabemodi (Download / E-Mail)
- feat03: Fragen und Antworten im PDF
- feat04: Per-Quiz-Konfiguration
- feat05: Automatische Bereinigung (Scheduled Task)
- feat06: Zugriffskontrolle und Berechtigungen

### Constraints
- Keine Änderung der Quiz-Bewertungslogik
- Keine allgemeine DMS-Funktion
- Kein Einfluss auf Moodle-Fragetypen

---

## Features

---

### feat01 PDF-Erzeugung nach bestandenem Versuch

**Goal**
Nach jedem bestandenen Quizversuch soll automatisch genau eine PDF-Datei erzeugt werden, die als Compliance-Nachweis dient.

**Behavior**
- Trigger: `\mod_quiz\event\attempt_submitted`
- Prüfung: Ist der Versuch bestanden? (sumgrades ≥ gradepass)
- Falls bestanden: PDF erzeugen und im Moodle File System speichern
- Falls nicht bestanden: keine PDF, kein Eintrag in der DB
- Mehrere bestandene Versuche → je eine eigene PDF
- Duplikatschutz: existiert bereits ein DB-Eintrag für diesen Versuch → Abbruch

**PDF-Kopfbereich (Pflichtfelder)**
- Name des Lernenden
- Quiz-Name
- Bestanden: Ja/Nein

**PDF-Kopfbereich (optionale Felder, konfigurierbar)**
- Rohpunkte / Gesamtpunkte
- Bestehensgrenze
- Prozentzahl
- Zeitstempel des Versuchsendes
- Dauer des Versuchs
- Versuchsnummer

**Non-goals**
- Keine PDF für nicht bestandene Versuche (für interne Nutzung noch offen, → Open Question)
- Keine Änderung der Quizbewertung
- Keine externe Archivfunktion

**Decisions**
- PDF-Bibliothek: TCPDF (Moodle built-in, keine externe Abhängigkeit)
- Speicherort: Moodle File API unter Quiz-Kursmodul-Kontext
- Dateiname: `quiz-{slug}-attempt-{n}-{datum}.pdf`

**Open Questions**
- Soll intern auch für nicht bestandene Versuche eine PDF erzeugt werden (admin-only)?

---

### feat02 Konfigurierbare Ausgabemodi

**Goal**
Die Ausgabe des PDFs soll global konfigurierbar und pro Quiz überschreibbar sein: Download, sofortiger E-Mail-Versand oder beides.

**Behavior**
- Globale Admin-Settings: Outputmode, E-Mail-Empfänger, E-Mail-Betreff, Aufbewahrungsfrist
- Pro Quiz: alle globalen Settings können überschrieben werden (leeres Feld = global-Default übernehmen)
- Download: PDF wird im Moodle File System gespeichert → Download-Button erscheint auf Quiz-Review-Seite
- E-Mail: wird sofort nach PDF-Erzeugung versendet (PDF als Anhang)
- Beide Modi können gleichzeitig aktiv sein

**E-Mail**
- Empfänger: Lernender (immer) + zusätzliche Adressen (kommagetrennt, konfigurierbar)
- Betreff: konfigurierbar, unterstützt `{quizname}` und `{username}`
- Versand: sofort nach erfolgreicher PDF-Erzeugung

**Non-goals**
- Keine Verwaltung externer E-Mail-Aufbewahrung
- Kein E-Mail-Scheduling / verzögerter Versand

**Decisions**
- E-Mail-Versand über `email_to_user()` (Moodle-nativ)
- Per-Quiz-Config in separater DB-Tabelle `local_eledia_exam2pdf_cfg`

---

### feat03 Fragen und Antworten im PDF

**Goal**
Das PDF soll neben den Kopfdaten auch alle Fragen des Quiz und die Antworten des Lernenden enthalten — strukturiert nach Fragetyp.

**Behavior**

*Offene Fragen (essay)*
- Frage + Freitextantwort des Lernenden
- Keine korrekte Lösung (nicht definierbar)

*Single Choice / Multiple Choice*
- Frage + gegebene Antwort(en) des Lernenden
- Markierung: Richtig / Falsch / Teilweise richtig
- Korrekte Lösung (falls konfiguriert): Anzeige der richtigen Option(en)

*Short Answer / Numerical*
- Frage + gegebene Antwort
- Korrekte Lösung (falls konfiguriert)

*Korrekte Antworten*
- Konfigurierbar (global + per Quiz)
- Falls keine korrekte Lösung hinterlegt: nur vorhandene Daten dokumentieren

**Non-goals**
- Keine Änderung von Moodle-Fragetypen
- Keine Anzeige von Hinweisen oder Feedback-Texten

**Decisions**
- Antwortrendering über `question_attempt::get_response_summary()`
- Korrektheitsmarkierung über `question_attempt::get_state()`
- Korrekte Antworten: fragetyp-spezifische Logik in `pdf\generator::get_correct_answer_text()`

---

### feat04 Per-Quiz-Konfiguration

**Goal**
Alle globalen Admin-Settings sollen pro Quiz überschrieben werden können, ohne die globalen Defaults zu verändern.

**Behavior**
- Settings-Seite erreichbar über Quiz-Navigation (nur für Trainer/Admin)
- Felder: alle Admin-Settings
- Leeres Feld = globalen Default übernehmen
- Speicherung in `local_eledia_exam2pdf_cfg` (name/value-Paare)

**Non-goals**
- Keine Vererbung über Kurs-Ebene

**Decisions**
- Settings-Seite als eigenständige PHP-Seite (`quizsettings.php`), eingebunden über `extend_settings_navigation()` in lib.php

---

### feat05 Automatische Bereinigung

**Goal**
Gespeicherte PDFs sollen nach Ablauf der konfigurierten Aufbewahrungsfrist automatisch gelöscht werden.

**Behavior**
- Aufbewahrungsfrist in Tagen (ab bestandenem Versuch), konfigurierbar
- 0 = unbegrenzt
- Scheduled Task läuft täglich um 02:30 Uhr
- Löscht: Datei im Moodle File System + DB-Eintrag

**Non-goals**
- Kein manuelles Löschen einzelner PDFs durch Trainer (Backlog)

---

### feat06 Zugriffskontrolle und Berechtigungen

**Goal**
Nur berechtigte Nutzer sollen PDFs sehen und herunterladen können.

**Behavior**
- Lernende: nur eigene PDFs, nur nach bestandenem Versuch, nur wenn Outputmode Download aktiv
- Trainer/Admin (Capability `manage`): alle PDFs eines Quiz
- Download-Button: aktiv (Versuch bestanden + PDF vorhanden) oder deaktiviert mit Hinweis
- Pluginfile-Zugriff: Capability-Check in `local_eledia_exam2pdf_pluginfile()`

**Capabilities**
- `local/eledia_exam2pdf:downloadown` — Student, Teacher
- `local/eledia_exam2pdf:manage` — Editing Teacher, Manager
- `local/eledia_exam2pdf:configure` — Editing Teacher, Manager

**Non-goals**
- Keine rollenbasierte Sichtbarkeit für nicht bestandene Versuche (Lernende)
