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
 * Scheduled task: delete expired PDF certificates.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\task;

/**
 * Nightly task that removes PDF files and records whose retention period has elapsed.
 */
class cleanup_expired_pdfs extends \core\task\scheduled_task {

    /**
     * Returns the localised name of this scheduled task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'local_eledia_exam2pdf');
    }

    /**
     * Executes the task: deletes expired PDF files and DB records.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        // Find all expired records (timeexpires > 0 AND timeexpires <= now).
        $sql    = 'SELECT id, cmid FROM {local_eledia_exam2pdf} WHERE timeexpires > 0 AND timeexpires <= :now';
        $params = ['now' => $now];

        $records = $DB->get_records_sql($sql, $params);

        if (empty($records)) {
            mtrace('local_eledia_exam2pdf: no expired PDFs to delete.');
            return;
        }

        $fs      = get_file_storage();
        $deleted = 0;
        $errors  = 0;

        foreach ($records as $record) {
            try {
                $context = \core\context\module::instance($record->cmid);
                $fs->delete_area_files(
                    $context->id,
                    'local_eledia_exam2pdf',
                    'attempt_pdf',
                    $record->id
                );
                $DB->delete_records('local_eledia_exam2pdf', ['id' => $record->id]);
                $deleted++;
            } catch (\Throwable $e) {
                mtrace('local_eledia_exam2pdf: error deleting record ' . $record->id . ': ' . $e->getMessage());
                $errors++;
            }
        }

        mtrace("local_eledia_exam2pdf: deleted {$deleted} expired PDFs. Errors: {$errors}.");
    }
}
