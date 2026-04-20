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
`vendor/` liegt in `.gitignore`; der Release-Build wird deshalb nicht über
`git archive` allein erzeugt, sondern über `bin/release.sh`, das `vendor/`
gezielt in das Staging-ZIP kopiert.

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
   bash bin/release.sh /tmp
   ```
4. ZIP auf `moodle.org/plugins` hochladen.

### Moodle Plugin Directory metadata

Diese Werte gehören in die Plugin-Directory-Maske und werden im Repo als
kanonische Referenz gepflegt:

| Feld | Wert |
|---|---|
| Source code | `https://github.com/jmoskaliuk/local_eledia_exam2pdf` |
| Issue tracker | `https://github.com/jmoskaliuk/local_eledia_exam2pdf/issues` |
| Documentation | `https://github.com/jmoskaliuk/local_eledia_exam2pdf/blob/main/README.md` |
| User documentation | `https://github.com/jmoskaliuk/local_eledia_exam2pdf/blob/main/docs/02-user-doc.md` |
| Development / QA workflow | `https://github.com/jmoskaliuk/local_eledia_exam2pdf/blob/main/docs/dev-workflow.md` |

### Manual submission checklist items

Diese Punkte werden bewusst nicht im PHP-Code gepflegt und müssen bei der
Moodle.org-Einreichung manuell mitgeführt werden:

- aktuelle Screenshots für Quiz-Overview, Bulk-Download und Review-Download
- Short description und Full description im Plugin-Directory-Formular
- optional spätere Umbenennung des öffentlichen Repos auf das empfohlene
  Schema `moodle-local_eledia_exam2pdf`
