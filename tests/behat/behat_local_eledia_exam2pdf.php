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
 * Behat step definitions for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL check here — Behat context files must be loadable
// outside the normal Moodle bootstrap.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions for the local_eledia_exam2pdf plugin.
 *
 * Provides steps that simulate the plugin observer after the standard quiz
 * attempt generator has written the attempt to the database. The built-in
 * generator step "user X has attempted Y with responses:" does NOT fire the
 * attempt_submitted event, so the plugin observer never runs during Behat.
 *
 * Instead of calling the real observer (which depends on TCPDF), this step
 * creates the DB record and a dummy PDF file directly. The Behat tests
 * verify download-button visibility, not PDF content.
 */
class behat_local_eledia_exam2pdf extends behat_base {
    /**
     * Simulates the exam2pdf observer for the latest attempt of a user on a quiz.
     *
     * Creates a PDF record and dummy file if the attempt is in scope (passed
     * or all-finished, depending on config). Does nothing when the attempt is
     * out of scope, so "failed-attempt" scenarios correctly see no button.
     *
     * @Given the exam2pdf observer has processed the attempt for :username in :quizname
     * @param string $username The username (e.g. "student1").
     * @param string $quizname The quiz name (e.g. "Compliance Exam").
     */
    public function the_exam2pdf_observer_has_processed_the_attempt_for_in(
        string $username,
        string $quizname
    ): void {
        global $DB;

        // Look up the user.
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        // Look up the quiz.
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);

        // Get the latest finished attempt for this user/quiz.
        $attempt = $DB->get_record_sql(
            "SELECT *
               FROM {quiz_attempts}
              WHERE quiz = :quiz AND userid = :userid AND state = :state
           ORDER BY id DESC",
            [
                'quiz'   => $quiz->id,
                'userid' => $user->id,
                'state'  => 'finished',
            ],
            MUST_EXIST
        );

        // Get effective config (global defaults merged with per-quiz overrides).
        $config = \local_eledia_exam2pdf\helper::get_effective_config($quiz->id);

        // Check if the attempt is in scope for PDF generation.
        if (!\local_eledia_exam2pdf\helper::is_in_pdf_scope($attempt, $quiz, $config)) {
            return;
        }

        // Create the DB record and store a minimal dummy PDF file.
        // This bypasses TCPDF so the Behat test is independent of the PDF
        // rendering engine and only tests the download-button UI logic.
        $cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $retentiondays = (int) ($config['retentiondays'] ?? 365);
        $timeexpires   = $retentiondays > 0 ? (time() + $retentiondays * DAYSECS) : 0;

        $record              = new \stdClass();
        $record->quizid      = $quiz->id;
        $record->cmid        = $cm->id;
        $record->attemptid   = $attempt->id;
        $record->userid      = $user->id;
        $record->timecreated = time();
        $record->timeexpires = $timeexpires;
        $record->contenthash = '';
        $record->id          = $DB->insert_record('local_eledia_exam2pdf', $record);

        // Store a minimal PDF placeholder in the Moodle file system.
        $fs       = get_file_storage();
        $filename = 'behat-test-certificate.pdf';
        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'local_eledia_exam2pdf',
            'filearea'  => 'attempt_pdf',
            'itemid'    => $record->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        $dummypdf   = '%PDF-1.4 dummy content for Behat testing';
        $storedfile = $fs->create_file_from_string($fileinfo, $dummypdf);

        // Update content hash in DB record.
        $DB->set_field(
            'local_eledia_exam2pdf',
            'contenthash',
            $storedfile->get_contenthash(),
            ['id' => $record->id]
        );
    }
}
