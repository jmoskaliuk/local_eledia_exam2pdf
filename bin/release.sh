#!/usr/bin/env bash
# release.sh — Clean release ZIP aus git archive, honoriert .gitattributes export-ignore
#
# Usage: ./bin/release.sh [output-dir]   (default: /tmp)
#
# Produziert: <output-dir>/<shortname>-<version>.zip
# Liest Component + Version direkt aus version.php.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "${SCRIPT_DIR}")"
OUT_DIR="${1:-/tmp}"

cd "${PLUGIN_DIR}"

# Derive Component + Shortname + Version aus version.php (BSD-kompatibles sed).
COMPONENT=$(sed -nE "s/.*\\\$plugin->component[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)
SHORTNAME="${COMPONENT#*_}"   # local_eledia_exam2pdf -> eledia_exam2pdf
VERSION=$(sed -nE "s/.*\\\$plugin->release[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)

if [[ -z "${COMPONENT}" || -z "${SHORTNAME}" || -z "${VERSION}" ]]; then
    echo "ERROR: Konnte component/release nicht aus version.php parsen." >&2
    exit 2
fi

# Working tree muss clean sein — git archive baut aus HEAD, nicht aus dem WD.
if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Working tree hat uncommitete Änderungen. Erst committen oder stashen." >&2
    git status --short
    exit 3
fi

ZIP="${OUT_DIR}/${SHORTNAME}-${VERSION}.zip"

echo "Building ${ZIP}"
echo "  component: ${COMPONENT}"
echo "  shortname: ${SHORTNAME}"
echo "  version:   ${VERSION}"
echo

# --prefix sorgt für den Frankenstyle-Top-Level-Ordner, den das Plugins Directory erwartet.
git archive --format=zip --prefix="${SHORTNAME}/" -o "${ZIP}" HEAD

# Verifikation
echo "── ZIP contents (first 20 entries) ──"
unzip -l "${ZIP}" | head -25

echo
echo "── Forbidden-path scan ──"
forbidden=$(unzip -l "${ZIP}" | grep -E "\.git/|node_modules/|\.DS_Store|\.idea/|\.vscode/|\.submission-draft|\.deploy/" || true)
if [[ -n "${forbidden}" ]]; then
    echo "WARNING: ZIP enthält Pfade, die ausgeschlossen sein sollten:"
    echo "${forbidden}"
    echo "In .gitattributes ergänzen und neu builden."
    exit 4
fi
echo "(clean)"

echo
echo "── Top-level structure check ──"
# Nur echte Datei-/Ordnereinträge betrachten: die enthalten dank --prefix immer
# einen Slash. awk-Header-Zeilen, der --------- Trenner und die Summary-Zeile
# haben keinen Slash und fallen damit automatisch raus — robust gegen
# git-archive-Commit-Hash-Kommentarzeilen, die NR>3 verschieben würden.
top=$(unzip -l "${ZIP}" | awk '{print $4}' | grep '/' | cut -d/ -f1 | sort -u)
echo "${top}"
if [[ "$(echo "${top}" | wc -l | tr -d ' ')" != "1" || "${top}" != "${SHORTNAME}" ]]; then
    echo "ERROR: Top-Level-Dir muss exakt '${SHORTNAME}/' sein — tatsächlich:"
    echo "${top}"
    exit 5
fi

echo
echo "✓ Release ZIP fertig: ${ZIP}"
echo "  Size: $(du -h "${ZIP}" | cut -f1)"
