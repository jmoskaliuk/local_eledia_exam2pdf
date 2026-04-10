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
 * Unit tests for the cleanup_expired_pdfs scheduled task.
 *
 * @package    local_eledia_exam2pdf
 * @category   test
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_exam2pdf\task\cleanup_expired_pdfs
 */

namespace local_eledia_exam2pdf\task;

/**
 * Tests the nightly cleanup task that removes expired PDF certificates.
 *
 * @covers \local_eledia_exam2pdf\task\cleanup_expired_pdfs
 */
final class cleanup_expired_pdfs_test extends \advanced_testcase {
    /** @var \stdClass */
    protected \stdClass $course;

    /** @var \stdClass */
    protected \stdClass $quiz;

    /** @var \context_module */
    protected \context $context;

    /**
     * Base fixtures: one course, one quiz, its module context.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->quiz    = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $this->context = \core\context\module::instance($this->quiz->cmid);
    }

    /**
     * Helper: inserts a PDF record with an optional stored file and returns the record ID.
     *
     * @param int  $timeexpires  Unix timestamp; 0 = never expires.
     * @param bool $withfile     Whether to also store a matching dummy file.
     * @return int  Inserted record ID.
     */
    protected function insert_record(int $timeexpires, bool $withfile = true): int {
        global $DB;

        $recordid = $DB->insert_record('local_eledia_exam2pdf', (object) [
            'quizid'      => $this->quiz->id,
            'cmid'        => $this->quiz->cmid,
            'attemptid'   => rand(100, 9999),
            'userid'      => 2,
            'timecreated' => time() - DAYSECS,
            'timeexpires' => $timeexpires,
            'contenthash' => str_repeat('a', 40),
        ]);

        if ($withfile) {
            $fs = get_file_storage();
            $fs->create_file_from_string([
                'contextid' => $this->context->id,
                'component' => 'local_eledia_exam2pdf',
                'filearea'  => 'attempt_pdf',
                'itemid'    => $recordid,
                'filepath'  => '/',
                'filename'  => 'certificate-' . $recordid . '.pdf',
            ], '%PDF-1.4 dummy content for record ' . $recordid);
        }

        return $recordid;
    }

    /**
     * With no records at all, the task runs without error and deletes nothing.
     */
    public function test_empty_database_is_noop(): void {
        global $DB;

        $task = new cleanup_expired_pdfs();
        $this->expectOutputRegex('/no expired PDFs/');
        $task->execute();

        $this->assertSame(0, $DB->count_records('local_eledia_exam2pdf'));
    }

    /**
     * Records with `timeexpires = 0` never expire and must not be deleted.
     */
    public function test_timeexpires_zero_is_never_deleted(): void {
        global $DB;

        $id = $this->insert_record(0);

        $task = new cleanup_expired_pdfs();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertTrue($DB->record_exists('local_eledia_exam2pdf', ['id' => $id]));
    }

    /**
     * Records with `timeexpires` in the future must not be deleted.
     */
    public function test_future_expiry_is_kept(): void {
        global $DB;

        $id = $this->insert_record(time() + DAYSECS);

        $task = new cleanup_expired_pdfs();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertTrue($DB->record_exists('local_eledia_exam2pdf', ['id' => $id]));

        // File must still be present.
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $id, 'filename', false);
        $this->assertCount(1, $files);
    }

    /**
     * Records whose `timeexpires` is in the past must be deleted — both DB row and file.
     */
    public function test_past_expiry_is_deleted(): void {
        global $DB;

        $id = $this->insert_record(time() - DAYSECS);

        $task = new cleanup_expired_pdfs();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertFalse($DB->record_exists('local_eledia_exam2pdf', ['id' => $id]));

        // File must also be gone.
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $id, 'filename', false);
        $this->assertEmpty($files);

        $this->assertStringContainsString('deleted 1 expired PDFs', $output);
    }

    /**
     * Mixed scenario: only the expired record is deleted.
     */
    public function test_mixed_expiries_only_expired_deleted(): void {
        global $DB;

        $expiredid = $this->insert_record(time() - DAYSECS);
        $futureid  = $this->insert_record(time() + DAYSECS);
        $neverid   = $this->insert_record(0);

        $task = new cleanup_expired_pdfs();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertFalse($DB->record_exists('local_eledia_exam2pdf', ['id' => $expiredid]));
        $this->assertTrue($DB->record_exists('local_eledia_exam2pdf', ['id' => $futureid]));
        $this->assertTrue($DB->record_exists('local_eledia_exam2pdf', ['id' => $neverid]));
    }

    /**
     * A record that expires exactly at `now` (boundary condition) is deleted.
     */
    public function test_expiry_at_exactly_now_is_deleted(): void {
        global $DB;

        $id = $this->insert_record(time());

        $task = new cleanup_expired_pdfs();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertFalse($DB->record_exists('local_eledia_exam2pdf', ['id' => $id]));
    }

    /**
     * The task has a non-empty name returned from language strings.
     */
    public function test_get_name_returns_string(): void {
        $task = new cleanup_expired_pdfs();
        $this->assertNotEmpty($task->get_name());
    }
}
