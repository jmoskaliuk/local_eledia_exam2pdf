# Development Workflow — local_eledia_exam2pdf

## Übersicht

```text
Code ändern → sync-mirror → rsync → upgrade/purge_caches → Browser-Smoketest → precheck → git commit → git push → CI prüfen
```

---

## 1. Lokal entwickeln

Arbeitsverzeichnis:
- Repository: `~/Documents/Code/exam2pdf`
- Moodle-Plugin-Ziel (Host): `~/demo/site/moodle/public/local/eledia_exam2pdf`
- Moodle-Plugin-Ziel (Container): `/var/www/site/moodle/public/local/eledia_exam2pdf`

Wichtige Endpunkte für Tests:
- Quiz-Overview: `mod/quiz/report.php?mode=overview`
- Quiz-Review: `mod/quiz/review.php?attempt=...`
- Quiz-Overrides: `local/eledia_exam2pdf/quizsettings.php?cmid=...`

**PDF-Abhängigkeit**: `vendor/` (mPDF) wird via Composer verwaltet.
Nach `git clone` oder auf frischer Maschine: `composer install --no-dev` ausführen.
Die `vendor/`-Verzeichnis liegt in `.gitignore` und muss beim ZIP-Export für das
Moodle Plugin Directory manuell eingeschlossen werden.

---

## 2. Deploy in lokale Moodle-Instanz

Einzeiliger Full-Deploy inkl. Precheck:

```bash
bash bin/sync-mirror.sh \
  && rsync -a --delete .deploy/local/eledia_exam2pdf/ \
       ~/demo/site/moodle/public/local/eledia_exam2pdf/ \
  && docker exec -u www-data demo-webserver-1 \
       php /var/www/site/moodle/admin/cli/upgrade.php --non-interactive --allow-unstable \
  && docker exec -u www-data demo-webserver-1 \
       php /var/www/site/moodle/admin/cli/purge_caches.php \
  && bash bin/precheck.sh
```

Nur Cache leeren (ohne Deploy):

```bash
docker exec -u www-data demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php
```

---

## 3. Lokale QA — precheck.sh

`bin/precheck.sh` spiegelt exakt die GitHub-CI-Pipeline (`moodle-ci.yml`).

```bash
bash bin/precheck.sh                 # Alle Checks
bash bin/precheck.sh --only phpcs    # Nur einen Check
bash bin/precheck.sh --verbose       # Mit vollständiger Ausgabe
bash bin/precheck.sh --with-behat    # Inkl. Behat-Feature-Tests
```

Zielzustand: **PASS:9 WARN:0 FAIL:0 SKIP:1** (Behat per opt-in)

Bekannte Eigenheiten:
- `vendor/` wird für `phpdoc` und `phpmd` via Temp-Copy ausgeschlossen (mPDF würde sonst false failures produzieren).
- `gherkinlint` hat einen Toolchain-Bug bei selbstreferenziellen `no-dupe-feature-names`-Meldungen — `mpci_grunt()` filtert diese automatisch.

---

## 4. Smoke-Checks

- Overview-Report: Actions-Spalte + Download/Regenerieren
- Bulk-Button: ZIP/merged Download
- Review-Seite: Download-Auswertung nur bei erlaubten Fällen
- PDF-Inhalt: Header, Logo, Navigationsleiste, Fragen/Antworten, Punkte, Auswertungs-Score, Footer

---

## 5. Git + GitHub

```bash
git add -A
git commit -m "<message>"
git push origin main
```

---

## 6. CI

Nach jedem Push die GitHub-Actions-Läufe prüfen.
Bei Fehlschlägen: Logs analysieren, fixen, erneut pushen.

---

## 7. Release / Plugin-Directory-Export

1. `version.php` bumpen (YYYYMMDDXX)
2. `git tag vX.Y.Z && git push origin vX.Y.Z`
3. ZIP bauen — **`vendor/` muss enthalten sein**:
   ```bash
   composer install --no-dev --optimize-autoloader
   git archive HEAD --prefix=eledia_exam2pdf/ | tar x -C /tmp/
   cp -r vendor/ /tmp/eledia_exam2pdf/
   cd /tmp && zip -r eledia_exam2pdf-vX.Y.Z.zip eledia_exam2pdf/
   ```
4. ZIP auf `moodle.org/plugins` hochladen.

