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
        try {
            self::ensure_pdf_for_attempt((int) $event->objectid, false);
        } catch (\Throwable $e) {
            $attemptid = (int) $event->objectid;
            // In test environments, swallowing exceptions makes real bugs invisible.
            // Re-throw so tests surface the actual problem instead of silently
            // asserting "0 PDFs" as a symptom.
            if (
                (defined('PHPUNIT_TEST') && PHPUNIT_TEST) ||
                (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING)
            ) {
                throw $e;
            }
            debugging(
                'local_eledia_exam2pdf: PDF generation failed for attempt '
                    . $attemptid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Ensures a PDF record exists for an attempt and returns that record.
     *
     * In automatic mode this is used by the event observer. In on-demand mode
     * callers can pass `$allowondemand = true` to generate on click.
     *
     * @param int $attemptid Quiz attempt ID.
     * @param bool $allowondemand Whether on-demand generation is allowed.
     * @return \stdClass|null Generated/existing DB record, or null when attempt is out of scope.
     */
    public static function ensure_pdf_for_attempt(int $attemptid, bool $allowondemand = false): ?\stdClass {
        global $DB, $CFG;

        // Load attempt — use string 'finished' directly to avoid class-loading
        // issues with \mod_quiz\quiz_attempt during event dispatch.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt || $attempt->state !== 'finished') {
            return null;
        }

        // Load quiz and effective config.
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $config = helper::get_effective_config($quiz->id);

        // In automatic mode we only generate when explicitly configured.
        if (($config['pdfgeneration'] ?? 'auto') !== 'auto' && !$allowondemand) {
            return null;
        }

        // Scope check (passed/all).
        if (!helper::is_in_pdf_scope($attempt, $quiz, $config)) {
            return null;
        }

        // Reuse existing record when file is still present.
        $existing = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attemptid],
            '*',
            IGNORE_MISSING
        );
        if ($existing) {
            if (helper::get_stored_file($existing)) {
                return $existing;
            }
            // Cleanup orphaned row and regenerate.
            $DB->delete_records('local_eledia_exam2pdf', ['id' => $existing->id]);
        }

        // Generate the PDF binary.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $pdfcontent = pdf\generator::generate($attemptobj, $quiz, $config);

        $filename = self::build_filename($attempt, $quiz);

        // Store the PDF in Moodle's file system.
        $cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

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
        try {
            $record->id = $DB->insert_record('local_eledia_exam2pdf', $record);
        } catch (\dml_write_exception $e) {
            // Handle race: another process inserted the same attempt first.
            $existing = $DB->get_record(
                'local_eledia_exam2pdf',
                ['attemptid' => $attemptid],
                '*',
                IGNORE_MISSING
            );
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

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
        $fs->delete_area_files(
            $context->id,
            'local_eledia_exam2pdf',
            'attempt_pdf',
            $record->id
        );

        try {
            $storedfile = $fs->create_file_from_string($fileinfo, $pdfcontent);
        } catch (\Throwable $e) {
            $DB->delete_records('local_eledia_exam2pdf', ['id' => $record->id]);
            throw $e;
        }

        // Update content hash in DB record.
        $DB->set_field(
            'local_eledia_exam2pdf',
            'contenthash',
            $storedfile->get_contenthash(),
            ['id' => $record->id]
        );
        $record->contenthash = $storedfile->get_contenthash();

        // Handle output: email and/or prepare for download.
        $outputmode = $config['outputmode'] ?? 'download';

        if (in_array($outputmode, ['email', 'both'], true)) {
            self::send_email($attempt, $quiz, $config, $storedfile, $filename);
        }

        return $record;
    }

    // Private helpers.

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
     * @param \stdClass    $attempt    The quiz attempt record.
     * @param \stdClass    $quiz       The quiz record.
     * @param array        $config     Effective config values.
     * @param \stored_file $storedfile Moodle stored file object.
     * @param string       $filename   Attachment filename.
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
        $extraraw     = $config['emailrecipients'] ?? '';
        $extraaddrs   = array_filter(array_map('trim', explode(',', $extraraw)));
        $recipients   = array_unique(array_merge($recipients, $extraaddrs));

        // Resolve subject / body placeholders.
        $replacements = [
            '{quizname}' => $quiz->name,
            '{username}' => fullname($learner),
            '{fullname}' => fullname($learner),
            '{date}'     => userdate(time()),
        ];
        $subject = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $config['emailsubject'] ?? $quiz->name
        );
        $body = str_replace(
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
            $recipient->id    = -1;
            if ($address === $learner->email) {
                $recipient = $learner;
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
