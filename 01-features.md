# Features — eLeDia | exam2pdf

## Product Overview

### Purpose
Teacher und Manager brauchen einen schnellen Weg, PDF-Prüfungsberichte für Quizversuche zu erzeugen und herunterzuladen — einzeln oder gebündelt. Studenten können optional ihr eigenes Zertifikat auf der Review-Seite laden.

### Core Concepts
- **Teacher-zentriert**: Hauptzugang über die Quiz-Results/Grades-Tabelle
- **PDF-Erzeugung** konfigurierbar: automatisch bei Abgabe oder on-demand bei Klick
- **PDF-Scope** konfigurierbar: nur bestandene oder alle abgeschlossenen Versuche
- **Bulk-Download**: alle Reports als ZIP oder zusammengefügtes PDF
- **Student-Self-Service**: optionale Download-Möglichkeit auf der Review-Seite
- **Per-Quiz-Konfiguration**: globale Defaults, Quiz-spezifisch überschreibbar

### Key Features
- feat01: PDF-Erzeugung (konfigurierbar: auto oder on-demand)
- feat02: Konfigurierbare Ausgabemodi (Download / E-Mail)
- feat03: Fragen und Antworten im PDF
- feat04: Per-Quiz-Konfiguration
- feat05: Automatische Bereinigung (Scheduled Task)
- feat06: Zugriffskontrolle und Berechtigungen
- feat07: Quiz-Results-Integration (Teacher-Ansicht)
- feat08: Bulk-Download (.pdf-Reports herunterladen)
- feat09: Student-Self-Service (optional, konfigurierbar)
- feat10: Konfigurierbarer PDF-Scope

### Constraints
- Keine Änderung der Quiz-Bewertungslogik
- Keine allgemeine DMS-Funktion
- Kein Einfluss auf Moodle-Fragetypen

---

## Features

---

### feat01 PDF-Erzeugung (konfigurierbar)

**Goal**
Für Quizversuche soll ein PDF-Prüfungsbericht erzeugt werden können. Der Zeitpunkt der Erzeugung ist konfigurierbar.

**Behavior**

*Modus "Bei Abgabe (automatisch)":*
- Trigger: `\mod_quiz\event\attempt_submitted`
- Prüfung: Fällt der Versuch in den konfigurierten PDF-Scope? (→ feat10)
- Falls ja: PDF erzeugen und im Moodle File System speichern
- Falls nein: keine PDF, kein DB-Eintrag
- Duplikatschutz: existiert bereits ein DB-Eintrag für diesen Versuch → Abbruch

*Modus "On-demand (bei Klick)":*
- Kein Observer-Trigger bei Abgabe
- PDF wird erst erzeugt wenn Teacher (feat07) oder Student (feat09) den Button klickt
- Nach Erzeugung: Datei im Moodle File System speichern + DB-Eintrag

*In beiden Modi:*
- Teacher kann über die Results-Tabelle (feat07) ein PDF (nach-)generieren
- Mehrere Versuche → je eine eigene PDF

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

**Admin-Setting**
- `pdfgeneration`: Select — "Bei Abgabe (automatisch)" | "On-demand (bei Klick)"
- Default: "Bei Abgabe (automatisch)"

**Decisions**
- PDF-Bibliothek: TCPDF (Moodle built-in)
- Speicherort: Moodle File API unter Quiz-Kursmodul-Kontext
- Dateiname: `quiz-{slug}-attempt-{n}-{datum}.pdf`

---

### feat02 Konfigurierbare Ausgabemodi

**Goal**
Die Ausgabe des PDFs soll global konfigurierbar und pro Quiz überschreibbar sein: Download, sofortiger E-Mail-Versand oder beides.

**Behavior**
- Globale Admin-Settings: Outputmode, E-Mail-Empfänger, E-Mail-Betreff, Aufbewahrungsfrist
- Pro Quiz: alle globalen Settings können überschrieben werden (leeres Feld = global-Default)
- Download: PDF wird im Moodle File System gespeichert → verfügbar über Results-Tabelle (feat07) und optional Review-Seite (feat09)
- E-Mail: wird sofort nach PDF-Erzeugung versendet (PDF als Anhang)
- Beide Modi können gleichzeitig aktiv sein

**E-Mail**
- Empfänger: Lernender (immer) + zusätzliche Adressen (kommagetrennt, konfigurierbar)
- Betreff: konfigurierbar, unterstützt `{quizname}` und `{username}`
- Versand: sofort nach erfolgreicher PDF-Erzeugung

**Decisions**
- E-Mail-Versand über `email_to_user()` (Moodle-nativ)
- Per-Quiz-Config in `local_eledia_exam2pdf_cfg`

---

### feat03 Fragen und Antworten im PDF

**Goal**
Das PDF soll neben den Kopfdaten auch alle Fragen des Quiz und die Antworten des Lernenden enthalten.

**Behavior**

*Offene Fragen (essay)*
- Frage + Freitextantwort des Lernenden
- Keine korrekte Lösung (nicht definierbar)

*Single Choice / Multiple Choice*
- Frage + gegebene Antwort(en) des Lernenden
- Markierung: Richtig / Falsch / Teilweise richtig
- Korrekte Lösung (falls konfiguriert)

*Short Answer / Numerical*
- Frage + gegebene Antwort
- Korrekte Lösung (falls konfiguriert)

*Korrekte Antworten*
- Konfigurierbar (global + per Quiz)

**Decisions**
- Antwortrendering über `question_attempt::get_response_summary()`
- Korrektheitsmarkierung über `question_attempt::get_state()`
- Korrekte Antworten: fragetyp-spezifische Logik in `pdf\generator::get_correct_answer_text()`

---

### feat04 Per-Quiz-Konfiguration

**Goal**
Alle globalen Admin-Settings sollen pro Quiz überschrieben werden können.

**Behavior**
- Settings-Seite erreichbar über Quiz-Navigation (nur für Trainer/Admin)
- Felder: alle Admin-Settings
- Leeres Feld = globalen Default übernehmen
- Speicherung in `local_eledia_exam2pdf_cfg` (name/value-Paare)

**Decisions**
- Settings-Seite als eigenständige PHP-Seite (`quizsettings.php`), eingebunden über `extend_settings_navigation()` in lib.php

---

### feat05 Automatische Bereinigung

**Goal**
Gespeicherte PDFs sollen nach Ablauf der konfigurierten Aufbewahrungsfrist automatisch gelöscht werden.

**Behavior**
- Aufbewahrungsfrist in Tagen (ab Versuch), konfigurierbar
- 0 = unbegrenzt
- Scheduled Task läuft täglich um 02:30 Uhr
- Löscht: Datei im Moodle File System + DB-Eintrag

---

### feat06 Zugriffskontrolle und Berechtigungen

**Goal**
Rollenbasierter Zugriff auf PDF-Erzeugung und -Download.

**Behavior**
- Lernende: nur eigene PDFs, nur wenn Student-Self-Service aktiv (feat09)
- Trainer/Admin (`downloadall`): alle PDFs eines Quiz über Results-Tabelle
- PDF-Erzeugung (`generatepdf`): nur Trainer/Admin (für On-demand + Regenerierung)
- Pluginfile-Zugriff: Capability-Check in `local_eledia_exam2pdf_pluginfile()`

**Capabilities**
- `local/eledia_exam2pdf:downloadown` — Student, Teacher
- `local/eledia_exam2pdf:downloadall` — Editing Teacher, Manager
- `local/eledia_exam2pdf:generatepdf` — Editing Teacher, Manager
- `local/eledia_exam2pdf:configure` — Editing Teacher, Manager

---

### feat07 Quiz-Results-Integration (NEU — Hauptfeature v2)

**Goal**
Teacher und Manager sollen in der Quiz-Results/Grades-Tabelle direkt PDF-Reports pro Teilnehmer sehen und herunterladen können.

**Behavior**

*"What to include"-Sektion:*
- Neue Checkbox-Option "PDF-Reports anzeigen" im Filter-Bereich der Grades-Seite
- Wenn aktiviert: zusätzliche Spalte ".pdf" in der Ergebnistabelle

*Per-TN-Button in der Tabelle:*
- Jede Zeile (= ein Versuch) bekommt ein PDF-Icon/Button
- Icon-Varianten:
  - PDF vorhanden → Download-Icon (aktiv, Klick = sofortiger Download)
  - PDF nicht vorhanden + Auto-Modus → Kein Icon (PDF noch nicht generiert, z.B. nicht bestanden bei Scope "nur bestanden")
  - PDF nicht vorhanden + On-demand-Modus → Generierungs-Icon (Klick = PDF erzeugen + downloaden)
- Nur sichtbar mit Capability `downloadall`

*Technische Integration (Entscheidung: Option C):*
- Eigene Report-Seite `/local/eledia_exam2pdf/report.php`
- Verlinkt aus der Quiz-Navigation via `extend_settings_navigation()` (neben "Grades")
- Zeigt dieselben Versuchsdaten wie die Grades-Tabelle, plus PDF-Spalte und Bulk-Button
- Volle Kontrolle über Tabelle und UI, keine Abhängigkeit von internen Quiz-Hooks
- Kompatibel mit Moodle 4.5 LTS bis 5.1

*Verworfene Optionen:*
- Option A (Quiz-Report-Subplugin): Braucht ein separates Plugin, kann nicht in `local_` leben
- Option B (Hook in Grades-Tabelle): JS-basierte Spalteninjektion zu fragil bei Moodle-Upgrades

---

### feat08 Bulk-Download (NEU)

**Goal**
Teacher soll alle PDF-Reports der aktuellen Ansicht mit einem Klick herunterladen können.

**Behavior**

*Button-Platzierung:*
- Neben dem bestehenden "Download"-Button (CSV) auf der Grades-Seite
- Button-Label: ".pdf-Reports herunterladen"
- Nur sichtbar mit Capability `downloadall`

*Bulk-Format (konfigurierbar):*
- Option "ZIP": Ein ZIP-Archiv mit einem PDF pro Versuch, Dateinamen enthalten TN-Name und Versuchsnummer
- Option "Zusammengefügtes PDF": Alle Berichte in einem einzigen PDF-Dokument hintereinander
- Admin-Setting: `bulkformat` — "ZIP mit einzelnen PDFs" | "Ein zusammengefügtes PDF"

*Scope:*
- Generiert/sammelt nur die aktuell angezeigten (gefilterten) Versuche
- Im On-demand-Modus: fehlende PDFs werden bei Bulk-Download automatisch erzeugt

**Non-goals**
- Kein asynchroner Hintergrund-Job (synchrone Erzeugung, ggf. mit Progress-Bar)

---

### feat09 Student-Self-Service (NEU — aus v1 übernommen, jetzt optional)

**Goal**
Studenten können optional ihr eigenes PDF auf der Quiz-Review-Seite herunterladen.

**Behavior**
- Admin-Setting: `studentdownload` — Checkbox "Student darf herunterladen" (Default: Ja)
- Wenn aktiviert UND Versuch im PDF-Scope (feat10):
  - Download-Button "Download certificate" auf der Quiz-Review-Seite
  - Hook: `before_footer_html_generation` (wie bisher implementiert)
- Wenn deaktiviert: kein Button auf der Review-Seite
- Wenn Versuch nicht im PDF-Scope: deaktivierter Button mit Hinweis

**Decisions**
- Bestehende Implementierung (hook/quiz_page_callbacks.php) bleibt, wird durch Config-Check erweitert

---

### feat10 Konfigurierbarer PDF-Scope (NEU)

**Goal**
Administratoren sollen festlegen können, für welche Versuche PDFs erzeugt/angeboten werden.

**Behavior**
- Admin-Setting: `pdfscope` — Select "Nur bestandene Versuche" | "Alle abgeschlossenen Versuche"
- Default: "Nur bestandene Versuche"
- Wirkt sich aus auf:
  - Observer (feat01, Auto-Modus): Prüfung ob Versuch im Scope
  - Results-Tabelle (feat07): welche Zeilen ein PDF-Icon bekommen
  - Student-Review (feat09): welche Versuche den Button zeigen
  - Bulk-Download (feat08): welche Versuche eingeschlossen werden

**Decisions**
- Zentrale Prüffunktion in `helper::is_in_pdf_scope(attempt, config)` die von allen Features genutzt wird

---

## Admin-Settings Übersicht (v2)

| Setting | Typ | Default | Feature |
|---|---|---|---|
| pdfgeneration | Select: Auto / On-demand | Auto | feat01 |
| pdfscope | Select: Nur bestanden / Alle abgeschlossen | Nur bestanden | feat10 |
| studentdownload | Checkbox | Ja | feat09 |
| bulkformat | Select: ZIP / Zusammengefügtes PDF | ZIP | feat08 |
| outputmode | Select: Download / Email / Beides | Download | feat02 |
| retentiondays | Text (Tage) | 365 | feat05 |
| show_score | Checkbox | Ja | feat01 |
| show_passgrade | Checkbox | Ja | feat01 |
| show_percentage | Checkbox | Ja | feat01 |
| show_timestamp | Checkbox | Ja | feat01 |
| show_duration | Checkbox | Ja | feat01 |
| show_attemptnumber | Checkbox | Ja | feat01 |
| showcorrectanswers | Checkbox | Nein | feat03 |
| emailrecipients | Text | (leer) | feat02 |
| emailsubject | Text | Template | feat02 |
