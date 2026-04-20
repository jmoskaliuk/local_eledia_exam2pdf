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
 * Generator regression tests for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_exam2pdf\pdf\generator
 */

namespace local_eledia_exam2pdf;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Regression tests for the PDF generator internals.
 *
 * @covers \local_eledia_exam2pdf\pdf\generator
 */
final class generator_test extends \advanced_testcase {
    /** @var \stdClass */
    protected \stdClass $course;

    /** @var \stdClass */
    protected \stdClass $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * The rich-text helper must preserve safe inline markup instead of flattening it.
     */
    public function test_render_rich_text_fragment_preserves_markup(): void {
        $method = new \ReflectionMethod(\local_eledia_exam2pdf\pdf\generator::class, 'render_rich_text_fragment');
        $method->setAccessible(true);

        $html = $method->invoke(null, '<strong>Bold</strong> H<sub>2</sub>O', FORMAT_HTML);

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<sub>2</sub>', $html);
    }

    /**
     * Formatted learner answers must keep their markup in the rendered answer cell.
     */
    public function test_decorate_answer_value_preserves_formatted_answer_markup(): void {
        $method = new \ReflectionMethod(\local_eledia_exam2pdf\pdf\generator::class, 'decorate_answer_value');
        $method->setAccessible(true);

        $html = $method->invoke(null, '<p><strong>Essay</strong> answer</p>', \question_state::$gradedright, FORMAT_HTML);

        $this->assertStringContainsString('<strong>Essay</strong>', $html);
        $this->assertStringContainsString('qans-correct', $html);
    }

    /**
     * Non-essay responses must keep the readable summary instead of raw qtdata values.
     */
    public function test_resolve_response_text_and_format_keeps_summary_for_non_essay(): void {
        $method = new \ReflectionMethod(\local_eledia_exam2pdf\pdf\generator::class, 'resolve_response_text_and_format');
        $method->setAccessible(true);

        [$answertext, $format] = $method->invoke(
            null,
            'truefalse',
            'True',
            ['answer' => '1', 'answerformat' => FORMAT_HTML]
        );

        $this->assertSame('True', $answertext);
        $this->assertSame(FORMAT_PLAIN, $format);
    }

    /**
     * Essay responses may keep markup, but learner HTML must still be cleaned.
     */
    public function test_render_learner_answer_fragment_cleans_unsafe_markup(): void {
        $method = new \ReflectionMethod(\local_eledia_exam2pdf\pdf\generator::class, 'render_learner_answer_fragment');
        $method->setAccessible(true);

        $html = $method->invoke(
            null,
            '<p><strong>Essay</strong><script>alert(1)</script></p>',
            FORMAT_HTML
        );

        $this->assertStringContainsString('<strong>Essay</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Questions flow naturally: no forced pagebreak between slots.
     *
     * The card's CSS (page-break-inside: avoid) keeps individual questions
     * together, but multiple questions are allowed per page.
     */
    public function test_render_single_question_slot_has_no_forced_pagebreak(): void {
        $this->setAdminUser();
        $quiz = $this->create_quiz_with_questions(2);
        $attempt = $this->create_finished_attempt($quiz, 0.0);
        $attemptobj = \mod_quiz\quiz_attempt::create((int) $attempt->id);
        $slots = array_values($attemptobj->get_slots());

        $this->assertCount(2, $slots);

        $method = new \ReflectionMethod(\local_eledia_exam2pdf\pdf\generator::class, 'render_single_question_slot');
        $method->setAccessible(true);
        $config = helper::get_effective_config((int) $quiz->id);

        $html = $method->invoke(null, $attemptobj, (int) $slots[1], 2, $config);

        $this->assertStringNotContainsString('<pagebreak />', $html);
    }

    /**
     * Creates a quiz with the requested number of true/false questions.
     *
     * @param int $count Number of questions to add.
     * @return \stdClass
     */
    private function create_quiz_with_questions(int $count): \stdClass {
        global $DB;

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
            'sumgrades' => $count,
            'grade' => $count,
            'gradepass' => 0,
            'attempts' => 0,
        ]);

        /** @var \core_question_generator $questiongen */
        $questiongen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongen->create_question_category();

        for ($i = 1; $i <= $count; $i++) {
            $question = $questiongen->create_question('truefalse', null, [
                'category' => $cat->id,
                'questiontext' => '<p><strong>Question ' . $i . '</strong></p>',
                'questiontextformat' => FORMAT_HTML,
            ]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        $DB->set_field('quiz', 'sumgrades', (float) $count, ['id' => $quiz->id]);

        return $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
    }

    /**
     * Creates a finished quiz attempt with the given raw score.
     *
     * @param \stdClass $quiz The quiz record.
     * @param float $sumgrades Raw sumgrades to store.
     * @return \stdClass
     */
    private function create_finished_attempt(\stdClass $quiz, float $sumgrades): \stdClass {
        global $DB;

        $timenow = time();
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, null, $timenow, false, $this->student->id);
        $attempt->studentisonline = false;
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $DB->update_record('quiz_attempts', (object) [
            'id' => $attempt->id,
            'state' => 'finished',
            'timefinish' => $timenow,
            'sumgrades' => $sumgrades,
        ]);

        return $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
    }
}
