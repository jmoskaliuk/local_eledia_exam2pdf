# Development Workflow — local_eledia_exam2pdf

## Übersicht

Empfohlener Ablauf:

```text
Code ändern -> Lokal deployen -> Cache leeren -> Browser-Smoketest -> Git push -> CI prüfen
```

---

## 1. Lokal entwickeln

Arbeitsverzeichnis:
- Repository: `/tmp/local_eledia_exam2pdf`
- Moodle-Plugin-Ziel: `/var/www/site/moodle/public/local/eledia_exam2pdf`

Wichtige Endpunkte für Tests:
- Quiz-Overview: `mod/quiz/report.php?mode=overview`
- Quiz-Review: `mod/quiz/review.php?attempt=...`
- Quiz-Overrides: `local/eledia_exam2pdf/quizsettings.php?cmid=...`

---

## 2. Deploy in lokale Moodle-Instanz

```bash
mkdir -p /tmp/deploy_exam2pdf/local/eledia_exam2pdf
rsync -a --delete /tmp/local_eledia_exam2pdf/ /tmp/deploy_exam2pdf/local/eledia_exam2pdf/
bash /tmp/local_eledia_exam2pdf/deploy.sh --source /tmp/deploy_exam2pdf --no-host-copy
```

Cache danach explizit leeren:

```bash
docker exec demo-webserver-1 sh -lc 'php /var/www/site/moodle/admin/cli/purge_caches.php'
```

---

## 3. Smoke-Checks

- Overview-Report: Actions-Spalte + Download/Regenerieren
- Bulk-Button: ZIP/merged Download
- Review-Seite: Download-Auswertung nur bei erlaubten Fällen
- PDF-Inhalt: Header, Logo, Navigation, Fragen/Antworten, Punkte, Footer

---

## 4. Git + GitHub

```bash
git add -A
git commit -m "<message>"
git push origin <branch>
```

Für direkten Stand auf `main`:

```bash
git switch main
git merge --ff-only <feature-branch>
git push origin main
```

---

## 5. CI

Nach jedem Push die GitHub-Actions-Läufe prüfen.
Bei Fehlschlägen: Logs analysieren, fixen, erneut pushen.

