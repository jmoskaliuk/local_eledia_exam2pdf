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
 * Provides steps that trigger the plugin observer after the standard quiz
 * attempt generator has written the attempt to the database. The built-in
 * generator step "user X has attempted Y with responses:" does NOT fire the
 * attempt_submitted event, so the plugin observer never runs during Behat.
 */
class behat_local_eledia_exam2pdf extends behat_base {
    /**
     * Triggers the exam2pdf observer for the latest attempt of a user on a quiz.
     *
     * This must be called AFTER the standard generator step that creates the
     * quiz attempt, because that step writes the attempt row without firing
     * the attempt_submitted event.
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

        // Build the event object the observer expects.
        $cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid'      => $attempt->id,
            'relateduserid' => $user->id,
            'context'       => $context,
            'other'         => [
                'quizid'       => $quiz->id,
                'submitterid'  => $user->id,
            ],
        ]);

        // Call the observer directly (triggering via $event->trigger() would
        // go through the event manager and all registered observers, which is
        // fine too, but a direct call is more predictable in tests).
        \local_eledia_exam2pdf\observer::on_attempt_submitted($event);
    }

    /**
     * Asserts that a PDF record was created for the user's latest attempt.
     *
     * This is a diagnostic step that checks the database state directly
     * and reports detailed information on failure.
     *
     * @Then the exam2pdf PDF record for :username in :quizname should exist
     * @param string $username The username (e.g. "student1").
     * @param string $quizname The quiz name (e.g. "Compliance Exam").
     */
    public function the_exam2pdf_pdf_record_should_exist(
        string $username,
        string $quizname
    ): void {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);

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
            IGNORE_MISSING
        );

        if (!$attempt) {
            throw new \Exception(
                "No finished attempt for {$username} in '{$quizname}'."
            );
        }

        // Gather grading diagnostics.
        require_once($CFG->libdir . '/gradelib.php');
        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'quiz',
            'iteminstance'  => $quiz->id,
            'courseid'     => $quiz->course,
        ]);
        $gradepass = ($gradeitem && !empty($gradeitem->gradepass))
            ? (float) $gradeitem->gradepass : 0.0;
        $grade = ($quiz->sumgrades > 0)
            ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;

        // Check config.
        $config = \local_eledia_exam2pdf\helper::get_effective_config($quiz->id);

        // Check PDF record.
        $record = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attempt->id],
            '*',
            IGNORE_MISSING
        );

        // Check stored file.
        $fileinfo = 'N/A';
        if ($record) {
            $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
            $fileinfo = $file ? 'OK (size=' . $file->get_filesize() . ')' : 'NOT_FOUND';
        }

        $diag = sprintf(
            "attempt.id=%d, attempt.sumgrades=%s, attempt.state=%s, " .
            "quiz.id=%d, quiz.sumgrades=%s, quiz.grade=%s, " .
            "grade=%.2f, gradepass=%.2f, passed=%s, " .
            "config.pdfgeneration=%s, config.pdfscope=%s, config.studentdownload=%s, " .
            "pdf_record=%s, stored_file=%s",
            $attempt->id,
            $attempt->sumgrades ?? 'NULL',
            $attempt->state,
            $quiz->id,
            $quiz->sumgrades,
            $quiz->grade,
            $grade,
            $gradepass,
            ($gradepass > 0 && $grade >= $gradepass) ? 'YES' : ($gradepass <= 0 ? 'YES(no_pass_grade)' : 'NO'),
            $config['pdfgeneration'] ?? 'NULL',
            $config['pdfscope'] ?? 'NULL',
            isset($config['studentdownload']) ? ($config['studentdownload'] ? '1' : '0') : 'NULL',
            $record ? "id={$record->id}, cmid={$record->cmid}, userid={$record->userid}" : 'NOT_FOUND',
            $fileinfo
        );

        if (!$record) {
            throw new \Exception(
                "No PDF record found for attempt {$attempt->id}. Diagnostics: {$diag}"
            );
        }

        // Also verify the stored file exists.
        if (!$fileinfo || $fileinfo === 'NOT_FOUND') {
            throw new \Exception(
                "PDF record exists (id={$record->id}) but stored file not found. Diagnostics: {$diag}"
            );
        }
    }

    /**
     * Asserts the download button is visible; on failure reports the diagnostic div.
     *
     * This step checks the browser page for "Download certificate". If the
     * text is missing, it reads the #exam2pdf-diag element (injected by the
     * temporary diagnostic block in the hook callback) and includes its
     * content in the exception message so CI logs show the exact variable
     * state.
     *
     * @Then the exam2pdf download button should be visible
     */
    public function the_exam2pdf_download_button_should_be_visible(): void {
        $page = $this->getSession()->getPage();

        // Check for the download button text.
        $pagetext = $page->getText();
        if (strpos($pagetext, 'Download certificate') !== false) {
            return;
        }

        // Button not found — gather diagnostics from the page.
        $diagel = $page->find('css', '#exam2pdf-diag');
        $diagtext = $diagel ? $diagel->getText() : 'DIAG_ELEMENT_NOT_FOUND';

        // Also check for the download wrapper div in the raw HTML.
        $rawhtml = $page->getContent();
        $haswrapper = (strpos($rawhtml, 'local-eledia-exam2pdf-downloadwrap') !== false)
            ? 'YES' : 'NO';

        // Check if any exam2pdf content exists in page source.
        $exam2pdfcount = substr_count($rawhtml, 'exam2pdf');

        throw new \Exception(
            'Download certificate button NOT found on page. '
            . 'Diagnostic div: [' . $diagtext . '] '
            . 'Wrapper div in HTML: ' . $haswrapper . ', '
            . 'exam2pdf occurrences in source: ' . $exam2pdfcount . ', '
            . 'Page text length: ' . strlen($pagetext)
        );
    }
}
