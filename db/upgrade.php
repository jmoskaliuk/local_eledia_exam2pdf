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
    global $DB;

    if ($oldversion < 2026041202) {
        // Import bundled User Tours.
        require_once(__DIR__ . '/install.php');
        local_eledia_exam2pdf_import_tours();

        upgrade_plugin_savepoint(true, 2026041202, 'local', 'eledia_exam2pdf');
    }

    if ($oldversion < 2026041700) {
        $table = new xmldb_table('local_eledia_exam2pdf');
        $oldindex = new xmldb_index('idx_attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        $newindex = new xmldb_index('uniq_attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
        $dbman = $DB->get_manager();

        // Clean up existing duplicate rows before adding a unique index.
        $duplicates = $DB->get_records_sql(
            'SELECT attemptid, MIN(id) AS keepid
               FROM {local_eledia_exam2pdf}
           GROUP BY attemptid
             HAVING COUNT(1) > 1'
        );
        if (!empty($duplicates)) {
            $fs = get_file_storage();
            foreach ($duplicates as $duplicate) {
                $redundant = $DB->get_records_select(
                    'local_eledia_exam2pdf',
                    'attemptid = :attemptid AND id <> :keepid',
                    [
                        'attemptid' => $duplicate->attemptid,
                        'keepid' => $duplicate->keepid,
                    ],
                    'id ASC',
                    'id, cmid'
                );

                foreach ($redundant as $record) {
                    try {
                        $context = \core\context\module::instance($record->cmid);
                        $fs->delete_area_files($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $record->id);
                    } catch (\Throwable $e) {
                        // Best effort cleanup: still delete DB row below.
                        unset($e);
                    }
                    $DB->delete_records('local_eledia_exam2pdf', ['id' => $record->id]);
                }
            }
        }

        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026041700, 'local', 'eledia_exam2pdf');
    }

    if ($oldversion < 2026041800) {
        // 0.6.0: Grading comments default changes from "off" to "on" so the
        // BEWERTUNGSKOMMENTAR block appears in PDFs out of the box. Existing
        // installs that still carry the historical "0" default are flipped
        // once; any explicit "1" is already correct and is not touched.
        $current = get_config('local_eledia_exam2pdf', 'showquestioncomments');
        if ($current === false || (string) $current === '0') {
            set_config('showquestioncomments', 1, 'local_eledia_exam2pdf');
        }

        upgrade_plugin_savepoint(true, 2026041800, 'local', 'eledia_exam2pdf');
    }

    return true;
}
