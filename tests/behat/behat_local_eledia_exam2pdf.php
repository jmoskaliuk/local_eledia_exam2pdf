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
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

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
                'state'  => \mod_quiz\quiz_attempt::FINISHED,
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
                'quizid' => $quiz->id,
            ],
        ]);

        // Call the observer directly (triggering via $event->trigger() would
        // go through the event manager and all registered observers, which is
        // fine too, but a direct call is more predictable in tests).
        \local_eledia_exam2pdf\observer::on_attempt_submitted($event);
    }
}
