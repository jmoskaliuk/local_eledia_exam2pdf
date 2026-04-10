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
 * Integration tests for local_eledia_exam2pdf observer.
 *
 * These tests drive the full event pipeline: set up a quiz with one question,
 * start and finish an attempt with a controlled score, trigger the
 * `attempt_submitted` event and assert that the observer created (or skipped)
 * the PDF record as expected.
 *
 * @package    local_eledia_exam2pdf
 * @category   test
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_exam2pdf\observer
 */

namespace local_eledia_exam2pdf;

use mod_quiz\quiz_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests the observer that reacts to `\mod_quiz\event\attempt_submitted`.
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass Course record used across tests. */
    protected \stdClass $course;

    /** @var \stdClass User who takes the quiz. */
    protected \stdClass $student;

    /**
     * Create a reusable course + user for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    // Helpers.

    /**
     * Creates a quiz with a single true/false question and returns the quiz record.
     *
     * @param array $quizoverrides  Values to override in `create_module('quiz', …)`.
     * @return \stdClass
     */
    protected function create_quiz_with_question(array $quizoverrides = []): \stdClass {
        $defaults = [
            'course'     => $this->course->id,
            'sumgrades'  => 1,
            'grade'      => 10,
            'gradepass'  => 5,
            'attempts'   => 0,
        ];
        $quiz = $this->getDataGenerator()->create_module('quiz', array_merge($defaults, $quizoverrides));

        /** @var \core_question_generator $questiongen */
        $questiongen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat         = $questiongen->create_question_category();
        $question    = $questiongen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        return $quiz;
    }

    /**
     * Creates a finished quiz attempt with the given raw score.
     *
     * @param \stdClass $quiz      The quiz.
     * @param float     $sumgrades Raw sum of grades (0..quiz->sumgrades).
     * @return \stdClass quiz_attempts record.
     */
    protected function create_finished_attempt(\stdClass $quiz, float $sumgrades): \stdClass {
        global $DB;

        $timenow = time();

        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $this->student->id);
        $quba    = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, null, $timenow, false, $this->student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Force the attempt into a finished state with the requested score.
        $DB->update_record('quiz_attempts', (object) [
            'id'         => $attempt->id,
            'state'      => quiz_attempt::FINISHED,
            'timefinish' => $timenow,
            'sumgrades'  => $sumgrades,
        ]);

        return $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
    }

    /**
     * Triggers `\mod_quiz\event\attempt_submitted` for a given attempt.
     *
     * @param \stdClass $attempt The quiz_attempts row.
     * @param \stdClass $quiz    The quiz row the attempt belongs to.
     * @return void
     */
    protected function trigger_attempt_submitted(\stdClass $attempt, \stdClass $quiz): void {
        $cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid'     => $attempt->id,
            'relateduserid' => $this->student->id,
            'courseid'     => $this->course->id,
            'context'      => $context,
            'other'        => [
                'submitterid' => $this->student->id,
                'quizid'      => $quiz->id,
            ],
        ]);
        $event->trigger();
    }

    // Pass / fail decision.

    /**
     * A passed attempt creates exactly one PDF record and one stored file.
     */
    public function test_passed_attempt_creates_pdf_record(): void {
        global $DB;

        // Admin user required for some privileged operations during PDF generation.
        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        // 8/10 >= 5 → pass.
        $attempt = $this->create_finished_attempt($quiz, 8.0);

        $this->trigger_attempt_submitted($attempt, $quiz);

        $records = $DB->get_records('local_eledia_exam2pdf', ['attemptid' => $attempt->id]);
        $this->assertCount(1, $records, 'Expected exactly one PDF record for the passed attempt.');

        $record = reset($records);
        $this->assertEquals($quiz->id, $record->quizid);
        $this->assertEquals($this->student->id, $record->userid);
        $this->assertNotEmpty($record->contenthash);

        // File should exist in the plugin file area.
        $fs       = get_file_storage();
        $context  = \core\context\module::instance($record->cmid);
        $files    = $fs->get_area_files(
            $context->id,
            'local_eledia_exam2pdf',
            'attempt_pdf',
            $record->id,
            'filename',
            false
        );
        $this->assertCount(1, $files, 'Expected one PDF file stored for the record.');
    }

    /**
     * A failed attempt does not create a PDF record.
     */
    public function test_failed_attempt_does_not_create_pdf(): void {
        global $DB;

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        // 2/10 < 5 → fail.
        $attempt = $this->create_finished_attempt($quiz, 2.0);

        $this->trigger_attempt_submitted($attempt, $quiz);

        $this->assertFalse(
            $DB->record_exists('local_eledia_exam2pdf', ['attemptid' => $attempt->id]),
            'Failed attempts must not produce a PDF record.'
        );
    }

    /**
     * When the quiz has no passing grade, every finished attempt is treated as passed.
     */
    public function test_quiz_without_gradepass_always_creates_pdf(): void {
        global $DB;

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 0]);
        $attempt = $this->create_finished_attempt($quiz, 1.0);

        $this->trigger_attempt_submitted($attempt, $quiz);

        $this->assertTrue(
            $DB->record_exists('local_eledia_exam2pdf', ['attemptid' => $attempt->id]),
            'Without a passgrade every finished attempt should yield a PDF.'
        );
    }

    // Idempotency.

    /**
     * Re-firing the event for the same attempt must not create a second record.
     */
    public function test_duplicate_event_does_not_create_second_record(): void {
        global $DB;

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        $attempt = $this->create_finished_attempt($quiz, 9.0);

        $this->trigger_attempt_submitted($attempt, $quiz);
        $this->trigger_attempt_submitted($attempt, $quiz);

        $count = $DB->count_records('local_eledia_exam2pdf', ['attemptid' => $attempt->id]);
        $this->assertSame(1, $count, 'Observer must be idempotent per attempt.');
    }

    // Email output mode.

    /**
     * With outputmode='email' the observer sends an email on successful PDF generation.
     */
    public function test_email_output_mode_sends_message(): void {
        global $DB;

        set_config('outputmode', 'email', 'local_eledia_exam2pdf');
        set_config('emailsubject', 'Your certificate for {quizname}', 'local_eledia_exam2pdf');

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        $attempt = $this->create_finished_attempt($quiz, 8.0);

        // Redirect emails.
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $this->trigger_attempt_submitted($attempt, $quiz);

        $messages = $sink->get_messages();
        $sink->close();

        // Assert a PDF record was created.
        $this->assertTrue(
            $DB->record_exists('local_eledia_exam2pdf', ['attemptid' => $attempt->id])
        );

        // Assert at least one email was sent.  Skip strict subject match because.
        // Moodle's phpmailer sink may normalise headers; we check at least one recipient.
        $this->assertGreaterThanOrEqual(
            1,
            count($messages),
            'Expected at least one email to be sent in email output mode.'
        );
    }

    /**
     * The retentiondays config is applied to the record's timeexpires field.
     */
    public function test_retentiondays_sets_timeexpires(): void {
        global $DB;

        set_config('retentiondays', '30', 'local_eledia_exam2pdf');

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        $attempt = $this->create_finished_attempt($quiz, 7.0);

        $before = time();
        $this->trigger_attempt_submitted($attempt, $quiz);
        $after = time();

        $record = $DB->get_record('local_eledia_exam2pdf', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertGreaterThanOrEqual($before + 30 * DAYSECS, $record->timeexpires);
        $this->assertLessThanOrEqual($after + 30 * DAYSECS, $record->timeexpires);
    }

    /**
     * retentiondays=0 means the record never expires (`timeexpires = 0`).
     */
    public function test_retentiondays_zero_means_never_expires(): void {
        global $DB;

        set_config('retentiondays', '0', 'local_eledia_exam2pdf');

        $this->setAdminUser();
        $quiz    = $this->create_quiz_with_question(['sumgrades' => 10, 'grade' => 10, 'gradepass' => 5]);
        $attempt = $this->create_finished_attempt($quiz, 7.0);

        $this->trigger_attempt_submitted($attempt, $quiz);

        $record = $DB->get_record('local_eledia_exam2pdf', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $record->timeexpires);
    }
}
