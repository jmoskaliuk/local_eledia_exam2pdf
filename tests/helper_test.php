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
 * Unit tests for local_eledia_exam2pdf helper.
 *
 * @package    local_eledia_exam2pdf
 * @category   test
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_exam2pdf\helper
 */

namespace local_eledia_exam2pdf;

/**
 * Tests for the {@see helper} utility class.
 *
 * @covers \local_eledia_exam2pdf\helper
 */
final class helper_test extends \advanced_testcase {
    /**
     * Reset DB after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // Tests for get_effective_config.

    /**
     * Returns the hard-coded defaults when no global config and no override are set.
     */
    public function test_get_effective_config_returns_hardcoded_defaults(): void {
        $config = helper::get_effective_config(42);

        $this->assertSame('auto', $config['pdfgeneration']);
        $this->assertSame('passed', $config['pdfscope']);
        $this->assertTrue($config['studentdownload']);
        $this->assertSame('zip', $config['bulkformat']);
        $this->assertSame('download', $config['outputmode']);
        $this->assertSame('', $config['emailrecipients']);
        $this->assertSame(365, $config['retentiondays']);
        $this->assertTrue($config['showcorrectanswers']);
        $this->assertTrue($config['show_score']);
        $this->assertTrue($config['show_passgrade']);
        $this->assertTrue($config['show_percentage']);
        $this->assertTrue($config['show_timestamp']);
        $this->assertTrue($config['show_duration']);
        $this->assertTrue($config['show_attemptnumber']);
    }

    /**
     * Global plugin settings are reflected as defaults.
     */
    public function test_get_effective_config_respects_global_settings(): void {
        set_config('outputmode', 'email', 'local_eledia_exam2pdf');
        set_config('emailrecipients', 'admin@example.com', 'local_eledia_exam2pdf');
        set_config('retentiondays', '90', 'local_eledia_exam2pdf');
        set_config('showcorrectanswers', '1', 'local_eledia_exam2pdf');
        set_config('show_score', '1', 'local_eledia_exam2pdf');

        $config = helper::get_effective_config(42);

        $this->assertSame('email', $config['outputmode']);
        $this->assertSame('admin@example.com', $config['emailrecipients']);
        $this->assertSame(90, $config['retentiondays']);
        $this->assertTrue($config['showcorrectanswers']);
        $this->assertTrue($config['show_score']);
    }

    /**
     * `retentiondays = '0'` must stay 0 (never-expire sentinel), not fall back to 365.
     * Guards against the short-ternary `?:` bug that eats zero.
     */
    public function test_get_effective_config_preserves_retentiondays_zero(): void {
        set_config('retentiondays', '0', 'local_eledia_exam2pdf');

        $config = helper::get_effective_config(42);

        $this->assertSame(0, $config['retentiondays']);
    }

    /**
     * Per-quiz overrides take precedence over global defaults.
     */
    public function test_per_quiz_overrides_take_precedence(): void {
        global $DB;

        // Set global defaults.
        set_config('outputmode', 'download', 'local_eledia_exam2pdf');
        set_config('retentiondays', '365', 'local_eledia_exam2pdf');
        set_config('show_score', '0', 'local_eledia_exam2pdf');

        // Insert per-quiz overrides.
        $quizid = 123;
        $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
            'quizid' => $quizid, 'name' => 'outputmode', 'value' => 'both',
        ]);
        $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
            'quizid' => $quizid, 'name' => 'retentiondays', 'value' => '30',
        ]);
        $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
            'quizid' => $quizid, 'name' => 'show_score', 'value' => '1',
        ]);

        $config = helper::get_effective_config($quizid);

        $this->assertSame('both', $config['outputmode']);
        $this->assertSame(30, $config['retentiondays']);
        $this->assertTrue($config['show_score']);
    }

    /**
     * Overrides for quiz A must not leak into quiz B's effective config.
     */
    public function test_quiz_config_isolation(): void {
        global $DB;

        $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
            'quizid' => 111, 'name' => 'outputmode', 'value' => 'email',
        ]);

        $configa = helper::get_effective_config(111);
        $configb = helper::get_effective_config(222);

        $this->assertSame('email', $configa['outputmode']);
        $this->assertSame('download', $configb['outputmode']);
    }

    /**
     * An empty override value falls back to the global default.
     */
    public function test_empty_override_falls_back_to_global(): void {
        global $DB;

        set_config('outputmode', 'email', 'local_eledia_exam2pdf');

        // Insert override with empty value — should NOT override.
        $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
            'quizid' => 42, 'name' => 'outputmode', 'value' => '',
        ]);

        $config = helper::get_effective_config(42);
        $this->assertSame('email', $config['outputmode']);
    }

    // Tests for save_quiz_config.

    /**
     * Inserts new override rows when none exist.
     */
    public function test_save_quiz_config_inserts_new_values(): void {
        global $DB;

        helper::save_quiz_config(42, [
            'outputmode'    => 'email',
            'retentiondays' => '30',
        ]);

        $records = $DB->get_records('local_eledia_exam2pdf_cfg', ['quizid' => 42], 'name');
        $this->assertCount(2, $records);

        $byname = [];
        foreach ($records as $row) {
            $byname[$row->name] = $row->value;
        }

        $this->assertSame('email', $byname['outputmode']);
        $this->assertSame('30', $byname['retentiondays']);
    }

    /**
     * Existing override rows are updated in place, not duplicated.
     */
    public function test_save_quiz_config_updates_existing(): void {
        global $DB;

        helper::save_quiz_config(42, ['outputmode' => 'email']);
        helper::save_quiz_config(42, ['outputmode' => 'download']);

        $records = $DB->get_records('local_eledia_exam2pdf_cfg', ['quizid' => 42]);
        $this->assertCount(1, $records);

        $row = reset($records);
        $this->assertSame('download', $row->value);
    }

    /**
     * Passing an empty string deletes the existing override row.
     */
    public function test_save_quiz_config_deletes_on_empty_value(): void {
        global $DB;

        helper::save_quiz_config(42, ['outputmode' => 'email']);
        $this->assertTrue(
            $DB->record_exists(
                'local_eledia_exam2pdf_cfg',
                ['quizid' => 42, 'name' => 'outputmode']
            )
        );

        helper::save_quiz_config(42, ['outputmode' => '']);
        $this->assertFalse(
            $DB->record_exists(
                'local_eledia_exam2pdf_cfg',
                ['quizid' => 42, 'name' => 'outputmode']
            )
        );
    }

    /**
     * Passing null also deletes the existing override row.
     */
    public function test_save_quiz_config_deletes_on_null_value(): void {
        global $DB;

        helper::save_quiz_config(42, ['outputmode' => 'email']);
        helper::save_quiz_config(42, ['outputmode' => null]);

        $this->assertFalse(
            $DB->record_exists(
                'local_eledia_exam2pdf_cfg',
                ['quizid' => 42, 'name' => 'outputmode']
            )
        );
    }

    /**
     * Deleting a non-existent override is a no-op (does not throw).
     */
    public function test_save_quiz_config_delete_nonexistent_is_noop(): void {
        helper::save_quiz_config(42, ['outputmode' => null]);

        global $DB;
        $this->assertSame(0, $DB->count_records('local_eledia_exam2pdf_cfg', ['quizid' => 42]));
    }

    // Tests for is_in_pdf_scope.

    /**
     * An unfinished attempt is never in scope.
     */
    public function test_is_in_pdf_scope_unfinished_attempt_returns_false(): void {
        $attempt = (object) ['state' => 'inprogress', 'sumgrades' => 10.0];
        $quiz    = (object) ['id' => 1, 'course' => 1, 'sumgrades' => 10, 'grade' => 10];

        $this->assertFalse(helper::is_in_pdf_scope($attempt, $quiz, ['pdfscope' => 'all']));
        $this->assertFalse(helper::is_in_pdf_scope($attempt, $quiz, ['pdfscope' => 'passed']));
    }

    /**
     * With scope 'all', every finished attempt is in scope.
     */
    public function test_is_in_pdf_scope_all_accepts_every_finished(): void {
        $attempt = (object) ['state' => 'finished', 'sumgrades' => 0.0];
        $quiz    = (object) ['id' => 1, 'course' => 1, 'sumgrades' => 10, 'grade' => 10];

        $this->assertTrue(helper::is_in_pdf_scope($attempt, $quiz, ['pdfscope' => 'all']));
    }

    /**
     * With scope 'passed', a passed attempt is in scope.
     */
    public function test_is_in_pdf_scope_passed_accepts_passing_attempt(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'sumgrades' => 10, 'grade' => 10,
        ]);

        // Set gradepass in gradebook.
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id, 'courseid' => $course->id,
        ]);
        $gradeitem->gradepass = 5.0;
        $gradeitem->update();

        $quizrecord = (object) [
            'id' => $quiz->id, 'course' => $course->id,
            'sumgrades' => 10, 'grade' => 10,
        ];

        // 8/10 >= 5 — passed.
        $attempt = (object) ['state' => 'finished', 'sumgrades' => 8.0];
        $this->assertTrue(helper::is_in_pdf_scope($attempt, $quizrecord, ['pdfscope' => 'passed']));
    }

    /**
     * With scope 'passed', a failed attempt is NOT in scope.
     */
    public function test_is_in_pdf_scope_passed_rejects_failing_attempt(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'sumgrades' => 10, 'grade' => 10,
        ]);

        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id, 'courseid' => $course->id,
        ]);
        $gradeitem->gradepass = 5.0;
        $gradeitem->update();

        $quizrecord = (object) [
            'id' => $quiz->id, 'course' => $course->id,
            'sumgrades' => 10, 'grade' => 10,
        ];

        // 2/10 < 5 — failed.
        $attempt = (object) ['state' => 'finished', 'sumgrades' => 2.0];
        $this->assertFalse(helper::is_in_pdf_scope($attempt, $quizrecord, ['pdfscope' => 'passed']));
    }

    /**
     * Defaults to scope 'passed' when pdfscope key is missing from config.
     */
    public function test_is_in_pdf_scope_defaults_to_passed(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'sumgrades' => 10, 'grade' => 10,
        ]);

        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id, 'courseid' => $course->id,
        ]);
        $gradeitem->gradepass = 5.0;
        $gradeitem->update();

        $quizrecord = (object) [
            'id' => $quiz->id, 'course' => $course->id,
            'sumgrades' => 10, 'grade' => 10,
        ];

        // 2/10 < 5 — failed, and no pdfscope key — should default to 'passed' — false.
        $attempt = (object) ['state' => 'finished', 'sumgrades' => 2.0];
        $this->assertFalse(helper::is_in_pdf_scope($attempt, $quizrecord, []));
    }

    // Tests for get_download_url.

    /**
     * Returns a moodle_url pointing at the plugin file area for a record.
     */
    public function test_get_download_url_builds_pluginfile_url(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $record = (object) [
            'id'   => 7,
            'cmid' => $quiz->cmid,
        ];

        $url = helper::get_download_url($record, 'test.pdf');

        $this->assertInstanceOf(\moodle_url::class, $url);
        $out = $url->out(false);
        $this->assertStringContainsString('/pluginfile.php/', $out);
        $this->assertStringContainsString('/local_eledia_exam2pdf/', $out);
        $this->assertStringContainsString('/attempt_pdf/', $out);
        $this->assertStringContainsString('/7/', $out);
        $this->assertStringContainsString('test.pdf', $out);
    }

    // Tests for get_stored_file.

    /**
     * Returns null when no file has been stored for the record yet.
     */
    public function test_get_stored_file_returns_null_when_missing(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $record = (object) [
            'id'   => 999,
            'cmid' => $quiz->cmid,
        ];

        $this->assertNull(helper::get_stored_file($record));
    }

    /**
     * Returns the stored_file instance when a file exists in the plugin file area.
     */
    public function test_get_stored_file_returns_stored_file_when_present(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz   = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $record = (object) [
            'id'   => 123,
            'cmid' => $quiz->cmid,
        ];

        $context = \core\context\module::instance($quiz->cmid);
        $fs      = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_eledia_exam2pdf',
            'filearea'  => 'attempt_pdf',
            'itemid'    => $record->id,
            'filepath'  => '/',
            'filename'  => 'certificate.pdf',
        ], '%PDF-1.4 stub content');

        $file = helper::get_stored_file($record);

        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertSame('certificate.pdf', $file->get_filename());
        $this->assertSame('local_eledia_exam2pdf', $file->get_component());
        $this->assertSame('attempt_pdf', $file->get_filearea());
        $this->assertSame((int) $record->id, (int) $file->get_itemid());
    }
}
