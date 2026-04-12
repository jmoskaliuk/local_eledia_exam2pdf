<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Post-installation steps for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs after the plugin tables have been created.
 *
 * Imports the bundled User Tours so that admins and teachers get a
 * guided introduction on first visit.
 *
 * @return bool
 */
function xmldb_local_eledia_exam2pdf_install() {
    local_eledia_exam2pdf_import_tours();
    return true;
}

/**
 * Imports all User Tour JSON files shipped with this plugin.
 *
 * Called from both install and upgrade. Safe to call multiple times —
 * each call creates a new tour (Moodle does not deduplicate by name,
 * so callers should guard against repeated imports).
 *
 * @return void
 */
function local_eledia_exam2pdf_import_tours(): void {
    $tourdir = __DIR__ . '/usertours';
    if (!is_dir($tourdir)) {
        return;
    }

    $files = glob($tourdir . '/tour_*.json');
    foreach ($files as $file) {
        $json = file_get_contents($file);
        if ($json === false) {
            continue;
        }
        \tool_usertours\manager::import_tour_from_json($json);
    }
}
