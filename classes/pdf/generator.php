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
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\pdf;

use mod_quiz\quiz_attempt;

/**
 * Generates the PDF certificate for a passed quiz attempt using TCPDF.
 */
class generator {
    // Public API.

    /**
     * Generates the complete PDF for one quiz attempt and returns it as a string.
     *
     * @param quiz_attempt $attemptobj Fully initialised quiz_attempt object.
     * @param \stdClass    $quiz       The quiz DB record.
     * @param array        $config     Effective config (global + per-quiz overrides).
     * @return string Raw PDF bytes.
     */
    public static function generate(quiz_attempt $attemptobj, \stdClass $quiz, array $config): string {
        global $CFG;

        require_once($CFG->libdir . '/tcpdf/tcpdf.php');
        require_once($CFG->libdir . '/gradelib.php');

        $originallanguage = self::force_pdf_language($config);
        try {
            $pdf = self::create_pdf_document($quiz, $config);
            $pdf->AddPage();

            $html = self::render_attempt_document($attemptobj, $quiz, $config);
            $pdf->writeHTML($html, true, false, true, false, '');

            // Return as string.
            return $pdf->Output('', 'S');
        } finally {
            self::restore_forced_language($originallanguage);
        }
    }

    /**
     * Generates one merged PDF for multiple attempts of the same quiz.
     *
     * @param int[] $attemptids List of quiz_attempt IDs.
     * @param \stdClass $quiz The quiz DB record.
     * @param array $config Effective config (global + per-quiz overrides).
     * @return string Raw PDF bytes.
     */
    public static function generate_merged(array $attemptids, \stdClass $quiz, array $config): string {
        global $CFG;

        require_once($CFG->libdir . '/tcpdf/tcpdf.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attemptids = array_values(array_unique(array_map('intval', $attemptids)));
        if (empty($attemptids)) {
            return '';
        }

        $originallanguage = self::force_pdf_language($config);
        try {
            $pdf = self::create_pdf_document($quiz, $config);

            foreach ($attemptids as $attemptid) {
                $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
                $pdf->AddPage();
                $html = self::render_attempt_document($attemptobj, $quiz, $config);
                $pdf->writeHTML($html, true, false, true, false, '');
            }

            return $pdf->Output('', 'S');
        } finally {
            self::restore_forced_language($originallanguage);
        }
    }

    // Private render helpers.

    /**
     * Creates a configured TCPDF document instance.
     *
     * @param \stdClass $quiz The quiz record.
     * @param array $config Effective plugin config.
     * @return \TCPDF
     */
    private static function create_pdf_document(\stdClass $quiz, array $config): \TCPDF {
        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            /** @var string Footer text rendered on each page. */
            protected string $customfootertext = '';

            /**
             * Sets footer text.
             *
             * @param string $text Footer text.
             * @return void
             */
            public function set_custom_footer_text(string $text): void {
                $this->customfootertext = trim(strip_tags($text));
            }

            /**
             * Renders page footer.
             *
             * TCPDF requires this method to be named "Footer" (PascalCase) — it is
             * an internal TCPDF callback, not a Moodle API method. The Moodle
             * naming rule does not apply here; suppress the sniff accordingly.
             *
             * @return void
             * phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod
             */
            public function Footer(): void {
                if ($this->customfootertext === '') {
                    parent::Footer();
                    return;
                }

                $this->SetY(-12);
                $this->SetFont('helvetica', '', 8);
                $this->setTextColor(100, 100, 100);
                $this->Cell(0, 0, $this->customfootertext, 0, 0, 'C');
                $this->Cell(0, 0, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
            }
            // phpcs:enable moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        };
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
        $pdf->set_custom_footer_text((string) ($config['pdffootertext'] ?? ''));
        return $pdf;
    }

    /**
     * Resolves and applies the configured PDF language.
     *
     * @param array $config Effective plugin config.
     * @return string|null Original language to restore, or null when no switch was needed.
     */
    private static function force_pdf_language(array $config): ?string {
        $targetlanguage = self::resolve_pdf_language($config);
        $currentlanguage = current_language();
        if ($targetlanguage === '' || $targetlanguage === $currentlanguage) {
            return null;
        }

        force_current_language($targetlanguage);
        return $currentlanguage;
    }

    /**
     * Restores a previously forced language.
     *
     * @param string|null $originallanguage Language code returned by force_pdf_language().
     * @return void
     */
    private static function restore_forced_language(?string $originallanguage): void {
        if ($originallanguage !== null && $originallanguage !== '') {
            force_current_language($originallanguage);
        }
    }

    /**
     * Resolves effective PDF language from config.
     *
     * @param array $config Effective plugin config.
     * @return string Moodle language code.
     */
    private static function resolve_pdf_language(array $config): string {
        $installedlanguages = get_string_manager()->get_list_of_translations();
        $selectedlanguage = (string) ($config['pdflanguage'] ?? 'site');

        if ($selectedlanguage === '' || $selectedlanguage === 'site') {
            $sitelanguage = (string) (get_config('core', 'lang') ?: '');
            if ($sitelanguage !== '' && array_key_exists($sitelanguage, $installedlanguages)) {
                return $sitelanguage;
            }
            return current_language();
        }

        if (array_key_exists($selectedlanguage, $installedlanguages)) {
            return $selectedlanguage;
        }

        return current_language();
    }

    /**
     * Renders one full attempt document (logo + header + questions).
     *
     * @param quiz_attempt $attemptobj Fully initialised quiz_attempt object.
     * @param \stdClass $quiz The quiz DB record.
     * @param array $config Effective config values.
     * @return string
     */
    private static function render_attempt_document(quiz_attempt $attemptobj, \stdClass $quiz, array $config): string {
        global $DB;

        $attempt = $attemptobj->get_attempt();
        $learner = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

        // Determine pass status via the gradebook (gradepass is not in the quiz table).
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'courseid' => $quiz->course,
        ]);
        $gradepass = ($gradeitem && !empty($gradeitem->gradepass)) ? (float) $gradeitem->gradepass : 0.0;

        $passed = true;
        if ($gradepass > 0 && $quiz->sumgrades > 0) {
            $grade  = $attempt->sumgrades / $quiz->sumgrades * $quiz->grade;
            $passed = ($grade >= $gradepass);
        }

        // Compute optional header values.
        $grade      = ($quiz->sumgrades > 0) ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;
        $percentage = ($quiz->grade > 0) ? round($grade / $quiz->grade * 100, 1) : 0;
        $duration   = '';
        if ($attempt->timestart && $attempt->timefinish) {
            $secs     = $attempt->timefinish - $attempt->timestart;
            $duration = gmdate('H:i:s', $secs);
        }

        $logohtml = self::get_logo_html();
        $html  = self::render_header(
            $logohtml,
            $learner,
            $quiz,
            $passed,
            $attempt,
            $grade,
            $percentage,
            $duration,
            $config,
            $gradepass
        );
        $html .= self::render_navigation($attemptobj);
        $html .= self::render_questions($attemptobj, $config);
        return $html;
    }

    /**
     * Fetches the site logo from the Moodle file storage and returns an HTML
     * img element with a base64 data-URI src, suitable for embedding in TCPDF.
     *
     * The logo is read from the core_admin/logo file area (set via Site
     * Administration -> Appearance -> Logos). Falls back to an empty string
     * when no logo has been uploaded — PDF generation is never aborted.
     *
     * @return string HTML <img>, or empty string.
     */
    private static function get_logo_html(): string {
        try {
            $syscontext = \core\context\system::instance();
            $fs         = get_file_storage();
            // Try full-size logo first, then compact logo as fallback.
            foreach (['logo', 'logocompact'] as $area) {
                $files = $fs->get_area_files(
                    $syscontext->id,
                    'core_admin',
                    $area,
                    0,
                    'filename',
                    false
                );
                if (!empty($files)) {
                    $file = reset($files);
                    $mime = $file->get_mimetype();
                    $data = base64_encode($file->get_content());
                    $src  = 'data:' . $mime . ';base64,' . $data;
                    return '<img src="' . $src . '" style="height:24px; width:auto; margin-top:-4px;" />';
                }
            }
        } catch (\Throwable $e) {
            // Logo is optional — PDF generation continues without it.
            debugging('exam2pdf: logo fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return '';
    }

    /**
     * Renders the PDF header block (mandatory + optional fields).
     *
     * @param string    $logohtml   Optional logo image HTML.
     * @param \stdClass $learner    The learner's user row.
     * @param \stdClass $quiz       The quiz row.
     * @param bool      $passed     Whether the attempt was passed.
     * @param \stdClass $attempt    The quiz_attempts row.
     * @param float     $grade      The rescaled grade on the quiz's grade scale.
     * @param float     $percentage The grade as a percentage of the maximum.
     * @param string    $duration   Formatted duration string ("H:i:s" or empty).
     * @param array     $config     Effective config values.
     * @param float     $gradepass  The pass grade from the gradebook (0 = none configured).
     * @return string HTML markup.
     */
    private static function render_header(
        string $logohtml,
        \stdClass $learner,
        \stdClass $quiz,
        bool $passed,
        \stdClass $attempt,
        float $grade,
        float $percentage,
        string $duration,
        array $config,
        float $gradepass = 0.0
    ): string {
        $passedlabel = $passed
            ? get_string('yes')
            : get_string('no');
        $passedcolor = $passed ? '#1a7a2e' : '#c0392b';

        $html = '';
        if ($logohtml !== '') {
            $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 2px 0;">'
                . '<tr>'
                . '<td width="72%" style="padding:0;">&nbsp;</td>'
                . '<td width="28%" align="right" style="text-align:right; vertical-align:top; padding:0;">'
                . $logohtml
                . '</td>'
                . '</tr>'
                . '</table>';
        }

        // Use a table container for the title because TCPDF does not reliably
        // apply width/margin styles on heading tags like <h1>.
        $html .= '<table cellpadding="0" cellspacing="0"'
            . ' style="width:100%; margin:0 0 6px 0; border:1px solid #c6d8ec;">'
            . '<tr>'
            . '<td style="color:#1a3a5c; font-size:17pt; font-weight:bold; text-align:center;'
            . ' background-color:#e8f2fc; padding:8px 12px;">'
            . get_string('pdf_title', 'local_eledia_exam2pdf')
            . '</td>'
            . '</tr>'
            . '</table>';

        // Mandatory fields table.
        $html .= '<table cellpadding="5" cellspacing="0"'
            . ' style="width:100%; border:1px solid #cfd8e3; border-collapse:collapse;'
            . ' table-layout:fixed; margin:0;">'
            . '<colgroup>'
            . '<col style="width:36%;" />'
            . '<col style="width:64%;" />'
            . '</colgroup>';

        $html .= self::header_row(
            get_string('pdf_name', 'local_eledia_exam2pdf'),
            fullname($learner)
        );
        $html .= self::header_row(
            get_string('pdf_quiz', 'local_eledia_exam2pdf'),
            s($quiz->name)
        );
        $html .= '<tr>' .
            '<td style="font-weight:bold; background:#f5f7fa; border:1px solid #e1e6ed;">' .
            get_string('pdf_passed', 'local_eledia_exam2pdf') . '</td>' .
            '<td style="color:' . $passedcolor . '; font-weight:bold; border:1px solid #e1e6ed;">'
            . $passedlabel . '</td>' .
            '</tr>';

        // Optional fields.
        if (!empty($config['show_score'])) {
            $html .= self::header_row(
                get_string('pdf_score', 'local_eledia_exam2pdf'),
                round($grade, 2) . ' / ' . round($quiz->grade, 2)
            );
        }
        if (!empty($config['show_passgrade']) && $gradepass > 0) {
            $html .= self::header_row(
                get_string('pdf_passgrade', 'local_eledia_exam2pdf'),
                round($gradepass, 2)
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
        $html .= '<br />';

        return $html;
    }

    /**
     * Renders a quiz navigation summary grouped by page and section heading.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @return string HTML markup.
     */
    private static function render_navigation(quiz_attempt $attemptobj): string {
        $numpages = (int) $attemptobj->get_num_pages();
        if ($numpages <= 0) {
            return '';
        }

        $html  = '<h2 style="color:#1a3a5c; font-size:13pt;">';
        $html .= get_string('pdf_navigation_heading', 'local_eledia_exam2pdf');
        $html .= '</h2>';
        $html .= '<table cellpadding="4" cellspacing="0"'
            . ' style="width:100%; border:1px solid #d9e0e8; border-collapse:collapse; margin:0 0 8px 0;">';

        for ($page = 0; $page < $numpages; $page++) {
            $slots = $attemptobj->get_slots($page);
            if (empty($slots)) {
                continue;
            }

            $pagelabel = get_string('page') . ' ' . ($page + 1);
            $html .= '<tr>'
                . '<td style="width:18%; font-weight:bold; background:#f5f7fa; border:1px solid #e1e6ed;">'
                . s($pagelabel)
                . '</td>'
                . '<td style="width:82%; border:1px solid #e1e6ed;">'
                . self::render_navigation_page_slots($attemptobj, $slots)
                . '</td>'
                . '</tr>';
        }

        $html .= '</table>';
        $html .= '<br />';

        return $html;
    }

    /**
     * Renders all slot badges for one page, grouped by section heading.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param int[] $slots Slot numbers for one quiz page.
     * @return string HTML markup.
     */
    private static function render_navigation_page_slots(quiz_attempt $attemptobj, array $slots): string {
        $groups = self::group_navigation_slots_by_area($attemptobj, $slots);
        if (empty($groups)) {
            return '';
        }

        $defaultarealabel = get_string('section') . ' 1';
        $showarealabels = (count($groups) > 1) || (array_key_first($groups) !== $defaultarealabel);
        $html = '';

        foreach ($groups as $arealabel => $areaslots) {
            if ($showarealabels) {
                $html .= '<div style="font-weight:bold; color:#4f5d73; margin:0 0 2px 0;">' . s($arealabel) . '</div>';
            }
            $html .= self::render_navigation_slot_badges($attemptobj, $areaslots);
            if ($showarealabels) {
                $html .= '<div style="height:2px;"></div>';
            }
        }

        return $html;
    }

    /**
     * Groups slots by section heading while preserving slot order.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param int[] $slots Slot numbers for one quiz page.
     * @return array<string, int[]> Ordered area label => slots map.
     */
    private static function group_navigation_slots_by_area(quiz_attempt $attemptobj, array $slots): array {
        $groups = [];
        $currentarealabel = get_string('section') . ' 1';

        foreach ($slots as $slot) {
            $heading = trim(strip_tags((string) $attemptobj->get_heading_before_slot($slot)));
            if ($heading !== '') {
                $currentarealabel = $heading;
            }

            if (!array_key_exists($currentarealabel, $groups)) {
                $groups[$currentarealabel] = [];
            }
            $groups[$currentarealabel][] = (int) $slot;
        }

        return $groups;
    }

    /**
     * Renders navigation badges for a list of slot numbers.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param int[] $slots Slot numbers.
     * @return string HTML markup.
     */
    private static function render_navigation_slot_badges(quiz_attempt $attemptobj, array $slots): string {
        $html = '<table cellpadding="2" cellspacing="2" style="border-collapse:separate;">'
            . '<tr>';

        foreach ($slots as $slot) {
            [$bgcolor, $bordercolor, $textcolor, $symbol] = self::resolve_navigation_badge_style(
                $attemptobj->get_question_state($slot)
            );
            $displaynumber = $attemptobj->get_question_number($slot);

            $html .= '<td style="width:24px; border:1px solid ' . $bordercolor . '; background-color:' . $bgcolor
                . '; text-align:center; vertical-align:middle;">'
                . '<span style="font-size:9pt; font-weight:bold; color:' . $textcolor . ';">' . s($displaynumber) . '</span>'
                . '<br />' . self::render_navigation_symbol_html($symbol, $textcolor)
                . '</td>';
        }

        $html .= '</tr></table>';
        return $html;
    }

    /**
     * Resolves badge colors and symbol for one question state.
     *
     * @param \question_state $state Question state object.
     * @return string[] [background color, border color, text color, symbol type].
     */
    private static function resolve_navigation_badge_style(\question_state $state): array {
        if ($state->get_summary_state() === 'needsgrading') {
            return ['#fff6e8', '#d89b3b', '#9a6412', 'question'];
        }

        if ($state->is_correct()) {
            return ['#eaf7ea', '#4f9b59', '#1f6d2c', 'check'];
        } else if ($state->is_partially_correct()) {
            return ['#fff6e8', '#d89b3b', '#9a6412', '~'];
        } else if ($state->is_incorrect()) {
            return ['#fdecec', '#d06767', '#9f2f2f', 'cross'];
        } else if ($state->is_finished()) {
            return ['#f0f4f8', '#a8b7c7', '#546277', '•'];
        }

        return ['#ffffff', '#c4cfdb', '#667788', '•'];
    }

    /**
     * Renders the status symbol for one navigation badge.
     *
     * Uses built-in ZapfDingbats glyphs for check/cross to avoid dependency on
     * optional Unicode TTF font files in TCPDF deployments.
     *
     * @param string $symbol Symbol type from resolve_navigation_badge_style().
     * @param string $color  Text/icon color.
     * @return string HTML markup.
     */
    private static function render_navigation_symbol_html(string $symbol, string $color): string {
        if ($symbol === 'check') {
            // ZapfDingbats "3" = check mark.
            return '<span style="font-size:8pt; color:' . $color . '; font-family:zapfdingbats;">3</span>';
        }
        if ($symbol === 'cross') {
            // ZapfDingbats "7" = cross mark.
            return '<span style="font-size:8pt; color:' . $color . '; font-family:zapfdingbats;">7</span>';
        }
        if ($symbol === 'question') {
            return '<span style="display:inline-block; width:9px; height:9px; line-height:9px;'
                . ' border:1px solid ' . $color . '; border-radius:50%; text-align:center;'
                . ' font-size:6pt; font-weight:bold; color:' . $color . ';">?</span>';
        }

        return '<span style="font-size:8pt; color:' . $color . ';">' . s($symbol) . '</span>';
    }

    /**
     * Renders the questions and learner answers section.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param array        $config     Effective config values.
     * @return string HTML markup.
     */
    private static function render_questions(quiz_attempt $attemptobj, array $config): string {
        // The get_question_usage() method is restricted to unit tests in Moodle 5.x.
        // Load the question usage via the public question engine API instead.
        $quba  = \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid());
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
            $html .= '<tr style="background:#f0f4f8;">' .
                '<td colspan="2"><strong>' . get_string('pdf_question', 'local_eledia_exam2pdf') .
                ' ' . $num . ':</strong> ' . s($qtext) . '</td>' .
                '</tr>';

            // Learner answer.
            $response    = $qa->get_response_summary();
            $stateobject = $qa->get_state();
            $statelabel  = '';
            $statecolor  = '#555';

            if ($stateobject->is_correct()) {
                $statelabel = get_string('pdf_result_correct', 'local_eledia_exam2pdf');
                $statecolor = '#1a7a2e';
            } else if ($stateobject->is_partially_correct()) {
                $statelabel = get_string('pdf_result_partial', 'local_eledia_exam2pdf');
                $statecolor = '#e67e22';
            } else if ($stateobject->is_incorrect()) {
                $statelabel = get_string('pdf_result_incorrect', 'local_eledia_exam2pdf');
                $statecolor = '#c0392b';
            }

            $displayanswer = !empty($response)
                ? s($response)
                : get_string('pdf_noanswer', 'local_eledia_exam2pdf');

            $html .= '<tr>' .
                '<td style="width:40%; font-weight:bold;">' .
                get_string('pdf_youranswer', 'local_eledia_exam2pdf') . '</td>' .
                '<td>' . $displayanswer;

            if ($statelabel) {
                $html .= ' <span style="color:' . $statecolor . '; font-weight:bold;">(' . $statelabel . ')</span>';
            }

            $html .= '</td></tr>';

            // Correct answer (SC/MC + short answer — not essay).
            if (!empty($config['showcorrectanswers']) && $qtype !== 'essay') {
                $correct = self::get_correct_answer_text($question, $qtype);
                $html .= '<tr style="background:#fafafa;">' .
                    '<td style="font-weight:bold;">' . get_string('pdf_correctanswer', 'local_eledia_exam2pdf') . '</td>' .
                    '<td>' . ($correct ?: get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf')) . '</td>' .
                    '</tr>';
            }

            // Question score.
            $mark = $qa->get_mark();
            $maxmark = $qa->get_max_mark();
            $html .= '<tr style="background:#fafafa;">' .
                '<td style="font-weight:bold;">' . get_string('pdf_question_score', 'local_eledia_exam2pdf') . '</td>' .
                '<td>' . self::format_question_mark($mark) . ' / ' . self::format_question_mark($maxmark) . '</td>' .
                '</tr>';

            if (!empty($config['showquestioncomments'])) {
                $manualcomment = self::get_manual_comment_text($qa);
                if ($manualcomment !== '') {
                    $html .= '<tr style="background:#fafafa;">' .
                        '<td style="font-weight:bold;">' . get_string('pdf_question_comment', 'local_eledia_exam2pdf') . '</td>' .
                        '<td>' . s($manualcomment) . '</td>' .
                        '</tr>';
                }
            }

            $html .= '</table>';
            $num++;
        }

        return $html;
    }

    /**
     * Extracts a human-readable correct answer string for common question types.
     *
     * @param \question_definition $question The question definition object.
     * @param string               $qtype    The qtype name (e.g. 'multichoice').
     * @return string The resolved correct-answer text, or empty string if unknown.
     */
    private static function get_correct_answer_text(\question_definition $question, string $qtype): string {
        // The qtype_truefalse_question class does NOT expose an ->answers array —
        // it stores ->rightanswer (bool) via its loader. Handle truefalse separately
        // before touching $question->answers anywhere else.
        if ($qtype === 'truefalse') {
            if (property_exists($question, 'rightanswer') && isset($question->rightanswer)) {
                return $question->rightanswer
                    ? get_string('true', 'qtype_truefalse')
                    : get_string('false', 'qtype_truefalse');
            }
            return '';
        }

        // For all other qtypes that populate ->answers, guard against it being null or
        // not iterable — some question types lazy-load answers and may leave the
        // property empty when rendered via the question engine.
        $answers = (property_exists($question, 'answers') && is_iterable($question->answers))
            ? $question->answers
            : [];

        switch ($qtype) {
            case 'multichoice':
                $correct = [];
                foreach ($answers as $answer) {
                    if ($answer->fraction > 0) {
                        $correct[] = strip_tags($answer->answer);
                    }
                }
                return implode(', ', $correct);

            case 'shortanswer':
            case 'numerical':
                $best = null;
                foreach ($answers as $answer) {
                    if ($best === null || $answer->fraction > $best->fraction) {
                        $best = $answer;
                    }
                }
                return $best ? strip_tags($best->answer) : '';

            default:
                // For other types fall back to get_correct_response() if available.
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
     * Formats a question mark value for compact "x / y" display.
     *
     * @param float|null $mark Mark value from question attempt.
     * @return string
     */
    private static function format_question_mark(?float $mark): string {
        if ($mark === null) {
            return '0';
        }

        if (abs($mark - round($mark)) < 0.00001) {
            return (string) ((int) round($mark));
        }

        return rtrim(rtrim(number_format($mark, 2, '.', ''), '0'), '.');
    }

    /**
     * Returns the newest manual grading comment for one question attempt.
     *
     * @param \question_attempt $qa Question attempt.
     * @return string
     */
    private static function get_manual_comment_text(\question_attempt $qa): string {
        if (!method_exists($qa, 'has_manual_comment') || !$qa->has_manual_comment()) {
            return '';
        }

        [$comment] = $qa->get_manual_comment();
        if ($comment === null) {
            return '';
        }

        return trim((string) strip_tags((string) $comment));
    }

    /**
     * Helper: renders one two-column header table row.
     *
     * @param string $label The row label (left column).
     * @param mixed  $value The row value (right column), cast to string.
     * @return string HTML <tr> element.
     */
    private static function header_row(string $label, $value): string {
        return '<tr>' .
            '<td style="font-weight:bold; background:#f5f7fa; border:1px solid #e1e6ed;">'
            . s($label) . '</td>' .
            '<td style="border:1px solid #e1e6ed;">' . s((string) $value) . '</td>' .
            '</tr>';
    }
}
