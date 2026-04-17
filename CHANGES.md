# Changelog

Alle nennenswerten Änderungen an `local_eledia_exam2pdf` werden in diesem
Dokument festgehalten. Format: [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [0.5.1] - 2026-04-17

CI-Fix-Patch für 0.5.0: committed die bisher nie gepushten AMD-`build`-
Artefakte und richtet die Behat-Features wieder am umbenannten
Download-Button-String aus. Keine funktionalen Änderungen für Endnutzer.

### Fixed
- `amd/build/*.min.js` — die kompilierten AMD-Module (`quiz_overview_actions`,
  `review_download_button`, `report_section_button`) waren in 0.5.0 nur als
  ES6-Quellen unter `amd/src/` committed. Moodles Grunt-Amd-Task kompiliert
  `src/` → `build/` und prüft anschließend per `git diff`, dass der Baum
  clean ist — fehlende Build-Artefakte ließen darum alle vier Matrix-Zellen
  am Grunt-Step scheitern. Runtime-Effekt war identisch: im Dev-Moodle
  (`$CFG->cachejs = false`) konnten RequireJS die ES6-Quellen nicht laden
  (`No define call for …/quiz_overview_actions`), wodurch die Actions-Spalte
  im Report-View, der ZIP-Bulk-Button und der neu positionierte
  Download-Button auf der Review-Seite unsichtbar blieben.
- `tests/behat/download_button.feature` — nach dem Rename von
  `$string['download_button']` auf `'Download evaluation'` (in 0.4.x) wurden
  drei Szenarien mit veralteter Asserting-Text `"Download certificate"`
  fällig. Die Assertions `I should see`, `I should not see`, und
  `"…" "link" should exist` auf den neuen String angepasst.

## [0.5.0] - 2026-04-17

Submission-Readiness-Release: räumt die letzten Plugin-Directory-Blocker auf
(Inline-JS → AMD, FontAwesome-4 → `pix_icon`, Legacy-`before_footer`-Callback
entfernt) und erzwingt Inherit-Semantik für die Advcheckbox-Felder der
Quiz-Einstellungen.

### Added
- AMD-Module `local_eledia_exam2pdf/quiz_overview_actions`,
  `local_eledia_exam2pdf/review_download_button` und
  `local_eledia_exam2pdf/report_section_button` — lösen den Inline-JS per
  `$PAGE->requires->js_init_code()` komplett ab. `amd/src/` enthält die
  ES6-Quellen, `amd/build/` die AMD-`define()`-Builds. Das
  `quiz_overview_actions`-Modul injiziert zusätzlich `<style>`-Regeln, die die
  vertikale Zentrierung der Grade-Zelle und der Icon-only-Buttons auch nach
  mod_quiz-Redraws erhalten.
- `helper::save_quiz_config_with_inheritance()` + `helper::BOOL_KEYS` — neue
  Save-Path für die Per-Quiz-Einstellungen. Vergleicht jeden Advcheckbox-Wert
  gegen den globalen Default und legt nur dann eine Override-Zeile an, wenn
  sich beide unterscheiden. Verhindert, dass ein schlichter "Speichern"-Klick
  den aktuellen globalen Default als permanenten Per-Quiz-Override einfriert
  und die Vererbungskette später stillschweigend kippt.
- Unit-Tests für die drei Cap-Helper und den Inheritance-Save
  (15 neue Testfälle in `tests/helper_test.php`) — inkl. Regression-Guard
  "Manager mit `:manage`, aber ohne `:downloadall`, darf trotzdem `:downloadown`"
  und Test für "Global-Default-Flip nach Match-Save greift sofort".
- CI/CD-Pipeline aus `moodle-cicd`-Skill-Baseline: GitHub Actions Matrix
  (`MOODLE_405_STABLE` × pgsql, `MOODLE_500_STABLE` × pgsql, `MOODLE_501_STABLE`
  × pgsql, `MOODLE_501_STABLE` × mariadb).
- Lokaler Precheck `bin/precheck.sh` via `docker exec` gegen
  moodle-docker-Container.
- Release-Pipeline `bin/release.sh` + `.github/workflows/release.yml` für
  Tag-getriggerte GitHub-Releases.
- `.gitattributes` mit `export-ignore`-Liste für saubere Release-ZIPs.

### Changed
- **Maturity auf `MATURITY_BETA` gehoben**, Release-Kennzeichnung auf `0.5.0`.
  Plugin-Directory-Uploads akzeptieren ab dieser Stufe; `MATURITY_ALPHA` war
  vorher ein harter Ablehnungsgrund.
- Minimale Moodle-Version von 4.3 auf 4.5 angehoben
  (`$plugin->requires = 2024100700`).
- Icons im Download-Button und in der Bulk-ZIP-Sektion werden jetzt über
  `$OUTPUT->pix_icon()` mit Moodle-Core-Keys (`f/pdf`, `f/archive`,
  `t/download`, `i/reload`) gerendert statt FontAwesome-4-Klassen mit
  `-o`-Suffix (`fa-file-pdf-o`, `fa-file-archive-o`, `fa-refresh`). Die Icons
  sind damit in Moodle 4.5 (FA4) und 5.x (FA6) identisch sichtbar.
- Inline `style="..."`-Attribute in `render_download_button()` und
  `render_report_section()` durch Bootstrap-Utility-Klassen (`my-4`,
  `text-center`, `mt-1`) ersetzt.
- `quizsettings.php` ruft beim Save jetzt
  `helper::save_quiz_config_with_inheritance()` statt `save_quiz_config()` —
  Dirty-Tracking wird damit zur Pflichtschicht der Per-Quiz-Form.

### Removed
- Legacy-Callback `local_eledia_exam2pdf_before_footer()` in `lib.php`
  entfernt. Die Hooks-API ist seit Moodle 4.3 stabil, `$plugin->requires`
  verlangt 4.5+, der Legacy-Pfad war reine Duplikat-Quelle (Report-Section
  wurde auf Moodle 4.5+ zweimal untereinander gerendert).
- Statische Duplikat-Guards (`$overviewactionsinjected`,
  `$reviewdownloadinjected`) in `quiz_page_callbacks` entfernt — ohne den
  Legacy-Callback überflüssig.
- `quiz_page_callbacks::get_footer_html()` entfernt — wurde nur vom
  gelöschten Legacy-Callback aufgerufen.

## [0.1.0] - 2026-04-09

### Added
- Erste Plugin-Version `local_eledia_exam2pdf`
- PDF-Erzeugung nach bestandenen Quiz-Versuchen (TCPDF-basiert)
- Ausgabemodi: Download, E-Mail-Versand oder beides parallel
- Konfigurierbare Kopfzeilen mit Pflicht- und optionalen Feldern
- Per-Quiz-Overrides für alle Admin-Settings
- Scheduled Task `cleanup_expired_pdfs` (nächtlich 02:30 Uhr)
- Zugriffskontrolle über Capabilities
  (`downloadown`, `manage`, `configure`)
- Privacy API (GDPR) — vollständige Export- und Löschfunktion
- Moodle 4.3+ Hooks API Integration für Download-Button auf Quiz-Review-Seite
- Englisches Language Pack (`lang/en`)
