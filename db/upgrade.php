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
 * Upgrade steps for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes upgrade steps for local_eledia_exam2pdf.
 *
 * @param int $oldversion The previous plugin version.
 * @return bool
 */
function xmldb_local_eledia_exam2pdf_upgrade($oldversion) {
    if ($oldversion < 2026041202) {
        // Import bundled User Tours.
        require_once(__DIR__ . '/install.php');
        local_eledia_exam2pdf_import_tours();

        upgrade_plugin_savepoint(true, 2026041202, 'local', 'eledia_exam2pdf');
    }

    return true;
}
