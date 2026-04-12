# Development Workflow — local_eledia_exam2pdf

## Übersicht

Code wird in Cowork bearbeitet, über die GitHub API gepusht, auf GitHub Actions getestet und dann lokal auf die Moodle-Instanz deployt.

```
Cowork (Code ändern)
    ↓
GitHub API push (mcp__github__push_files)
    ↓
GitHub Actions CI (4 Matrix-Zellen)
    ↓
Lokal deployen (git pull → sync-mirror → rsync → purge caches)
    ↓
Smoketest im Browser
```

## Schritt 1: Code ändern

Änderungen werden in Cowork vorgenommen. Die Dateien liegen im Workspace-Ordner, der auf dem Mac unter `~/Documents/Claude/Projects/local_eledia_examexport/` gemountet ist.

## Schritt 2: Push via GitHub API

Da die Cowork-Sandbox keinen Zugriff auf das lokale `.git` hat, wird über die GitHub API gepusht (`mcp__github__push_files`). Der Push geht direkt auf den `main`-Branch bei `jmoskaliuk/local_eledia_exam2pdf`.

## Schritt 3: CI auf GitHub Actions

Nach dem Push läuft automatisch die CI-Pipeline mit 4 Matrix-Zellen:

| Moodle | PHP | Datenbank |
|--------|-----|----------|
| 4.5 (MOODLE_405_STABLE) | 8.1 | PostgreSQL |
| 5.0 (MOODLE_500_STABLE) | 8.3 | PostgreSQL |
| 5.1 (MOODLE_501_STABLE) | 8.3 | PostgreSQL |
| 5.1 (MOODLE_501_STABLE) | 8.3 | MariaDB |

Jede Zelle durchläuft: PHP Lint, PHPMD, PHPCS (CodeSniffer), PHPDoc Check, Validierung, Savepoints, Mustache Lint, Grunt, PHPUnit (mit `--fail-on-warning`), Behat (mit `--profile chrome`).

## Schritt 4: Lokal deployen

Nach erfolgreichem CI den neuesten Stand auf die lokale Moodle-Instanz bringen. Auf dem Mac ausführen:

```bash
cd ~/Documents/Claude/Projects/local_eledia_examexport \
  && git pull \
  && bash bin/sync-mirror.sh \
  && rsync -a --delete \
       .deploy/local/eledia_exam2pdf/ \
       /Users/moskaliuk/demo/site/moodle/local/eledia_exam2pdf/ \
  && docker exec demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php
```

Falls lokale Änderungen den Pull blockieren (weil der Push über die API kam, nicht lokal):

```bash
git checkout -- . && git clean -fd && git pull
```

## Schritt 5: Smoketest

Moodle öffnen unter: **http://webserver.demo.orb.local**

Checkliste:

1. Admin-Settings: *Site administration → Plugins → Local plugins → eLeDia exam2pdf* — alle Felder prüfen, ändern, speichern.
2. Quiz als Student bestehen → Review-Seite → "Download certificate"-Link muss erscheinen.
3. PDF herunterladen und prüfen.
4. Quiz als Student nicht bestehen → Review-Seite → kein "Download certificate"-Link.

## Lokale Infrastruktur

- OrbStack im nativen Docker-Modus (keine VM)
- Container: `demo-webserver-1` (PHP 8.3), `demo-db-1` (PostgreSQL), `demo-selenium-1` (Chromium)
- Moodle-Webroot im Container: `/var/www/site/moodle`
- Moodle-Webroot auf dem Mac: `/Users/moskaliuk/demo/site/moodle`
- URL: `http://webserver.demo.orb.local`

## Drei Orte wo Code lebt

1. **Repo auf dem Mac** — `~/Documents/Claude/Projects/local_eledia_examexport/` (hier editiert Cowork)
2. **`.deploy/`-Ordner** — Staging-Kopie im Repo (gebaut von `sync-mirror.sh`)
3. **Docker-Container** — `/var/www/site/moodle/local/eledia_exam2pdf/` (dort läuft Moodle)
