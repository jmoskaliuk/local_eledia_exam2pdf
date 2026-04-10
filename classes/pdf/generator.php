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
 * PDF generator for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\pdf;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the PDF certificate for a passed quiz attempt using TCPDF.
 */
class generator {

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Generates the complete PDF for one quiz attempt and returns it as a string.
     *
     * @param \quiz_attempt $attemptobj  Fully initialised quiz_attempt object.
     * @param \stdClass     $quiz        The quiz DB record.
     * @param array         $config      Effective config (global + per-quiz overrides).
     * @return string  Raw PDF bytes.
     */
    public static function generate(\quiz_attempt $attemptobj, \stdClass $quiz, array $config): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/tcpdf/tcpdf.php');

        $attempt = $attemptobj->get_attempt();
        $learner = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

        // ----------------------------------------------------------------
        // Determine pass status.
        // ----------------------------------------------------------------
        $passed = true;
        if (!empty($quiz->gradepass) && $quiz->gradepass > 0 && $quiz->sumgrades > 0) {
            $grade  = $attempt->sumgrades / $quiz->sumgrades * $quiz->grade;
            $passed = ($grade >= (float) $quiz->gradepass);
        }

        // ----------------------------------------------------------------
        // Compute optional header values.
        // ----------------------------------------------------------------
        $grade      = ($quiz->sumgrades > 0) ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;
        $percentage = ($quiz->grade > 0) ? round($grade / $quiz->grade * 100, 1) : 0;
        $duration   = '';
        if ($attempt->timestart && $attempt->timefinish) {
            $secs    = $attempt->timefinish - $attempt->timestart;
            $duration = gmdate('H:i:s', $secs);
        }

        // ----------------------------------------------------------------
        // Set up TCPDF.
        // ----------------------------------------------------------------
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Moodle / eLeDia exam2pdf');
        $pdf->SetAuthor('eLeDia GmbH');
        $pdf->SetTitle(get_string('pdf_title', 'local_eledia_exam2pdf'));
        $pdf->SetSubject($quiz->name);
        $pdf->SetMargins(20, 28, 20);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->setHeaderFont(['helvetica', 'B', 12]);
        $pdf->setFooterFont(['helvetica', '', 9]);
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();

        // ----------------------------------------------------------------
        // Build HTML content.
        // ----------------------------------------------------------------
        $html = self::render_header($learner, $quiz, $passed, $attempt, $grade, $percentage, $duration, $config);
        $html .= self::render_questions($attemptobj, $config);

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S'); // Return as string.
    }

    // -----------------------------------------------------------------------
    // Private render helpers
    // -----------------------------------------------------------------------

    /**
     * Renders the PDF header block (mandatory + optional fields).
     *
     * @param \stdClass $learner
     * @param \stdClass $quiz
     * @param bool      $passed
     * @param \stdClass $attempt
     * @param float     $grade
     * @param float     $percentage
     * @param string    $duration
     * @param array     $config
     * @return string HTML
     */
    private static function render_header(
        \stdClass $learner,
        \stdClass $quiz,
        bool $passed,
        \stdClass $attempt,
        float $grade,
        float $percentage,
        string $duration,
        array $config
    ): string {
        $passedLabel = $passed
            ? get_string('pdf_passed_yes', 'local_eledia_exam2pdf')
            : get_string('pdf_passed_no', 'local_eledia_exam2pdf');
        $passedColor = $passed ? '#1a7a2e' : '#c0392b';

        $html  = '<h1 style="color:#1a3a5c; font-size:18pt; text-align:center;">';
        $html .= get_string('pdf_title', 'local_eledia_exam2pdf');
        $html .= '</h1>';
        $html .= '<hr style="color:#1a3a5c;"/>';

        // Mandatory fields table.
        $html .= '<table cellpadding="4" style="width:100%; border:1px solid #ccc;">';

        $html .= self::header_row(
            get_string('pdf_name', 'local_eledia_exam2pdf'),
            fullname($learner)
        );
        $html .= self::header_row(
            get_string('pdf_quiz', 'local_eledia_exam2pdf'),
            s($quiz->name)
        );
        $html .= '<tr>'
            . '<td style="font-weight:bold; width:40%;">' . get_string('pdf_passed', 'local_eledia_exam2pdf') . '</td>'
            . '<td style="color:' . $passedColor . '; font-weight:bold;">' . $passedLabel . '</td>'
            . '</tr>';

        // Optional fields.
        if (!empty($config['show_score'])) {
            $html .= self::header_row(
                get_string('pdf_score', 'local_eledia_exam2pdf'),
                round($grade, 2) . ' / ' . round($quiz->grade, 2)
            );
        }
        if (!empty($config['show_passgrade']) && !empty($quiz->gradepass)) {
            $html .= self::header_row(
                get_string('pdf_passgrade', 'local_eledia_exam2pdf'),
                round($quiz->gradepass, 2)
            );
        }
        if (!empty($config['show_percentage'])) {
            $html .= self::header_row(
                get_string('pdf_percentage', 'local_eledia_exam2pdf'),
                $percentage . '%'
            );
        }
        if (!empty($config['show_timestamp']) && $attempt->timefinish) {
            $html .= self::header_row(
                get_string('pdf_timestamp', 'local_eledia_exam2pdf'),
                userdate($attempt->timefinish)
            );
        }
        if (!empty($config['show_duration']) && $duration) {
            $html .= self::header_row(
                get_string('pdf_duration', 'local_eledia_exam2pdf'),
                $duration
            );
        }
        if (!empty($config['show_attemptnumber'])) {
            $html .= self::header_row(
                get_string('pdf_attemptnumber', 'local_eledia_exam2pdf'),
                $attempt->attempt
            );
        }

        $html .= '</table>';
        $html .= '<br/>';

        return $html;
    }

    /**
     * Renders the questions and learner answers section.
     *
     * @param \quiz_attempt $attemptobj
     * @param array         $config
     * @return string HTML
     */
    private static function render_questions(\quiz_attempt $attemptobj, array $config): string {
        $quba  = $attemptobj->get_question_usage();
        $slots = $attemptobj->get_slots();

        if (empty($slots)) {
            return '';
        }

        $html  = '<h2 style="color:#1a3a5c; font-size:13pt;">';
        $html .= get_string('pdf_questions_heading', 'local_eledia_exam2pdf');
        $html .= '</h2>';

        $num = 1;
        foreach ($slots as $slot) {
            $qa       = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $qtype    = $question->get_type_name();

            $html .= '<table cellpadding="5" style="width:100%; border:1px solid #ddd; margin-bottom:6px;">';

            // Question text.
            $qtext = strip_tags($question->questiontext);
            $html .= '<tr style="background:#f0f4f8;">'
                . '<td colspan="2"><strong>' . get_string('pdf_question', 'local_eledia_exam2pdf')
                . ' ' . $num . ':</strong> ' . s($qtext) . '</td>'
                . '</tr>';

            // Student answer.
            $response    = $qa->get_response_summary();
            $stateobject = $qa->get_state();
            $stateLabel  = '';
            $stateColor  = '#555';

            if ($stateobject->is_correct()) {
                $stateLabel = get_string('pdf_result_correct', 'local_eledia_exam2pdf');
                $stateColor = '#1a7a2e';
            } else if ($stateobject->is_partially_correct()) {
                $stateLabel = get_string('pdf_result_partial', 'local_eledia_exam2pdf');
                $stateColor = '#e67e22';
            } else if ($stateobject->is_incorrect()) {
                $stateLabel = get_string('pdf_result_incorrect', 'local_eledia_exam2pdf');
                $stateColor = '#c0392b';
            }

            $displayAnswer = !empty($response)
                ? s($response)
                : get_string('pdf_noanswer', 'local_eledia_exam2pdf');

            $html .= '<tr>'
                . '<td style="width:40%; font-weight:bold;">'
                . get_string('pdf_youranswer', 'local_eledia_exam2pdf') . '</td>'
                . '<td>' . $displayAnswer;

            if ($stateLabel) {
                $html .= ' <span style="color:' . $stateColor . '; font-weight:bold;">(' . $stateLabel . ')</span>';
            }

            $html .= '</td></tr>';

            // Correct answer (SC/MC + short answer — not essay).
            if (!empty($config['showcorrectanswers']) && $qtype !== 'essay') {
                $correct = self::get_correct_answer_text($question, $qtype);
                $html .= '<tr style="background:#fafafa;">'
                    . '<td style="font-weight:bold;">' . get_string('pdf_correctanswer', 'local_eledia_exam2pdf') . '</td>'
                    . '<td>' . ($correct ?: get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf')) . '</td>'
                    . '</tr>';
            }

            $html .= '</table>';
            $num++;
        }

        return $html;
    }

    /**
     * Extracts a human-readable correct answer string for common question types.
     *
     * @param \question_definition $question
     * @param string               $qtype
     * @return string
     */
    private static function get_correct_answer_text(\question_definition $question, string $qtype): string {
        switch ($qtype) {
            case 'multichoice':
                $correct = [];
                foreach ($question->answers as $answer) {
                    if ($answer->fraction > 0) {
                        $correct[] = strip_tags($answer->answer);
                    }
                }
                return implode(', ', $correct);

            case 'truefalse':
                // The correct answer is whichever option has fraction == 1.
                foreach ($question->answers as $answer) {
                    if ((float) $answer->fraction === 1.0) {
                        return strip_tags($answer->answer);
                    }
                }
                return '';

            case 'shortanswer':
                $best = null;
                foreach ($question->answers as $answer) {
                    if ($best === null || $answer->fraction > $best->fraction) {
                        $best = $answer;
                    }
                }
                return $best ? strip_tags($best->answer) : '';

            case 'numerical':
                $best = null;
                foreach ($question->answers as $answer) {
                    if ($best === null || $answer->fraction > $best->fraction) {
                        $best = $answer;
                    }
                }
                return $best ? strip_tags($best->answer) : '';

            default:
                // For other types use get_correct_response() if available.
                if (method_exists($question, 'get_correct_response')) {
                    $cr = $question->get_correct_response();
                    if ($cr) {
                        return implode(', ', array_filter(array_map('strip_tags', (array) $cr)));
                    }
                }
                return '';
        }
    }

    /**
     * Helper: renders one two-column header table row.
     *
     * @param string $label
     * @param mixed  $value
     * @return string HTML <tr> element.
     */
    private static function header_row(string $label, $value): string {
        return '<tr>'
            . '<td style="font-weight:bold; width:40%;">' . s($label) . '</td>'
            . '<td>' . s((string) $value) . '</td>'
            . '</tr>';
    }
}
