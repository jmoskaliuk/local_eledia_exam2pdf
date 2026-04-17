# Developer Documentation — eLeDia | exam2pdf

## Architekturüberblick

Das Plugin ist ein `local_`-Plugin und integriert sich in bestehende Moodle-Quizseiten.
Der primäre UI-Pfad für Trainer läuft über den Standard-Quiz-Overview-Report.

```text
Teacher/Manager:
mod/quiz/report.php?mode=overview
  -> Hook-Injektion (before_footer_html_generation)
  -> Actions-Spalte pro Versuch (Download / Regenerieren)
  -> exam2pdf Bulk-Button (ZIP oder merged)

Student:
mod/quiz/review.php?attempt=...
  -> Hook-Injektion
  -> "Download Auswertung" (optional, capability + setting + scope)
```

PDF-Erzeugung erfolgt:
- automatisch via Event-Observer (`attempt_submitted`) oder
- on-demand via Endpunkte (`download.php`, `regenerate.php`, `zip.php`).

---

## Dateistruktur

```text
local/eledia_exam2pdf/
├── classes/
│   ├── helper.php
│   ├── observer.php
│   ├── hook/quiz_page_callbacks.php
│   ├── form/quizsettings.php
│   ├── pdf/generator.php
│   ├── privacy/provider.php
│   └── task/cleanup_expired_pdfs.php
├── db/
│   ├── access.php
│   ├── events.php
│   ├── hooks.php
│   ├── install.xml
│   ├── tasks.php
│   └── upgrade.php
├── lang/de/local_eledia_exam2pdf.php
├── lang/en/local_eledia_exam2pdf.php
├── download.php
├── regenerate.php
├── zip.php
├── quizsettings.php
├── settings.php
├── lib.php
└── version.php
```

---

## Hauptkomponenten

### `classes/observer.php`

- Observer für:
  - `mod_quiz\event\attempt_submitted`
  - `mod_quiz\event\question_manually_graded`
- zentrale Methode: `ensure_pdf_for_attempt(...)`
- berücksichtigt:
  - Generation-Mode (`auto`/`ondemand`)
  - Scope (`passed`/`all`)
  - Duplikatschutz
  - Regenerierung
  - optional E-Mail-Versand

### `classes/hook/quiz_page_callbacks.php`

- Hook: `before_footer_html_generation`
- injiziert abhängig vom Seitentyp:
  - **Overview-Report**:
    - Actions-Spalte in Tabelle
    - Download-/Regenerieren-Buttons pro Versuch
    - Bulk-Button (ZIP/merged)
  - **Review-Seite**:
    - Download-Button für Lernende (falls erlaubt)

### `classes/helper.php`

- Merge globaler + quizlokaler Konfiguration (`get_effective_config`)
- capability helper (`downloadall`, `downloadown`, `generatepdf`)
- Datei-Lookups und Download-URLs
- Scope-Prüfung (`is_in_pdf_scope`)

### `classes/pdf/generator.php`

- TCPDF-basierte Generierung
- Sprache erzwungen über konfiguriertes PDF-Language-Setting
- Header mit Pflicht-/Optionalfeldern
- Logo aus Moodle-Sitelogo (`core_admin/logo` bzw. `logocompact`)
- Fragebereich mit:
  - Teilnehmerantwort
  - Korrektantwort (optional)
  - Punkte pro Frage
  - Bewertungskommentar (optional)
- Test-Navigation/Überblick je Frage im PDF
- optionaler PDF-Footertext

---

## HTTP-Endpunkte

### `download.php`

- lädt vorhandenes PDF oder erzeugt on-demand
- owner/downloadall/capability checks
- berücksichtigt `studentdownload` und Scope

### `regenerate.php`

- explizite Neu-Generierung für einen Versuch
- nur für Rollen mit `generatepdf`
- Rücksprung auf die aufrufende Seite via `returnurl`

### `zip.php`

- Bulk-Download für ein Quizmodul (`cmid`)
- Ausgabeformat je Setting:
  - ZIP mit Einzeldateien
  - merged PDF

### `quizsettings.php`

- Quiz-spezifische Overrides (zusätzlich zu globalen Settings)
- eigene Seite in der Quiz-Navigation

---

## Datenmodell

### Tabelle `local_eledia_exam2pdf`

Speichert pro erzeugtem PDF u. a.:
- `quizid`, `cmid`, `attemptid`, `userid`
- `timecreated`, `timeexpires`
- `contenthash`

### Tabelle `local_eledia_exam2pdf_cfg`

- quizspezifische Overrides als key/value je `quizid`
- wirksame Werte werden in `helper::get_effective_config()` zusammengeführt

---

## Hook- und Event-Registrierung

- `db/events.php`:
  - `attempt_submitted`
  - `question_manually_graded`
- `db/hooks.php`:
  - `\core\hook\output\before_footer_html_generation`
  - Callback: `quiz_page_callbacks::inject_footer_html`

---

## Bekannte Punkte / Grenzen

- Tabellen-Layout hängt teilweise von Moodle/Theme-CSS ab; Actions-Spalte wird per JS normalisiert.
- Sehr große Bulk-Downloads bleiben synchron; Async-Variante ist ein möglicher Ausbau.
- CI-/Smoketest-Status wird in `05-quality.md` gepflegt.
