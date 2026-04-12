#!/usr/bin/env bash
# setup-plugin-ci.sh — Installiert moodle-plugin-ci ^4 im Orb-Container
#
# Einmalig ausführen (oder nach Container-Neustart).
# Danach kann precheck.sh die moodle-plugin-ci-Befehle nutzen.
#
# Usage:
#   bash bin/setup-plugin-ci.sh
#
# Voraussetzung: deploy.sh wurde mindestens einmal erfolgreich ausgeführt,
# damit ~/.moodle-deploy.conf mit Container + Webroot gecacht ist.

set -euo pipefail

# ---- Deploy-Config laden ----
CONF="${HOME}/.moodle-deploy.conf"
if [[ ! -f "$CONF" ]]; then
    echo "ERROR: ${CONF} nicht gefunden." >&2
    echo "       Zuerst 'bash deploy.sh --dry-run' ausführen." >&2
    exit 2
fi
# shellcheck disable=SC1090
source "$CONF"

CONTAINER="${SAVED_CONTAINER:?SAVED_CONTAINER fehlt in ${CONF}}"
WEBROOT="${SAVED_WEBROOT:?SAVED_WEBROOT fehlt in ${CONF}}"

# ---- Farben ----
if [[ -t 1 ]]; then
    C_GREEN=$'\033[32m'; C_BLUE=$'\033[34m'; C_DIM=$'\033[2m'; C_RESET=$'\033[0m'
else
    C_GREEN=""; C_BLUE=""; C_DIM=""; C_RESET=""
fi

CI_DIR="/opt/moodle-plugin-ci"

echo "${C_BLUE}▸ setup-plugin-ci — moodle-plugin-ci ^4 im Container installieren${C_RESET}"
echo "  Container: ${CONTAINER}"
echo "  Ziel:      ${CI_DIR}"
echo

# ---- Prüfen ob bereits installiert ----
if docker exec "$CONTAINER" test -x "${CI_DIR}/bin/moodle-plugin-ci" 2>/dev/null; then
    VERSION=$(docker exec "$CONTAINER" "${CI_DIR}/bin/moodle-plugin-ci" --version 2>/dev/null || echo "unknown")
    echo "${C_GREEN}✓ Bereits installiert:${C_RESET} ${VERSION}"
    echo "  Zum Neuinstallieren: docker exec $CONTAINER rm -rf $CI_DIR"
    exit 0
fi

# ---- Composer im Container finden oder installieren ----
echo "  Suche Composer im Container..."
COMPOSER_BIN=""
for candidate in /usr/local/bin/composer /usr/bin/composer /tmp/composer/composer; do
    if docker exec "$CONTAINER" test -x "$candidate" 2>/dev/null; then
        COMPOSER_BIN="$candidate"
        break
    fi
done

if [[ -z "$COMPOSER_BIN" ]]; then
    echo "  Composer nicht im PATH — installiere via composer-setup.php..."
    docker exec -u root \
        -e COMPOSER_HOME=/tmp/composer \
        "$CONTAINER" bash -c "
            php -r \"copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');\" && \
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
            rm -f /tmp/composer-setup.php
        "
    COMPOSER_BIN="/usr/local/bin/composer"
fi
echo "  Composer: ${COMPOSER_BIN}"

# ---- Installieren (als root, da /opt/ schreibgeschützt für www-data) ----
echo "  Installiere moodle-plugin-ci ^4 via Composer..."
docker exec -u root \
    -e COMPOSER_HOME=/tmp/composer \
    -e COMPOSER_NO_INTERACTION=1 \
    "$CONTAINER" bash -c "
        ${COMPOSER_BIN} create-project -n --no-dev --prefer-dist \
            moodlehq/moodle-plugin-ci ${CI_DIR} '^4' && \
        chmod -R a+rX ${CI_DIR}
    "

# ---- Verifizieren ----
VERSION=$(docker exec "$CONTAINER" "${CI_DIR}/bin/moodle-plugin-ci" --version 2>/dev/null || echo "FEHLER")
echo
echo "${C_GREEN}✓ Installiert:${C_RESET} ${VERSION}"
echo "  Pfad: ${CI_DIR}/bin/moodle-plugin-ci"
echo
echo "${C_DIM}Hinweis: Nach Container-Neustart muss dieses Skript erneut laufen,"
echo "sofern /opt/ nicht persistiert ist.${C_RESET}"
