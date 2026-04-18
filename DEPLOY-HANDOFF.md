# Lokales Deployment von Moodle-Plugins zu Johannes' Docker/Orb-Setup

Handoff-Dokument für Agenten, die ein Moodle-Plugin in Johannes'
lokale Dev-Umgebung deployen sollen. Gilt für **alle** Plugins —
die Umgebung ist identisch, nur Pluginname, Komponentenname und
Plugintyp ändern sich.

## 1. Die Umgebung

**Plattform**: macOS mit **OrbStack** im **nativen Docker-Modus**
(keine Linux-VM dazwischen). `orb list` zeigt nur den Header, keine
Machines. `docker ps` funktioniert ohne `orb -m <vm>`-Präfix.

**Moodle-Stack**: offizielles [moodle-docker](https://github.com/moodlehq/moodle-docker)
Setup, Docker-Compose-Projektname **`demo`**. URL:
`http://webserver.demo.orb.local`.

**Container** (laufend):

| Container | Rolle | Image |
|---|---|---|
| `demo-webserver-1` | Apache + PHP 8.3 | `mutms/mdc-php-apache:8.3` |
| `demo-db-1` | Postgres | `postgres:18` |
| `demo-selenium-1` | Behat-Browser | `seleniarm/standalone-chromium` |

**Host ↔ Container Bind-Mount**:

| Host-Pfad | Container-Pfad | Zweck |
|---|---|---|
| `~/demo/site` | `/var/www/site` | Moodle-Source |
| `~/demo/dataroot` | `/var/www/dataroot` | Moodledata |

**Moodle-Version**: 5.1 mit `public/`-Layout. **Kritisch zu wissen:**

- Plugin-Code: `/var/www/site/moodle/public/<type>/<name>/`
  (Host-Äquivalent: `~/demo/site/moodle/public/<type>/<name>/`)
- CLI-Skripte (upgrade, purge\_caches, ...):
  `/var/www/site/moodle/admin/cli/` — **NICHT unter public/!**

**PHP-User im Container**: `www-data`

## 2. Der Deploy-Flow (Big Picture)

Code lebt an drei Orten, und der Deploy kopiert ihn Schritt für Schritt
nach rechts:

```
1. Repo (Source of Truth)   ~/Documents/Claude/Projects/<repo>/
                                       │
                                       ▼  bin/sync-mirror.sh (falls vorhanden)
2. .deploy/-Staging         <repo>/.deploy/local/<component>/
                                       │
                                       ▼  rsync (Host → Host-Mount)
3. Container-Moodle         ~/demo/site/moodle/public/<type>/<name>/
                            ≙ /var/www/site/moodle/public/<type>/<name>/
```

Warum Staging? `sync-mirror.sh` wählt explizit aus, welche Dateien
ins Release gehören (keine `.git`, keine `node_modules`, keine
Dokumentations-Drafts, ...). Wenn das Ziel-Plugin **kein**
`bin/sync-mirror.sh` hat, kann der Rsync direkt vom Repo-Root erfolgen
— aber **mit** expliziten Excludes.

## 3. Der Deploy-One-Liner (empfohlen)

Nach jeder Code-Änderung ausführen. Ersetze `<PLUGIN_TYPE>` (z.B.
`local`, `mod`, `block`), `<PLUGIN_NAME>` (short name, z.B.
`eledia_exam2pdf`) und `<REPO_PATH>` entsprechend.

```bash
cd <REPO_PATH> \
  && bash bin/sync-mirror.sh \
  && rsync -a --delete \
       .deploy/<PLUGIN_TYPE>/<PLUGIN_NAME>/ \
       ~/demo/site/moodle/public/<PLUGIN_TYPE>/<PLUGIN_NAME>/ \
  && docker exec -u www-data demo-webserver-1 \
       php /var/www/site/moodle/admin/cli/upgrade.php --non-interactive --allow-unstable \
  && docker exec -u www-data demo-webserver-1 \
       php /var/www/site/moodle/admin/cli/purge_caches.php \
  && docker exec demo-webserver-1 \
       bash -c "php -r 'opcache_reset();' 2>/dev/null || true"
```

### Warum jeder Schritt nötig ist

1. **`sync-mirror.sh`** — rebuildet `.deploy/`, damit die Staging-Kopie
   mit dem Repo übereinstimmt. Ohne das driften Deploys still vor sich
   hin.
2. **`rsync -a --delete`** — spiegelt `.deploy/` in das gemountete
   Host-Verzeichnis; `--delete` entfernt Dateien, die im Repo gelöscht
   wurden, auch im Container.
3. **`upgrade.php --non-interactive`** — nötig nach jedem
   `version.php`-Bump oder neuen Tabellen/Capabilities. Wenn man nur
   bestehende Klassen/Templates ändert, reicht `--skip-upgrade` — aber
   ist defensiver immer mitzulaufen.
4. **`purge_caches.php`** — Moodle hält Lang-Strings, Mustache, Klassen-Autoloader
   und Renderer in Caches; ohne Purge sehen User weiter alte Werte.
5. **`opcache_reset()`** — PHP hält kompilierten Bytecode im OPcache.
   Purge allein reicht nicht: OPcache-Einträge überleben. Ohne
   `opcache_reset` kann der Browser weiterhin alten Code sehen, obwohl
   Dateien auf Disk neu sind. **Häufigste Deploy-Falle.**

### Die absolute Killer-Kombi (wenn gar nichts zieht)

```bash
docker exec -u www-data demo-webserver-1 \
  php /var/www/site/moodle/admin/cli/purge_caches.php \
  && docker exec demo-webserver-1 \
       bash -c "php -r 'opcache_reset();' 2>/dev/null || true" \
  && docker exec demo-webserver-1 apachectl -k graceful
```

Purge + OPcache-Reset + Apache-Reload. Nach diesem Befehl sieht der
Browser garantiert neuen Code.

## 4. Deploy-Verifikation (immer anhängen)

Nach dem Deploy **immer** prüfen, dass der neue Code im Container
angekommen ist. Typischer Pattern:

```bash
docker exec demo-webserver-1 grep -c "<eindeutiger-Marker-String>" \
  /var/www/site/moodle/public/<PLUGIN_TYPE>/<PLUGIN_NAME>/<Datei>
```

Als Marker am besten einen frischen Kommentar-Satz oder Variablennamen
wählen, der nur in der neuen Version existiert. Ergebnis `>0` → Deploy
erfolgreich. Ergebnis `0` → **nicht deployt**, nicht weitermachen.

Version zusätzlich prüfen:

```bash
docker exec demo-webserver-1 grep -E "release|version" \
  /var/www/site/moodle/public/<PLUGIN_TYPE>/<PLUGIN_NAME>/version.php
```

## 5. Häufige Stolperfallen

### 5.1 `rsync: executable not found`

**Fehler**: Agent hat versucht `docker exec demo-webserver-1 rsync ...`.
**Ursache**: Das Image hat kein rsync installiert.
**Fix**: `rsync` ist ein **Host-Kommando** (Mac → Bind-Mount). Im
Container **nie** `rsync` aufrufen.

### 5.2 `/var/www/html` existiert nicht

**Fehler**: Agent rät `/var/www/html/...` als Container-Webroot.
**Ursache**: Das ist der Standard-Apache-Webroot — hier aber **nicht**
verwendet.
**Fix**: Immer `/var/www/site/moodle/public/` (Plugins) bzw.
`/var/www/site/moodle/admin/cli/` (CLI).

### 5.3 Kein `public/` vor `admin/cli/`

**Fehler**: `/var/www/site/moodle/public/admin/cli/upgrade.php`
**Ursache**: Nur Plugin-Code sitzt unter `public/`, die CLI-Skripte
liegen eine Ebene höher.
**Fix**: `/var/www/site/moodle/admin/cli/upgrade.php` (ohne `public/`).

### 5.4 Änderungen wirken nicht

**Symptom**: User sagt "sehe keine Änderung" obwohl Deploy durchlief.
**Ursache in 9/10 Fällen**: OPcache hält alten Bytecode.
**Fix**: `opcache_reset` in den Deploy-Flow.

### 5.5 Permissions-Fehler beim `docker exec`

**Fehler**: `Permission denied` beim Schreiben in Moodledata.
**Ursache**: PHP im Container läuft als `www-data`. Dateien, die als
root erstellt wurden, können nicht geschrieben werden.
**Fix**: `docker exec -u www-data` für alle Moodle-CLI-Befehle.

### 5.6 Falscher Compose-Projektname

Wenn `docker ps` keinen `demo-webserver-1` zeigt, ist entweder der
Stack runter oder der Projektname anders. Check:
```bash
docker ps --format '{{.Names}}' | grep -i webserver
```

## 6. Precheck vor Commit (optional, empfohlen)

Wenn das Plugin `bin/precheck.sh` hat, danach laufen lassen:

```bash
bash bin/precheck.sh
```

**Wichtig**: Precheck läuft gegen die **Container-Kopie**, nicht gegen
das Repo. Ohne vorherigen Deploy bekommt man phantom-warnings aus der
vorigen Version. Deshalb immer: **sync-mirror → rsync → upgrade → purge
→ opcache-reset → precheck**.

## 7. Setup einer frischen Session

Nach Container-Neustart muss `moodle-plugin-ci` ggf. neu installiert
werden (liegt unter `/opt/moodle-plugin-ci/`, nicht persistiert):

```bash
cd <REPO_PATH> && bash bin/setup-plugin-ci.sh
```

Nur falls `precheck.sh` verwendet werden soll — für reines Deploy
nicht nötig.

## 8. Template: Vollständige Deploy-Prozedur

```bash
#!/usr/bin/env bash
# Beispiel-Deploy für ein eLeDia-Plugin.
# Anpassen: PLUGIN_TYPE, PLUGIN_NAME, REPO_PATH.
set -euo pipefail

PLUGIN_TYPE="local"
PLUGIN_NAME="eledia_beispielplugin"
REPO_PATH="$HOME/Documents/Claude/Projects/<repo-name>"

cd "$REPO_PATH"

# 1. Repo -> .deploy/ spiegeln (falls sync-mirror.sh vorhanden).
if [[ -x bin/sync-mirror.sh ]]; then
    bash bin/sync-mirror.sh
    SRC=".deploy/${PLUGIN_TYPE}/${PLUGIN_NAME}/"
else
    SRC="./"
fi

# 2. Host -> Container-Mount.
rsync -a --delete \
    --exclude='.git' --exclude='.deploy' \
    --exclude='node_modules' --exclude='vendor' \
    "$SRC" \
    "$HOME/demo/site/moodle/public/${PLUGIN_TYPE}/${PLUGIN_NAME}/"

# 3. Moodle-Upgrade (bei version.php-Bump nötig, sonst no-op).
docker exec -u www-data demo-webserver-1 \
    php /var/www/site/moodle/admin/cli/upgrade.php \
        --non-interactive --allow-unstable

# 4. Caches leeren.
docker exec -u www-data demo-webserver-1 \
    php /var/www/site/moodle/admin/cli/purge_caches.php

# 5. OPcache resetten — kritisch, sonst bleibt alter Bytecode.
docker exec demo-webserver-1 \
    bash -c "php -r 'opcache_reset();' 2>/dev/null || true"

# 6. Verifizieren — <MARKER> anpassen an einen neuen Codestring.
docker exec demo-webserver-1 grep -c "<MARKER>" \
    "/var/www/site/moodle/public/${PLUGIN_TYPE}/${PLUGIN_NAME}/version.php" \
    || echo "WARNUNG: Marker nicht gefunden — Deploy verifizieren!"

echo "Deploy fertig."
```

## 9. Kurz-Cheatsheet zum Copy/Paste

```bash
# Ins Projekt wechseln
cd ~/Documents/Claude/Projects/<repo>

# Deploy
bash bin/sync-mirror.sh && \
rsync -a --delete .deploy/<type>/<name>/ ~/demo/site/moodle/public/<type>/<name>/ && \
docker exec -u www-data demo-webserver-1 php /var/www/site/moodle/admin/cli/upgrade.php --non-interactive --allow-unstable && \
docker exec -u www-data demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php && \
docker exec demo-webserver-1 bash -c "php -r 'opcache_reset();' 2>/dev/null || true"

# Verify
docker exec demo-webserver-1 grep -c "<MARKER>" /var/www/site/moodle/public/<type>/<name>/<datei>.php
```

---

**Letzte Änderung**: 2026-04-18 (synchron mit `feedback_deploy_one_liner.md`
und `reference_moodle_local_docker.md`).
