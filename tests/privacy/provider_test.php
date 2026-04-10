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
 * Privacy provider tests for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @category   test
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_exam2pdf\privacy\provider
 */

namespace local_eledia_exam2pdf\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Verifies the GDPR provider correctly reports, exports and deletes PDF records.
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    /** @var \stdClass */
    protected \stdClass $course;

    /** @var \stdClass */
    protected \stdClass $quiza;

    /** @var \stdClass */
    protected \stdClass $quizb;

    /** @var \context_module */
    protected \context $contexta;

    /** @var \context_module */
    protected \context $contextb;

    /** @var \stdClass */
    protected \stdClass $user1;

    /** @var \stdClass */
    protected \stdClass $user2;

    /**
     * Two users, two quizzes, two contexts, some PDF records and files.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->quiza  = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Quiz A']);
        $this->quizb  = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Quiz B']);

        $this->contexta = \core\context\module::instance($this->quiza->cmid);
        $this->contextb = \core\context\module::instance($this->quizb->cmid);

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course->id, 'student');

        // user1 passed both quizzes; user2 only passed Quiz A.
        $this->insert_pdf_record($this->quiza, $this->contexta, $this->user1->id);
        $this->insert_pdf_record($this->quizb, $this->contextb, $this->user1->id);
        $this->insert_pdf_record($this->quiza, $this->contexta, $this->user2->id);
    }

    /**
     * Inserts a fake PDF record (and dummy file) for a given user in a quiz.
     *
     * @param \stdClass $quiz    The quiz module record (must have ->id and ->cmid).
     * @param \context  $context The module context that will own the stored file.
     * @param int       $userid  The owning user ID for the PDF record.
     * @return int The newly inserted local_eledia_exam2pdf record ID.
     */
    protected function insert_pdf_record(\stdClass $quiz, \context $context, int $userid): int {
        global $DB;

        $recordid = $DB->insert_record('local_eledia_exam2pdf', (object) [
            'quizid'      => $quiz->id,
            'cmid'        => $quiz->cmid,
            'attemptid'   => rand(100, 99999),
            'userid'      => $userid,
            'timecreated' => time() - DAYSECS,
            'timeexpires' => 0,
            'contenthash' => str_repeat('a', 40),
        ]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_eledia_exam2pdf',
            'filearea'  => 'attempt_pdf',
            'itemid'    => $recordid,
            'filepath'  => '/',
            'filename'  => 'cert-' . $recordid . '.pdf',
        ], '%PDF-1.4 dummy');

        return $recordid;
    }

    // -----------------------------------------------------------------------
    // Metadata
    // -----------------------------------------------------------------------

    /**
     * get_metadata() returns a populated collection that includes the
     * main plugin table and the core_files subsystem link.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_eledia_exam2pdf');
        $result     = provider::get_metadata($collection);

        $this->assertInstanceOf(collection::class, $result);
        $items = $result->get_collection();
        $this->assertNotEmpty($items);

        // Pull out the type-identifier of each item ("local_eledia_exam2pdf" or "core_files").
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
        }
        $this->assertContains('local_eledia_exam2pdf', $names);
        $this->assertContains('core_files', $names);
    }

    // -----------------------------------------------------------------------
    // get_contexts_for_userid()
    // -----------------------------------------------------------------------

    /**
     * user1 has records in both Quiz A and Quiz B → both contexts are returned.
     */
    public function test_get_contexts_for_userid_returns_all_user_contexts(): void {
        $contextlist = provider::get_contexts_for_userid($this->user1->id);

        $ids = $contextlist->get_contextids();
        $this->assertCount(2, $ids);
        $this->assertContains((int) $this->contexta->id, array_map('intval', $ids));
        $this->assertContains((int) $this->contextb->id, array_map('intval', $ids));
    }

    /**
     * user2 has records only in Quiz A → only context A is returned.
     */
    public function test_get_contexts_for_userid_isolates_users(): void {
        $contextlist = provider::get_contexts_for_userid($this->user2->id);

        $ids = array_map('intval', $contextlist->get_contextids());
        $this->assertSame([(int) $this->contexta->id], $ids);
    }

    /**
     * A user without any records returns an empty contextlist.
     */
    public function test_get_contexts_for_userid_empty_for_unknown_user(): void {
        $stranger    = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($stranger->id);
        $this->assertCount(0, $contextlist->get_contextids());
    }

    // -----------------------------------------------------------------------
    // get_users_in_context()
    // -----------------------------------------------------------------------

    /**
     * Both user1 and user2 have PDFs in Quiz A's context → both are returned.
     */
    public function test_get_users_in_context_quiz_a(): void {
        $userlist = new userlist($this->contexta, 'local_eledia_exam2pdf');
        provider::get_users_in_context($userlist);

        $ids = array_map('intval', $userlist->get_userids());
        sort($ids);

        $expected = [(int) $this->user1->id, (int) $this->user2->id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    /**
     * Only user1 has a PDF in Quiz B → only user1 is returned.
     */
    public function test_get_users_in_context_quiz_b(): void {
        $userlist = new userlist($this->contextb, 'local_eledia_exam2pdf');
        provider::get_users_in_context($userlist);

        $ids = array_map('intval', $userlist->get_userids());
        $this->assertSame([(int) $this->user1->id], $ids);
    }

    // -----------------------------------------------------------------------
    // export_user_data()
    // -----------------------------------------------------------------------

    /**
     * Exporting user1 emits data for both contexts and the associated files.
     */
    public function test_export_user_data_writes_records_and_files(): void {
        $approved = new approved_contextlist(
            $this->user1,
            'local_eledia_exam2pdf',
            [$this->contexta->id, $this->contextb->id]
        );

        provider::export_user_data($approved);

        $writera = writer::with_context($this->contexta);
        $writerb = writer::with_context($this->contextb);

        $this->assertTrue($writera->has_any_data());
        $this->assertTrue($writerb->has_any_data());
    }

    // -----------------------------------------------------------------------
    // delete_data_for_all_users_in_context()
    // -----------------------------------------------------------------------

    /**
     * Deleting a whole context wipes all PDF records and files for that quiz,
     * but leaves other contexts untouched.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->contexta);

        $this->assertSame(
            0,
            $DB->count_records('local_eledia_exam2pdf', ['quizid' => $this->quiza->id]),
            'Quiz A records must be wiped.'
        );
        $this->assertSame(
            1,
            $DB->count_records('local_eledia_exam2pdf', ['quizid' => $this->quizb->id]),
            'Quiz B records must stay.'
        );

        // Files in Quiz A's area are gone.
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->contexta->id, 'local_eledia_exam2pdf', 'attempt_pdf');
        $this->assertEmpty($files);
    }

    // -----------------------------------------------------------------------
    // delete_data_for_user()
    // -----------------------------------------------------------------------

    /**
     * Deleting a single user only removes that user's records, in all contexts.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $approved = new approved_contextlist(
            $this->user1,
            'local_eledia_exam2pdf',
            [$this->contexta->id, $this->contextb->id]
        );

        provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records('local_eledia_exam2pdf', ['userid' => $this->user1->id]));
        $this->assertSame(1, $DB->count_records('local_eledia_exam2pdf', ['userid' => $this->user2->id]));
    }

    // -----------------------------------------------------------------------
    // delete_data_for_users()
    // -----------------------------------------------------------------------

    /**
     * Deleting a set of users from a single context removes only their records
     * from that context.
     */
    public function test_delete_data_for_users_scoped_to_context(): void {
        global $DB;

        $userlist = new approved_userlist(
            $this->contexta,
            'local_eledia_exam2pdf',
            [$this->user1->id, $this->user2->id]
        );

        provider::delete_data_for_users($userlist);

        // Both Quiz A records for user1 & user2 are gone.
        $this->assertSame(
            0,
            $DB->count_records('local_eledia_exam2pdf',
                ['quizid' => $this->quiza->id, 'userid' => $this->user1->id])
        );
        $this->assertSame(
            0,
            $DB->count_records('local_eledia_exam2pdf',
                ['quizid' => $this->quiza->id, 'userid' => $this->user2->id])
        );

        // But user1's Quiz B record still exists (was not in the passed context).
        $this->assertSame(
            1,
            $DB->count_records('local_eledia_exam2pdf',
                ['quizid' => $this->quizb->id, 'userid' => $this->user1->id])
        );
    }
}
