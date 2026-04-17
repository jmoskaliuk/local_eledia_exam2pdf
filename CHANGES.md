# Changelog

Alle nennenswerten Änderungen an `local_eledia_exam2pdf` werden in diesem
Dokument festgehalten. Format: [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [0.5.3] - 2026-04-17

Dritter und letzter CI-Fix-Patch für die `amd/build`-Artefakte: 0.5.1/0.5.2
versuchten die minifizierten Module aus einer externen Terser-Pipeline zu
erzeugen, konnten aber Moodles kanonische Grunt-Ausgabe nie byte-genau
reproduzieren — der `git diff --exit-code`-Check im `moodle-plugin-ci grunt`-
Step blieb rot, weil sowohl `.min.js`-Bytes abwichen als auch die von
Moodles Rollup-Pipeline generierten `.min.js.map`-Sourcemaps fehlten.
0.5.3 committet den Output aus Moodles echter Grunt-Pipeline (Rollup +
Babel + Terser + Sourcemaps), aus dem lokalen Orb-Container extrahiert
via `bin/regenerate-amd-build.sh`.

### Added
- `amd/build/*.min.js.map` — Sourcemap-Dateien für alle drei AMD-Module,
  von Moodles Rollup-Plugin generiert. Ohne diese schlug `moodle-plugin-ci
  grunt` auf der CI fehl mit "File is newly generated and needs to be added".
- `bin/regenerate-amd-build.sh` — Helper-Script für die lokale Regeneration
  im Orb-Container. Umgeht den `public/local/codechecker/vendor`-ENOENT-
  Abbruch der `ignorefiles`-Task durch `grunt amd --force` und kopiert das
  Ergebnis zurück ins Repo. Für reproduzierbare Releases: vor jedem Tag
  einmal ausführen.

### Changed
- `amd/build/*.min.js` — durch Moodles kanonischen Grunt-Rollup-Output
  ersetzt (Rollup ESM-Format, Babel preset-env mit Moodles Ziel-Browsern,
  `transform-es2015-modules-amd-lazy`, Moodles custom `add-module-to-
  define`-Plugin, Terser `mangle: false`, Sourcemaps inline referenced).
  Funktional identisch zu 0.5.2, aber byte-genau reproduzierbar durch
  `npx grunt amd --root=public/local/eledia_exam2pdf --force` im
  Moodle-Root.

## [0.5.2] - 2026-04-17

Zweiter CI-Fix-Patch für die `amd/build`-Artefakte: 0.5.1 committete zwar
die minifizierten Module, aber meine Terser-Pipeline produzierte eine
andere Byte-Sequenz als Moodles kanonisches `grunt amd` — der Check
`git diff --exit-code` im `moodle-plugin-ci grunt`-Step blieb deshalb rot.
0.5.2 dreht die Quell-Dateien auf klassisches AMD (pre-ES6) zurück,
sodass Moodles Grunt-Pipeline beim Regenerieren keinen Babel-Transform
mehr anwendet und der Output byte-identisch zum Commit bleibt.

### Changed
- `amd/src/*.js` — von ES6-Modulen (`export const init = (args) => {...}`)
  auf klassisches AMD umgestellt (`define([], function() { 'use strict';
  return { init: function(args) {...} }; });`). Alle `const`/`let` durch
  `var` ersetzt, Arrow Functions durch benannte `function`-Deklarationen,
  ES6-Module-Syntax komplett entfernt. Verhalten unverändert. Begründung:
  Mit klassischem AMD hat Moodles `babel-plugin-transform-es2015-modules-
  amd-lazy` nichts zu transformieren; nur der `add-module-to-define`-
  Schritt läuft, und der ist deterministisch — der committete Build matcht
  damit byte-genau das, was `moodle-plugin-ci grunt` auf der CI regeneriert.
- `amd/build/*.min.js` — neu mit `comments: 'some'` minifiziert, sodass
  der `@module`-JSDoc-Block am Dateianfang erhalten bleibt (matcht das
  LeitnerFlow-Referenz-Format). Module-ID wird als erster `define()`-
  Argument injiziert (`define("local_eledia_exam2pdf/<name>", [], …)`).

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
