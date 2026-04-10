#!/usr/bin/env bash
# precheck.sh — Lokaler Moodle-Plugin-QA-Check via docker exec
#
# Läuft gegen den bestehenden moodle-docker-Container (demo-webserver-1 o.ä.)
# und nutzt die dort installierte Moodle-Umgebung inkl. Vendor-Libraries.
#
# Usage:
#   ./bin/precheck.sh                  # phplint, phpcs, phpdoc, xmllint, savepoint, lang, phpunit
#   ./bin/precheck.sh --with-behat     # Zusätzlich Behat-Feature-Tests
#   ./bin/precheck.sh --only phpcs     # Nur einen einzelnen Check
#   ./bin/precheck.sh --no-phpunit     # PHPUnit auslassen
#   ./bin/precheck.sh --verbose        # Alle Check-Outputs zeigen
#
# Exit: 0 bei PASS, 1 bei FAIL, 2 bei Config-Problem
#
# Voraussetzung: deploy.sh wurde mindestens einmal erfolgreich (auch --dry-run reicht)
# ausgeführt, damit ~/.moodle-deploy.conf mit Container + Webroot gecacht ist.

set -euo pipefail

# ---- Plugin-Identity ----
PLUGIN_COMPONENT="local_eledia_exam2pdf"
PLUGIN_SHORTNAME="eledia_exam2pdf"
PLUGIN_TYPE="local"
PLUGIN_REL_PATH="local/eledia_exam2pdf"

# ---- Argument-Parsing ----
WITH_BEHAT=false
NO_PHPUNIT=false
ONLY=""
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-behat)  WITH_BEHAT=true; shift ;;
        --no-phpunit)  NO_PHPUNIT=true; shift ;;
        --only)        ONLY="$2"; shift 2 ;;
        --verbose)     VERBOSE=true; shift ;;
        -h|--help)
            sed -n '3,17p' "$0"
            exit 0 ;;
        *)
            echo "Unknown option: $1" >&2
            exit 2 ;;
    esac
done

# ---- Deploy-Config laden (Container + Webroot) ----
CONF="${HOME}/.moodle-deploy.conf"
if [[ ! -f "$CONF" ]]; then
    echo "ERROR: ${CONF} nicht gefunden." >&2
    echo "       Zuerst 'bash deploy.sh --dry-run' laufen lassen, damit Container + Webroot gecacht werden." >&2
    exit 2
fi
# shellcheck disable=SC1090
source "$CONF"

CONTAINER="${SAVED_CONTAINER:?SAVED_CONTAINER fehlt in ${CONF}}"
WEBROOT="${SAVED_WEBROOT:?SAVED_WEBROOT fehlt in ${CONF}}"

# ---- Farben ----
if [[ -t 1 ]]; then
    C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'
    C_BLUE=$'\033[34m'; C_DIM=$'\033[2m'; C_RESET=$'\033[0m'
else
    C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_DIM=""; C_RESET=""
fi

PASS=0; WARN=0; FAIL=0; SKIP=0
FAILED_CHECKS=()

run_check() {
    local name="$1"; shift
    local mode="$1"; shift   # FAIL | WARN
    local cmd="$*"

    # Filter: --only
    if [[ -n "$ONLY" && "$ONLY" != "$name" ]]; then
        return 0
    fi

    local out rc
    set +e
    out=$(docker exec -u www-data "$CONTAINER" bash -c "cd '$WEBROOT' && $cmd" 2>&1)
    rc=$?
    set -e

    if [[ "$rc" -eq 0 ]]; then
        printf "  %-18s ${C_GREEN}PASS${C_RESET}\n" "$name"
        [[ "$VERBOSE" == true && -n "$out" ]] && printf "${C_DIM}%s${C_RESET}\n" "$out"
        PASS=$((PASS + 1))
    else
        if [[ "$mode" == "WARN" ]]; then
            printf "  %-18s ${C_YELLOW}WARN${C_RESET}\n" "$name"
            WARN=$((WARN + 1))
        else
            printf "  %-18s ${C_RED}FAIL${C_RESET}\n" "$name"
            FAIL=$((FAIL + 1))
            FAILED_CHECKS+=("$name")
        fi
        printf "${C_DIM}%s${C_RESET}\n" "$out" | head -n 20
    fi
}

skip_check() {
    local name="$1"
    local reason="$2"
    if [[ -n "$ONLY" && "$ONLY" != "$name" ]]; then
        return 0
    fi
    printf "  %-18s ${C_BLUE}SKIP${C_RESET} ${C_DIM}(%s)${C_RESET}\n" "$name" "$reason"
    SKIP=$((SKIP + 1))
}

# ---- Header ----
printf "${C_BLUE}▸ moodle-cicd precheck — %s${C_RESET}\n" "$PLUGIN_COMPONENT"
printf "  Container: %s\n" "$CONTAINER"
printf "  Plugin:    %s\n\n" "$WEBROOT/$PLUGIN_REL_PATH"

# ---- Preflight: Plugin im Container vorhanden? ----
if ! docker exec "$CONTAINER" test -d "$WEBROOT/$PLUGIN_REL_PATH"; then
    printf "${C_RED}ERROR:${C_RESET} Plugin-Ordner nicht im Container: %s\n" "$WEBROOT/$PLUGIN_REL_PATH" >&2
    echo "       Erst 'bash deploy.sh' ausführen, damit das Plugin im Container landet." >&2
    exit 2
fi

# ---- Checks ----

# phplint — Syntax-Check über alle PHP-Dateien
run_check "phplint" FAIL \
    "find $PLUGIN_REL_PATH -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null"

# phpcs (Moodle Coding Standard)
# -n  : warnings sichtbar, aber nicht blockierend (Errors bleiben hart-blocker).
#       Strict-Mode bewusst per --strict aktivierbar.
PHPCS_FLAGS="-n"
if [[ "${PHPCS_STRICT:-0}" == "1" ]]; then
    PHPCS_FLAGS=""
fi
if docker exec "$CONTAINER" test -f vendor/bin/phpcs; then
    run_check "phpcs" FAIL \
        "vendor/bin/phpcs $PHPCS_FLAGS --standard=moodle --extensions=php $PLUGIN_REL_PATH"
else
    skip_check "phpcs" "vendor/bin/phpcs nicht installiert"
fi

# phpdoc — grep-based: jede PHP-Datei muss @package enthalten
run_check "phpdoc" FAIL \
    "test -z \"\$(find $PLUGIN_REL_PATH -name '*.php' -exec grep -L '@package' {} +)\""

# xmllint install.xml — fällt zurück auf PHP DOMDocument, falls xmllint im Container fehlt.
HAS_XMLLINT=true
if ! docker exec "$CONTAINER" command -v xmllint >/dev/null 2>&1; then
    HAS_XMLLINT=false
fi

xml_check_cmd() {
    local file="$1"
    if [[ "$HAS_XMLLINT" == true ]]; then
        echo "xmllint --noout $file"
    else
        # PHP-DOM Fallback: parsen + bei Fehler exit 1.
        echo "php -r 'libxml_use_internal_errors(true); \$d=new DOMDocument(); if(!\$d->load(\"$file\")){foreach(libxml_get_errors() as \$e){fwrite(STDERR,\$e->message);} exit(1);}'"
    fi
}

if docker exec "$CONTAINER" test -f "$PLUGIN_REL_PATH/db/install.xml"; then
    run_check "xmllint-install" FAIL "$(xml_check_cmd "$PLUGIN_REL_PATH/db/install.xml")"
else
    skip_check "xmllint-install" "keine db/install.xml"
fi

# xmllint thirdpartylibs.xml (optional)
if docker exec "$CONTAINER" test -f "$PLUGIN_REL_PATH/thirdpartylibs.xml"; then
    run_check "xmllint-tpl" FAIL "$(xml_check_cmd "$PLUGIN_REL_PATH/thirdpartylibs.xml")"
else
    skip_check "xmllint-tpl" "keine thirdpartylibs.xml"
fi

# Savepoint-Konsistenz: letzte upgrade_plugin_savepoint == $plugin->version
if docker exec "$CONTAINER" test -f "$PLUGIN_REL_PATH/db/upgrade.php"; then
    run_check "savepoint" FAIL "
        VERSION=\$(grep -oE '\\\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' $PLUGIN_REL_PATH/version.php | grep -oE '[0-9]+');
        LAST=\$(grep -oE 'savepoint\\([^)]*[0-9]{10}' $PLUGIN_REL_PATH/db/upgrade.php | grep -oE '[0-9]{10}' | tail -1);
        test \"\$VERSION\" = \"\$LAST\" || { echo \"version.php=\$VERSION  upgrade.php=\$LAST\" >&2; exit 1; }
    "
else
    skip_check "savepoint" "keine db/upgrade.php (erste Version)"
fi

# lang — nur en/ im Release-relevanten Zustand (WARN, kein FAIL, Dev-Lang OK)
run_check "lang-only-en" WARN "
    LANGS=\$(ls $PLUGIN_REL_PATH/lang 2>/dev/null | tr '\\n' ' ');
    test \"\$LANGS\" = \"en \" || { echo \"Zusätzliche lang-packs: \$LANGS\" >&2; exit 1; }
"

# PHPUnit
if [[ "$NO_PHPUNIT" == false ]]; then
    if docker exec "$CONTAINER" test -f vendor/bin/phpunit; then
        if docker exec "$CONTAINER" test -d "$PLUGIN_REL_PATH/tests"; then
            run_check "phpunit" FAIL \
                "vendor/bin/phpunit --testsuite ${PLUGIN_COMPONENT}_testsuite --no-coverage"
        else
            skip_check "phpunit" "kein tests/-Verzeichnis"
        fi
    else
        skip_check "phpunit" "vendor/bin/phpunit nicht installiert"
    fi
fi

# Behat (opt-in)
if [[ "$WITH_BEHAT" == true ]]; then
    if docker exec "$CONTAINER" test -f vendor/bin/behat; then
        run_check "behat" FAIL \
            "vendor/bin/behat --profile=chrome --tags=@${PLUGIN_COMPONENT}"
    else
        skip_check "behat" "vendor/bin/behat nicht installiert"
    fi
else
    skip_check "behat" "opt-in via --with-behat"
fi

# ---- Summary ----
echo
printf "Summary: ${C_GREEN}PASS:%d${C_RESET}  ${C_YELLOW}WARN:%d${C_RESET}  ${C_RED}FAIL:%d${C_RESET}  ${C_BLUE}SKIP:%d${C_RESET}\n" \
    "$PASS" "$WARN" "$FAIL" "$SKIP"

if [[ "$FAIL" -gt 0 ]]; then
    printf "\n${C_RED}Failed checks:${C_RESET} %s\n" "${FAILED_CHECKS[*]}"
    exit 1
fi
exit 0
