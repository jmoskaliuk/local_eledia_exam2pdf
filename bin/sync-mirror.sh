#!/usr/bin/env bash
# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

# bin/sync-mirror.sh
# ----------------------------------------------------------------------
# Rebuilds the .deploy/local/eledia_exam2pdf/ mirror from the source of
# truth (the repo root). The mirror is what deploy.sh actually copies
# into the Moodle container; if files in the repo root are added or
# changed but not synced into the mirror, deploys silently drift.
#
# Run this BEFORE every deploy, or wire it into deploy.sh.
# ----------------------------------------------------------------------

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.."; pwd)"
MIRROR="$ROOT/.deploy/local/eledia_exam2pdf"

echo "═══════════════════════════════════════════════════════════════"
echo "  sync-mirror.sh — rebuilding deploy mirror"
echo "═══════════════════════════════════════════════════════════════"
echo "  Source:  $ROOT"
echo "  Mirror:  $MIRROR"
echo

# Wipe the mirror so removed files actually disappear.
mkdir -p "$(dirname "$MIRROR")"
rm -rf "$MIRROR"
mkdir -p "$MIRROR"

# Files & dirs to ship in the mirror. Anything not in this list stays out.
INCLUDES=(
    amd
    classes
    db
    lang
    pix
    tests
    bin
    download.php
    regenerate.php
    report.php
    zip.php
    lib.php
    settings.php
    quizsettings.php
    version.php
    README.md
    CHANGES.md
)

for item in "${INCLUDES[@]}"; do
    src="$ROOT/$item"
    if [[ ! -e "$src" ]]; then
        echo "  skip (missing): $item"
        continue
    fi
    if [[ -d "$src" ]]; then
        rsync -a --delete \
            --exclude='.git' \
            --exclude='.deploy' \
            --exclude='node_modules' \
            --exclude='vendor' \
            "$src" "$MIRROR/"
    else
        cp -p "$src" "$MIRROR/"
    fi
    echo "  synced:        $item"
done

echo
echo "  Mirror rebuilt OK."
echo "═══════════════════════════════════════════════════════════════"
