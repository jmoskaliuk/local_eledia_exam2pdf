#!/usr/bin/env bash
# precheck.sh — Lokaler Moodle-Plugin-QA-Check via moodle-plugin-ci
#
# Nutzt dieselben moodle-plugin-ci-Befehle wie .github/workflows/moodle-ci.yml,
# ausgeführt per docker exec im Orb-Container. Damit sind lokale und remote
# Checks identisch — "lokal grün" = "remote grün".
#
# Voraussetzung:
#   1. deploy.sh wurde mindestens einmal erfolgreich ausgeführt
#   2. bash bin/setup-plugin-ci.sh wurde einmalig ausgeführt
#
# Usage:
#   ./bin/precheck.sh                  # Alle Checks (ohne Behat)
#   ./bin/precheck.sh --with-behat     # Zusätzlich Behat-Feature-Tests
#   ./bin/precheck.sh --only phpcs     # Nur einen einzelnen Check
#   ./bin/precheck.sh --no-phpunit     # PHPUnit auslassen
#   ./bin/precheck.sh --verbose        # Alle Check-Outputs zeigen
#   ./bin/precheck.sh --legacy         # Fallback: raw vendor/bin (ohne moodle-plugin-ci)
#
# Exit: 0 bei PASS, 1 bei FAIL, 2 bei Config-Problem

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
LEGACY=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-behat)  WITH_BEHAT=true; shift ;;
        --no-phpunit)  NO_PHPUNIT=true; shift ;;
        --only)        ONLY="$2"; shift 2 ;;
        --verbose)     VERBOSE=true; shift ;;
        --legacy)      LEGACY=true; shift ;;
        -h|--help)
            sed -n '3,16p' "$0"
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
    echo "       Zuerst 'bash deploy.sh --dry-run' laufen lassen." >&2
    exit 2
fi
# shellcheck disable=SC1090
source "$CONF"

CONTAINER="${SAVED_CONTAINER:?SAVED_CONTAINER fehlt in ${CONF}}"
WEBROOT="${SAVED_WEBROOT:?SAVED_WEBROOT fehlt in ${CONF}}"

# moodle-plugin-ci expects the Moodle project root. In our local setup WEBROOT
# points to Moodle's public/ dir, so derive project root when needed.
MOODLE_ROOT="$WEBROOT"
if [[ "${WEBROOT%/}" == */public ]]; then
    MOODLE_ROOT="${WEBROOT%/public}"
fi

# ---- moodle-plugin-ci Pfad ----
CI_BIN="/opt/moodle-plugin-ci/bin/moodle-plugin-ci"

if [[ "$LEGACY" == false ]]; then
    if ! docker exec "$CONTAINER" test -x "$CI_BIN" 2>/dev/null; then
        echo "ERROR: moodle-plugin-ci nicht gefunden unter ${CI_BIN}" >&2
        echo "       Zuerst 'bash bin/setup-plugin-ci.sh' ausführen." >&2
        echo "       Oder --legacy für den alten Modus nutzen." >&2
        exit 2
    fi
fi

# ---- Farben ----
if [[ -t 1 ]]; then
    C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'
    C_BLUE=$'\033[34m'; C_DIM=$'\033[2m'; C_RESET=$'\033[0m'
else
    C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_DIM=""; C_RESET=""
fi

PASS=0; WARN=0; FAIL=0; SKIP=0
FAILED_CHECKS=()

# ---- Helper: docker exec mit moodle-plugin-ci ----
mpci() {
    # Führt moodle-plugin-ci im Container aus, mit korrektem Moodle- und Plugin-Pfad.
    docker exec -u www-data \
        -e MOODLE_DIR="$MOODLE_ROOT" \
        "$CONTAINER" \
        "$CI_BIN" "$@" "$WEBROOT/$PLUGIN_REL_PATH"
}

mpci_phpdoc() {
    # phpdoc should validate plugin code, not bundled third-party vendor code.
    docker exec -u www-data \
        -e MOODLE_DIR="$MOODLE_ROOT" \
        "$CONTAINER" bash -lc '
            tmpdir=$(mktemp -d /tmp/'"$PLUGIN_SHORTNAME"'_phpdoc.XXXXXX)
            trap "rm -rf \"$tmpdir\"" EXIT
            cp -a "'"$WEBROOT/$PLUGIN_REL_PATH"'"/. "$tmpdir"/
            rm -rf "$tmpdir/vendor"
            "'"$CI_BIN"'" phpdoc --max-warnings 0 "$tmpdir"
        '
}

mpci_phpmd() {
    # phpmd on bundled vendor libraries is noisy and memory-intensive.
    docker exec -u www-data \
        -e MOODLE_DIR="$MOODLE_ROOT" \
        "$CONTAINER" bash -lc '
            tmpdir=$(mktemp -d /tmp/'"$PLUGIN_SHORTNAME"'_phpmd.XXXXXX)
            trap "rm -rf \"$tmpdir\"" EXIT
            cp -a "'"$WEBROOT/$PLUGIN_REL_PATH"'"/. "$tmpdir"/
            rm -rf "$tmpdir/vendor"
            "'"$CI_BIN"'" phpmd "$tmpdir"
        '
}

mpci_grunt() {
    # grunt-gherkin-lint has a module-level features[] state that is NOT reset between
    # task invocations. When the plugin's feature files appear both as absolute paths
    # (from the plugin component run) and as relative paths (from the full Moodle scan),
    # gherkin-lint flags each file as a duplicate of itself — a known false positive.
    # We detect this pattern: if EVERY no-dupe-feature-names violation refers back to
    # the same file that was just reported as the current file (self-referential), the
    # gherkinlint failure is entirely noise and we suppress it.
    local raw rc
    set +e
    raw=$(mpci grunt --max-lint-warnings 0 2>&1 | tr -d '\r' | sed $'s/\x1b\\[[0-9;]*[a-zA-Z]//g')
    rc=$?
    set -e

    if [[ "$rc" -eq 0 ]]; then
        echo "$raw"
        return 0
    fi

    # Check if all no-dupe-feature-names violations are self-referential.
    # Scan through lines, tracking the last *.feature path header.
    local lastfile="" line dupe real_violations=0
    while IFS= read -r line; do
        if echo "$line" | grep -qE 'no-dupe-feature-names'; then
            dupe=$(echo "$line" | sed -n 's/.*already used in: \([^[:space:]]*\).*/\1/p')
            if [[ -z "$dupe" || "$lastfile" != *"$dupe"* ]]; then
                real_violations=$((real_violations + 1))
            fi
        elif echo "$line" | grep -qE '\.feature$'; then
            lastfile="$line"
        fi
    done <<< "$raw"

    if [[ "$real_violations" -eq 0 ]]; then
        # Only self-referential false positives — treat as clean.
        echo "$raw" | grep -vE 'no-dupe-feature-names|Warning: Task .gherkinlint. failed|Aborted due to warnings'
        return 0
    fi

    echo "$raw"
    return "$rc"
}

# ---- Helper: docker exec raw (für Legacy-Modus) ----
dexec() {
    docker exec -u www-data "$CONTAINER" bash -c "cd '$WEBROOT' && $*"
}

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
    out=$(eval "$cmd" 2>&1)
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
        if [[ "$VERBOSE" == true ]]; then
            printf "${C_DIM}%s${C_RESET}\n" "$out"
        else
            printf "${C_DIM}%s${C_RESET}\n" "$out" | head -n 30
        fi
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
if [[ "$LEGACY" == true ]]; then
    printf "${C_BLUE}▸ precheck (legacy) — %s${C_RESET}\n" "$PLUGIN_COMPONENT"
else
    printf "${C_BLUE}▸ precheck (moodle-plugin-ci) — %s${C_RESET}\n" "$PLUGIN_COMPONENT"
fi
printf "  Container: %s\n" "$CONTAINER"
printf "  Moodle:    %s\n" "$MOODLE_ROOT"
printf "  Plugin:    %s\n\n" "$WEBROOT/$PLUGIN_REL_PATH"

# ---- Preflight: Plugin im Container vorhanden? ----
if ! docker exec "$CONTAINER" test -d "$WEBROOT/$PLUGIN_REL_PATH"; then
    printf "${C_RED}ERROR:${C_RESET} Plugin-Ordner nicht im Container: %s\n" "$WEBROOT/$PLUGIN_REL_PATH" >&2
    echo "       Erst 'bash deploy.sh' ausführen." >&2
    exit 2
fi

# ===========================================================================
# CHECKS — moodle-plugin-ci Modus (identisch zu moodle-ci.yml)
# ===========================================================================
if [[ "$LEGACY" == false ]]; then

    # -- Statische Checks (brauchen kein initialisiertes Moodle) --

    run_check "phplint" FAIL \
        "mpci phplint"

    run_check "phpmd" WARN \
        "mpci_phpmd"

    # Identisch zu moodle-ci.yml Zeile 99
    run_check "phpcs" FAIL \
        "mpci phpcs --max-warnings 0"

    # Identisch zu moodle-ci.yml Zeile 103
    run_check "phpdoc" FAIL \
        "mpci_phpdoc"

    run_check "validate" FAIL \
        "mpci validate"

    run_check "savepoints" FAIL \
        "mpci savepoints"

    run_check "mustache" FAIL \
        "mpci mustache"

    # Moodle's ignorefiles task expects this path in some local setups.
    docker exec -u www-data "$CONTAINER" mkdir -p \
        "$WEBROOT/local/codechecker/vendor/phpcompatibility/php-compatibility" >/dev/null 2>&1 || true

    run_check "grunt" WARN \
        "mpci_grunt"

    # -- PHPUnit --
    if [[ "$NO_PHPUNIT" == false ]]; then
        # Identisch zu moodle-ci.yml Zeile 123
        run_check "phpunit" FAIL \
            "mpci phpunit --fail-on-warning"
    else
        skip_check "phpunit" "ausgeschlossen via --no-phpunit"
    fi

    # -- Behat (opt-in) --
    if [[ "$WITH_BEHAT" == true ]]; then
        # Identisch zu moodle-ci.yml Zeile 127
        # Profil "chrome" erfordert laufenden Selenium-Container (demo-selenium-1).
        BEHAT_PROFILE="${BEHAT_PROFILE:-chrome}"
        run_check "behat" FAIL \
            "mpci behat --profile $BEHAT_PROFILE"
    else
        skip_check "behat" "opt-in via --with-behat"
    fi

# ===========================================================================
# LEGACY CHECKS — raw vendor/bin (Fallback ohne moodle-plugin-ci)
# ===========================================================================
else

    run_check "phplint" FAIL \
        "dexec 'find $PLUGIN_REL_PATH -name \"*.php\" -print0 | xargs -0 -n1 php -l >/dev/null'"

    PHPCS_FLAGS="-n"
    if [[ "${PHPCS_STRICT:-0}" == "1" ]]; then
        PHPCS_FLAGS=""
    fi
    if docker exec "$CONTAINER" test -f "$WEBROOT/vendor/bin/phpcs"; then
        run_check "phpcs" FAIL \
            "dexec 'vendor/bin/phpcs $PHPCS_FLAGS --standard=moodle --extensions=php $PLUGIN_REL_PATH'"
    else
        skip_check "phpcs" "vendor/bin/phpcs nicht installiert"
    fi

    run_check "phpdoc" FAIL \
        "dexec 'test -z \"\$(find $PLUGIN_REL_PATH -name \"*.php\" -exec grep -L \"@package\" {} +)\"'"

    if docker exec "$CONTAINER" test -f "$WEBROOT/$PLUGIN_REL_PATH/db/install.xml"; then
        run_check "xmllint" FAIL \
            "dexec 'php -r \"libxml_use_internal_errors(true); \\\$d=new DOMDocument(); if(!\\\$d->load(\\\"$PLUGIN_REL_PATH/db/install.xml\\\")){foreach(libxml_get_errors() as \\\$e){fwrite(STDERR,\\\$e->message);} exit(1);}\"'"
    else
        skip_check "xmllint" "keine db/install.xml"
    fi

    if [[ "$NO_PHPUNIT" == false ]]; then
        if docker exec "$CONTAINER" test -f "$WEBROOT/vendor/bin/phpunit"; then
            run_check "phpunit" FAIL \
                "dexec 'vendor/bin/phpunit --testsuite ${PLUGIN_COMPONENT}_testsuite --no-coverage'"
        else
            skip_check "phpunit" "vendor/bin/phpunit nicht installiert"
        fi
    else
        skip_check "phpunit" "ausgeschlossen via --no-phpunit"
    fi

    if [[ "$WITH_BEHAT" == true ]]; then
        BEHAT_PROFILE="${BEHAT_PROFILE:-default}"
        BEHAT_TAGS="@${PLUGIN_COMPONENT}"
        if [[ "$BEHAT_PROFILE" == "default" ]]; then
            BEHAT_TAGS="@${PLUGIN_COMPONENT}&&~@javascript"
        fi
        BEHAT_CONFIG_PATH="${BEHAT_CONFIG_PATH:-/var/www/behatdata/behatrun/behat/behat.yml}"
        if ! docker exec "$CONTAINER" test -f "$BEHAT_CONFIG_PATH"; then
            skip_check "behat" "behat.yml nicht gefunden — zuerst behat:init"
        else
            docker exec -u root "$CONTAINER" rm -rf /tmp/behat_gherkin_cache >/dev/null 2>&1 || true
            run_check "behat" FAIL \
                "dexec 'vendor/bin/behat -c \"$BEHAT_CONFIG_PATH\" --profile=$BEHAT_PROFILE --tags=\"$BEHAT_TAGS\"'"
        fi
    else
        skip_check "behat" "opt-in via --with-behat"
    fi
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
