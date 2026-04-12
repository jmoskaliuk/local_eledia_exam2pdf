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
 * Event observer for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf;

use mod_quiz\quiz_attempt;

/**
 * Listens for quiz attempt_submitted events and triggers PDF generation
 * when the attempt is passed.
 */
class observer {
    /**
     * Handles \mod_quiz\event\attempt_submitted.
     *
     * @param \mod_quiz\event\attempt_submitted $event The fired event.
     */
    public static function on_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB, $CFG;

        $attemptid = $event->objectid;

        // Load attempt.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt || $attempt->state !== quiz_attempt::FINISHED) {
            return;
        }

        // Load quiz.
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);

        // Determine if the attempt is passed.
        if (!self::is_passed($attempt, $quiz)) {
            return; // No PDF for failed attempts.
        }

        // Avoid duplicate: if a PDF record already exists for this attempt, skip.
        if ($DB->record_exists('local_eledia_exam2pdf', ['attemptid' => $attemptid])) {
            return;
        }

        // Get effective config (global defaults merged with per-quiz overrides).
        $config = helper::get_effective_config($quiz->id);

        // Generate the PDF binary.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        try {
            $attemptobj = quiz_attempt::create($attemptid);
            $pdfcontent = pdf\generator::generate($attemptobj, $quiz, $config);
        } catch (\Throwable $e) {
            // In PHPUnit/Behat runs with --fail-on-warning, swallowing exceptions here
            // makes real bugs invisible to the test suite. Re-throw so tests surface
            // the actual problem instead of asserting "0 PDFs" as a symptom.
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
                throw $e;
            }
            debugging(
                'local_eledia_exam2pdf: PDF generation failed for attempt ' . $attemptid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return;
        }

        // Store the PDF in the Moodle file system.
        $cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $filename = self::build_filename($attempt, $quiz);

        // Calculate expiry timestamp.
        $retentiondays = (int) ($config['retentiondays'] ?? 365);
        $timeexpires   = $retentiondays > 0 ? (time() + $retentiondays * DAYSECS) : 0;

        // Insert DB record first to get the item ID.
        $record              = new \stdClass();
        $record->quizid      = $quiz->id;
        $record->cmid        = $cm->id;
        $record->attemptid   = $attemptid;
        $record->userid      = $attempt->userid;
        $record->timecreated = time();
        $record->timeexpires = $timeexpires;
        $record->contenthash = '';
        $record->id          = $DB->insert_record('local_eledia_exam2pdf', $record);

        // Save to file system.
        $fs        = get_file_storage();
        $fileinfo  = [
            'contextid' => $context->id,
            'component' => 'local_eledia_exam2pdf',
            'filearea'  => 'attempt_pdf',
            'itemid'    => $record->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        // Delete old file for this record if any (idempotency).
        $fs->delete_area_files($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $record->id);

        $storedfile = $fs->create_file_from_string($fileinfo, $pdfcontent);

        // Update content hash in DB record.
        $DB->set_field('local_eledia_exam2pdf', 'contenthash', $storedfile->get_contenthash(), ['id' => $record->id]);

        // Handle output: email and/or prepare for download.
        $outputmode = $config['outputmode'] ?? 'download';

        if (in_array($outputmode, ['email', 'both'], true)) {
            self::send_email($attempt, $quiz, $config, $storedfile, $filename);
        }
    }

    // Private helpers.

    /**
     * Determines whether a quiz attempt reached the passing grade.
     *
     * The pass threshold is stored in the gradebook (grade_items), not in the
     * quiz table itself. We must look it up from grade_item.
     *
     * @param \stdClass $attempt quiz_attempts row.
     * @param \stdClass $quiz    quiz row.
     * @return bool True when the attempt reached or exceeded the pass grade.
     */
    private static function is_passed(\stdClass $attempt, \stdClass $quiz): bool {
        // Fetch the pass grade from the gradebook.
        $gradepass = self::get_quiz_gradepass($quiz);

        // If no passing grade is configured, treat every finished attempt as passed.
        if ($gradepass <= 0) {
            return true;
        }

        // Rescale attempt score to the quiz's grade scale.
        if ($quiz->sumgrades == 0) {
            return false;
        }

        $grade = $attempt->sumgrades / $quiz->sumgrades * $quiz->grade;
        return ($grade >= $gradepass);
    }

    /**
     * Returns the quiz pass grade from the gradebook.
     *
     * @param \stdClass $quiz The quiz row (must contain id and course).
     * @return float The pass grade, or 0 if none is configured.
     */
    private static function get_quiz_gradepass(\stdClass $quiz): float {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'courseid' => $quiz->course,
        ]);

        if ($gradeitem && !empty($gradeitem->gradepass)) {
            return (float) $gradeitem->gradepass;
        }
        return 0.0;
    }

    /**
     * Builds a safe PDF filename for the attempt.
     *
     * @param \stdClass $attempt The quiz attempt row.
     * @param \stdClass $quiz    The quiz row.
     * @return string  e.g. "quiz-my-quiz-attempt-3-20250409.pdf"
     */
    private static function build_filename(\stdClass $attempt, \stdClass $quiz): string {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($quiz->name));
        $date = date('Ymd', $attempt->timefinish ?: time());
        return 'quiz-' . $slug . '-attempt-' . $attempt->attempt . '-' . $date . '.pdf';
    }

    /**
     * Sends the PDF as an email attachment.
     *
     * @param \stdClass           $attempt    The quiz attempt record.
     * @param \stdClass           $quiz       The quiz record.
     * @param array               $config     Effective config values.
     * @param \stored_file        $storedfile Moodle stored file object.
     * @param string              $filename   Attachment filename.
     * @return void
     */
    private static function send_email(
        \stdClass $attempt,
        \stdClass $quiz,
        array $config,
        \stored_file $storedfile,
        string $filename
    ): void {
        global $DB;

        $learner = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);
        $noreply = \core_user::get_noreply_user();

        // Build recipient list: learner + additional addresses.
        $recipients   = [$learner->email];
        $extraaddrs   = array_filter(array_map('trim', explode(',', $config['emailrecipients'] ?? '')));
        $recipients   = array_unique(array_merge($recipients, $extraaddrs));

        // Resolve subject / body placeholders.
        $replacements = [
            '{quizname}' => $quiz->name,
            '{username}'  => fullname($learner),
            '{fullname}'  => fullname($learner),
            '{date}'      => userdate(time()),
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements), $config['emailsubject'] ?? $quiz->name);
        $body    = str_replace(
            array_keys($replacements),
            array_values($replacements),
            get_string('email_body', 'local_eledia_exam2pdf')
        );

        // Write PDF to a temp file for attachment.
        $tempdir  = make_request_directory();
        $filepath = $tempdir . '/' . $filename;
        file_put_contents($filepath, $storedfile->get_content());

        foreach ($recipients as $address) {
            // Build a fake user object for email_to_user().
            $recipient        = new \stdClass();
            $recipient->email = $address;
            $recipient->id    = -1;  // Non-Moodle recipient.
            if ($address === $learner->email) {
                $recipient = $learner; // Use full user object for the learner.
            }

            email_to_user(
                $recipient,
                $noreply,
                $subject,
                $body,
                '',
                $filepath,
                $filename
            );
        }
    }
}
