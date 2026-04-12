# Quality — eLeDia | exam2pdf

## Meta

Dieses Dokument verfolgt Bugs, Testergebnisse und CI-Status.

---

## CI-Status

**GitHub Actions** — 4 Matrix-Zellen, alle grün (Stand 2026-04-12, Commit fbb77b5):

| Moodle | PHP | DB | Status |
|---|---|---|---|
| 4.5 (MOODLE_405_STABLE) | 8.1 | PostgreSQL | ✅ |
| 5.0 (MOODLE_500_STABLE) | 8.3 | PostgreSQL | ✅ |
| 5.1 (MOODLE_501_STABLE) | 8.3 | PostgreSQL | ✅ |
| 5.1 (MOODLE_501_STABLE) | 8.3 | MariaDB | ✅ |

Checks pro Zelle: PHP Lint, PHPMD, PHPCS (CodeSniffer), PHPDoc, Validierung, Savepoints, Mustache Lint, Grunt, PHPUnit (--fail-on-warning), Behat (--profile chrome).

---

## 🐞 Bugs

### bug01 $gradepass undefined in render_header()
Status: fixed (2026-04-12, Commit 6824d9dd)
Feature: feat01
Beschreibung: `$gradepass` in `generate()` definiert aber nicht an `render_header()` übergeben → PHP Warning → ErrorException in Moodle Error Handler.
Fix: `$gradepass` als 9. Parameter an `render_header()` übergeben.

### bug02 BrowserKit kann admin_setting_configcheckbox nicht unchecken
Status: fixed (2026-04-12, Commit cf76b1f)
Feature: Behat-Tests
Beschreibung: BrowserKit-Treiber kann Moodle-Admin-Checkboxen nicht deaktivieren.
Fix: `@javascript`-Tag am betroffenen Szenario → WebDriver nutzen.

### bug03 Behat-Generator feuert attempt_submitted Event nicht
Status: fixed (2026-04-12, Commit cf76b1f)
Feature: Behat-Tests
Beschreibung: `user X has attempted Y with responses:` schreibt Attempt direkt in DB ohne Event.
Fix: Custom Behat step `behat_local_eledia_exam2pdf` der den Observer manuell triggert.

### bug04 submitterid fehlt im Event-other-Array
Status: fixed (2026-04-12, Commit fbb77b5)
Feature: Behat-Tests
Beschreibung: `attempt_submitted::create()` erfordert `submitterid` im `other`-Array.
Fix: `submitterid => $user->id` in der Behat-Kontextklasse ergänzt.

---

## 🧪 Tests — Automatisiert

### PHPUnit (38 Tests, 93 Assertions)
Status: ✅ grün auf allen 4 Zellen

Testklassen:
- `observer_test.php` — Event-Handling, PDF-Erzeugung, Duplikatschutz
- `helper_test.php` — Config-Merge, Scope-Prüfung
- `task/cleanup_expired_pdfs_test.php` — Bereinigung abgelaufener PDFs
- `privacy/provider_test.php` — GDPR-Export und -Löschung

### Behat (7 Szenarien)
Status: ✅ grün auf allen 4 Zellen

Features:
- `admin_settings.feature` (4 Szenarien) — Settings-Seite, Persistenz, Checkbox-Toggle, Retention
- `download_button.feature` (3 Szenarien) — Download bei bestanden, kein Download bei nicht bestanden, Link vorhanden

---

## 🧪 Tests — Manuell (Smoketest)

### test01 Smoketest: PDF-Erzeugung nach bestandenem Versuch
Feature: feat01
Status: offen (wartet auf lokales Deployment mit aktuellem Code)

### test02 Negativtest: Nicht bestandener Versuch
Feature: feat01, feat06
Status: offen

### test03 E-Mail-Modus
Feature: feat02
Status: offen

### test04 Mehrere bestandene Versuche
Feature: feat01
Status: offen

### test05 Cleanup Task
Feature: feat05
Status: offen

### test06 Per-Quiz-Config Override
Feature: feat04
Status: offen

### test07 Teacher Report-Seite (NEU)
Feature: feat07
Status: offen (wartet auf Implementierung von task14)

### test08 Bulk-Download (NEU)
Feature: feat08
Status: offen (wartet auf Implementierung von task15)

### test09 Student-Download ein/aus (NEU)
Feature: feat09
Status: offen (wartet auf Implementierung von task16)

---

## 📋 Qualitätsziele

- [x] CI grün auf allen 4 Matrix-Zellen
- [x] PHPCS: 0 Fehler mit Moodle-Coding-Standards
- [x] PHPDoc: alle public methods dokumentiert
- [x] Behat: Happy Path automatisiert
- [x] PHPUnit: observer, helper, cleanup, privacy abgedeckt
- [ ] Manuelle Smoketests (test01–test06) durchgeführt
- [ ] Neue Features (test07–test09) getestet
- [ ] Plugin Directory Precheck bestanden
