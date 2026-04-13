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
Lokal deployen (sync-mirror → rsync → upgrade → purge caches)
    ↓
Smoketest im Browser
```

## Schritt 1: Code ändern

Änderungen werden in Cowork vorgenommen. Die Dateien liegen im Workspace-Ordner, der auf dem Mac unter `~/Documents/Claude/Projects/local_eledia_examexport/` gemountet ist.

## Schritt 2: Push via GitHub API

Da die Cowork-Sandbox keinen Zugriff auf das lokale `.git` hat, wird über die GitHub API gepusht (`mcp__github__push_files`). Der Push geht direkt auf den `main`-Branch bei `jmoskaliuk/local_eledia_exam2pdf`.

**Wichtig:** Der GitHub-Repo-Name ist `local_eledia_exam2pdf`, nicht `local_eledia_examexport` (das ist nur der lokale Ordnername).

## Schritt 3: CI auf GitHub Actions

Nach dem Push läuft automatisch die CI-Pipeline mit 4 Matrix-Zellen:

| Moodle | PHP | Datenbank |
|--------|-----|-----------|
| 4.5 (MOODLE_405_STABLE) | 8.1 | PostgreSQL |
| 5.0 (MOODLE_500_STABLE) | 8.3 | PostgreSQL |
| 5.1 (MOODLE_501_STABLE) | 8.3 | PostgreSQL |
| 5.1 (MOODLE_501_STABLE) | 8.3 | MariaDB |

Jede Zelle durchläuft: PHP Lint, PHPMD, PHPCS (`--max-warnings 0`), PHPDoc Check, Validierung, Savepoints, Mustache Lint, Grunt, PHPUnit (`--fail-on-warning`), Behat (`--profile chrome`).

## Schritt 4: Lokal deployen

Die lokale Moodle-Instanz verwendet ein Bind-Mount, d.h. das Dateisystem des Containers ist direkt auf dem Mac zugänglich. Plugins liegen unter `public/local/`.

### Schneller Deploy-Loop (empfohlen)

```bash
cd ~/Documents/Claude/Projects/local_eledia_examexport

# 1. Mirror aufbauen (entfernt Dateien die nicht ins Plugin gehören)
bash bin/sync-mirror.sh

# 2. Rsync zum Bind-Mount (direkt in den Container)
rsync -a --delete .deploy/local/eledia_exam2pdf/ ~/demo/site/moodle/public/local/eledia_exam2pdf/

# 3. Upgrade + Caches leeren
docker exec -u www-data demo-webserver-1 php /var/www/site/moodle/admin/cli/upgrade.php --non-interactive
docker exec demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php
```

### Nur Caches leeren (keine DB-Änderung)

```bash
docker exec demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php
```

### Git pull nach API-Push

Weil der Push über die API kam (nicht lokal), muss man vor dem nächsten lokalen Arbeiten pullen:

```bash
cd ~/Documents/Claude/Projects/local_eledia_examexport
git checkout -- . && git clean -fd && git pull
```

## Schritt 5: Smoketest

Moodle öffnen unter: **http://webserver.demo.orb.local**

### Admin-Settings

1. *Site administration → Plugins → Local plugins → eLeDia | exam2pdf* öffnen.
2. Alle Felder prüfen: PDF-Erzeugungsmodus, PDF-Umfang, Teilnehmer darf herunterladen, Ausgabemodus, Bulk-Format, E-Mail-Empfänger, E-Mail-Betreff, Aufbewahrungsfrist, optionale Kopfzeilenfelder, korrekte Antworten.
3. Ändern und speichern — keine Fehler.

### Per-Quiz-Settings

4. Ein Quiz bearbeiten → **Edit settings** → zum Abschnitt **exam2pdf Settings** scrollen.
5. Prüfen: alle Felder vorhanden (Teilnehmer darf herunterladen, Ausgabemodus, E-Mail-Empfänger, E-Mail-Betreff, Aufbewahrungsfrist, korrekte Antworten, optionale Felder).
6. Fragezeichen-Icons klicken → Hilfetext erscheint.
7. „Globalen Standard verwenden" vs. eigene Werte setzen, speichern, erneut öffnen → Werte korrekt geladen.

### Student-Flow

8. Quiz als Student bestehen → Review-Seite → **„Zertifikat herunterladen"**-Button muss erscheinen (wenn studentdownload aktiviert).
9. PDF herunterladen und prüfen: Logo, Kopfzeile, Fragen & Antworten, Footer.
10. Quiz als Student nicht bestehen → Review-Seite → kein Download-Button.
11. `studentdownload` per Quiz deaktivieren → Button verschwindet auch bei bestandenem Versuch.

### Teacher-Flow

12. Quiz-Results-Seite (`quiz/report.php`) → **„PDFs herunterladen"**-Button oberhalb der Versuchstabelle.
13. Button klicken → exam2pdf Report-Seite öffnet sich.
14. Report-Seite prüfen: sortierbare Spalten (Vorname, Nachname, Gestartet, Abgeschlossen, Bewertung, Erstellt), Paginierung, A-Z-Initialbars.
15. Download-Icon pro Zeile klicken → einzelnes PDF.
16. „Alle als ZIP herunterladen" → ZIP-Archiv mit allen PDFs.

### Navigation

17. Im Quiz → Zahnrad-Menü (Settings) → „exam2pdf" Link zur Report-Seite.

## Lokale Infrastruktur

- OrbStack im nativen Docker-Modus (keine VM)
- Container: `demo-webserver-1` (PHP 8.3), `demo-db-1` (PostgreSQL), `demo-selenium-1` (Chromium)
- Moodle-Webroot im Container: `/var/www/site/moodle` (CLI-Scripts unter `admin/cli/`)
- Moodle `public/` im Container: `/var/www/site/moodle/public` (Plugins unter `public/local/`)
- Moodle-Webroot auf dem Mac (Bind-Mount): `~/demo/site/moodle`
- URL: `http://webserver.demo.orb.local`

## Wichtige Pfade

| Was | Pfad |
|-----|------|
| Plugin im Container | `/var/www/site/moodle/public/local/eledia_exam2pdf/` |
| Plugin auf Mac (Bind-Mount) | `~/demo/site/moodle/public/local/eledia_exam2pdf/` |
| Moodle-Root im Container | `/var/www/site/moodle` |
| CLI-Scripts im Container | `/var/www/site/moodle/admin/cli/` |
| Moodle-URL | http://webserver.demo.orb.local |
| Projekt auf Mac | `~/Documents/Claude/Projects/local_eledia_examexport` |
| Deploy-Mirror | `~/Documents/Claude/Projects/local_eledia_examexport/.deploy/` |
| Git-Remote | https://github.com/jmoskaliuk/local_eledia_exam2pdf |

## Version bumpen

Vor jedem Deploy mit DB-Änderungen muss `$plugin->version` in `version.php` **höher** sein als die installierte Version. Format: `YYYYMMDDNN` (Datum + zweistellige Laufnummer).

## Häufige Fehler

### `cannotdowngrade`
**Ursache:** `$plugin->version` kleiner/gleich installierter Version.
**Fix:** Version in `version.php` erhöhen, erneut deployen.

### `No upgrade needed`
**Ursache:** Lokale `version.php` nicht aktualisiert nach API-Push.
**Fix:** `git pull` oder Version manuell anpassen.

### count() on null in quiz_attempt.php
**Ursache:** Quiz hat keine Fragen-Slots (z.B. vom Testdata-Plugin erzeugt). Moodle Core ruft `count()` auf `null` auf.
**Fix:** Der Observer prüft vorab `$DB->count_records('quiz_slots', ...)` und überspringt die PDF-Erzeugung. Der Generator hat zusätzlich try/catch.

### `get_config()` gibt `false` statt `null` zurück
**Ursache:** Wenn ein Config-Key nie gespeichert wurde, gibt `get_config()` `false` zurück. Der `??`-Operator fängt `false` nicht ab.
**Fix:** Explizit mit `=== false` prüfen: `(($val = get_config(...)) === false) ? $default : $val`.
