#!/usr/bin/env bash
# Regenerate amd/build/ via Moodle's canonical grunt pipeline in the local
# Orb container. Works around the "public/local/codechecker" ignorefiles
# abort by running `grunt amd` directly with --force, bypassing the full
# moodle-plugin-ci wrapper.
#
# Usage (from the repo root):
#   bash bin/regenerate-amd-build.sh
#
# After this script finishes successfully, `amd/build/` contains the canonical
# Moodle-grunt output (3x .min.js + 3x .min.js.map) ready to be committed.

set -euo pipefail

CONTAINER="demo-webserver-1"
MOODLE_ROOT="/var/www/site/moodle"
PLUGIN_PATH="$MOODLE_ROOT/public/local/eledia_exam2pdf"

echo "==> Sync repo -> .deploy -> container"
bash bin/sync-mirror.sh
bash deploy.sh --source .deploy --skip-upgrade --skip-cache

echo
echo "==> Verify plugin exists in container at $PLUGIN_PATH"
docker exec "$CONTAINER" test -d "$PLUGIN_PATH" || {
    echo "ERROR: plugin not found at $PLUGIN_PATH in container."
    echo "Container reports these candidate paths:"
    docker exec "$CONTAINER" find /var/www -maxdepth 6 -type d -name eledia_exam2pdf 2>/dev/null
    exit 1
}

echo
echo "==> Run Moodle grunt amd in container (with --force to skip ignorefiles abort)"
docker exec "$CONTAINER" bash -c "cd $MOODLE_ROOT && npx grunt amd --root=public/local/eledia_exam2pdf --force" || {
    echo "grunt returned non-zero; checking if amd/build was still written..."
}

echo
echo "==> Verify container-side amd/build was written"
docker exec "$CONTAINER" ls -la "$PLUGIN_PATH/amd/build/" || {
    echo "ERROR: no amd/build directory in container after grunt run."
    exit 1
}

echo
echo "==> Copy canonical amd/build from container back to repo"
rm -rf amd/build
docker cp "$CONTAINER:$PLUGIN_PATH/amd/build" amd/

echo
echo "==> Result"
ls -la amd/build/

echo
echo "==> git status"
git status --short amd/build/

echo
echo "Done. If you see .min.js and .min.js.map pairs above, the build is ready."
echo "Next: paste 'ls -la amd/build/' back into the Cowork chat so I can pick it up."
