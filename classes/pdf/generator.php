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

    // ══════════════════════════════════════════════════════════════════
    // DESIGN TOKENS
    // Inspired by the v1.1 mockup (pdf-auswertung-v1-stress.html).
    // Phase 4 will promote ACCENT_DEFAULT to a plugin setting; everything
    // else stays constant (semantic-state colors and neutrals).
    // ══════════════════════════════════════════════════════════════════
    /** eLeDia brand accent, used for headings, borders, comment blocks. */
    private const ACCENT_DEFAULT  = '#2a5d8a';
    private const ACCENT_SOFT     = '#eaf1f8';
    // Semantic state colors — fixed across themes (red/green/amber/blue).
    private const SUCCESS         = '#1f7a3f';
    private const SUCCESS_SOFT    = '#e8f3ec';
    private const FAIL            = '#b42318';
    private const FAIL_SOFT       = '#fcebe9';
    private const PARTIAL         = '#c48a00';  // amber yellow
    private const PARTIAL_INK     = '#5a3f00';  // dark brown for "?" text on amber
    private const PARTIAL_SOFT    = '#fdf3e1';
    private const PENDING         = '#1e6fb0';  // distinct blue, ≠ accent
    private const PENDING_SOFT    = '#e6f0fa';
    // Neutrals.
    private const INK             = '#1a1a1a';
    private const INK_SOFT        = '#4a4a4a';
    private const INK_MUTED       = '#7a7a7a';
    private const RULE            = '#dcdcdc';

    /**
     * Resolves the effective accent color for one render.
     *
     * Phase 1 always returns ACCENT_DEFAULT. Phase 4 will read from
     * $config['pdf_accentcolor'] and fall back to the default.
     *
     * @param array $config Effective plugin config.
     * @return string Hex color with leading "#".
     */
    private static function accent_color(array $config): string {
        $configured = (string) ($config['pdf_accentcolor'] ?? '');
        if ($configured !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $configured) === 1) {
            return $configured;
        }
        return self::ACCENT_DEFAULT;
    }

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

            $runningheader = self::build_running_header_html($attemptobj, $quiz, $config);
            $pdf->set_running_header_html($runningheader);
            $pdf->mark_next_as_cover();
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

                $runningheader = self::build_running_header_html($attemptobj, $quiz, $config);
                $pdf->set_running_header_html($runningheader);
                $pdf->mark_next_as_cover();
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

            /** @var string Running-header HTML rendered on every non-cover page. */
            protected string $runningheader = '';

            /** @var bool When true, the next call to Header() is skipped (cover page). */
            protected bool $skipnextheader = false;

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
             * Sets the running-header HTML used on every non-cover page.
             *
             * @param string $html Pre-rendered HTML (table or div).
             * @return void
             */
            public function set_running_header_html(string $html): void {
                $this->runningheader = $html;
            }

            /**
             * Marks the next page added via AddPage() as a cover page so the
             * running header is not rendered on it.
             *
             * @return void
             */
            public function mark_next_as_cover(): void {
                $this->skipnextheader = true;
            }

            /**
             * Renders the running page header (skipped for cover pages).
             *
             * TCPDF requires this method to be named "Header" (PascalCase) — it is
             * an internal TCPDF callback, not a Moodle API method. The Moodle
             * naming rule does not apply here; suppress the sniff accordingly.
             *
             * @return void
             * phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod
             */
            public function Header(): void {
                if ($this->skipnextheader) {
                    $this->skipnextheader = false;
                    return;
                }
                if ($this->runningheader === '') {
                    return;
                }
                // writeHTMLCell paints inside the top margin (28mm) above the body.
                $this->writeHTMLCell(0, 0, $this->original_lMargin, 10, $this->runningheader, 0, 0, false, true, 'L');
            }

            /**
             * Renders page footer.
             *
             * @return void
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
        $pdf->SetMargins(12, 28, 12);
        $pdf->SetHeaderMargin(8);
        $pdf->SetFooterMargin(10);
        $pdf->setHeaderFont(['helvetica', '', 9]);
        $pdf->setFooterFont(['helvetica', '', 9]);
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPrintHeader(true);
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
        $course  = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

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

        // Count questions still awaiting manual grading — drives the "pending" hero state.
        $pendingcount = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            $state = $attemptobj->get_question_state($slot);
            if ($state->get_summary_state() === 'needsgrading') {
                $pendingcount++;
            }
        }

        // COVER PAGE.
        $logohtml = self::get_logo_html();
        $html  = self::render_cover_header_band($logohtml, $attempt, $config);
        $html .= self::render_hero(
            $quiz,
            $course,
            $passed,
            $pendingcount,
            $attempt,
            $grade,
            $percentage,
            $config
        );
        $html .= self::render_cover_grid(
            $learner,
            $quiz,
            $course,
            $attempt,
            $duration,
            $gradepass,
            $config,
            $attemptobj
        );

        // FRAGEN-ÜBERSICHT — own full-width band under the meta blocks.
        // Lifts the navigation out of the cramped 50% right column so the
        // badges have room to breathe and line up across the whole page.
        $html .= self::render_navigation_compact($attemptobj, $config);

        // QUESTIONS PAGES — flow directly after the cover without a forced page
        // break; the first question starts on the cover page (if space) and each
        // subsequent question gets its own page (break is added BEFORE every
        // question card from the second one on, inside render_questions()).
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
     * Builds the running-header HTML rendered on every non-cover page.
     *
     * 3-column layout: learner | quiz · attempt · date | score · percentage,
     * followed by a thin bottom rule.
     *
     * @param quiz_attempt $attemptobj Fully initialised quiz_attempt object.
     * @param \stdClass    $quiz       The quiz DB record.
     * @param array        $config     Effective plugin config.
     * @return string HTML markup.
     */
    private static function build_running_header_html(
        quiz_attempt $attemptobj,
        \stdClass $quiz,
        array $config
    ): string {
        global $DB;

        $attempt = $attemptobj->get_attempt();
        $learner = $DB->get_record('user', ['id' => $attempt->userid], '*', IGNORE_MISSING);

        $learnername = $learner ? fullname($learner) : '';
        $quizname    = (string) $quiz->name;
        $attemptno   = (string) ($attempt->attempt ?? '');
        $datestr     = $attempt->timefinish
            ? userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'))
            : '';

        $grade      = ($quiz->sumgrades > 0) ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;
        $percentage = ($quiz->grade > 0) ? round($grade / $quiz->grade * 100, 1) : 0;
        $scoretext  = format_float($grade, 1) . ' / ' . format_float((float) $quiz->grade, 1)
            . ' · ' . format_float($percentage, 1) . ' %';

        $midparts = array_filter([
            $quizname,
            $attemptno !== '' ? get_string('pdf_attempt_hash', 'local_eledia_exam2pdf', $attemptno) : '',
            $datestr,
        ], static fn($v) => $v !== '' && $v !== null);
        $midline = implode(' · ', array_map('s', $midparts));

        $html  = '<table cellpadding="0" cellspacing="0" style="width:100%; font-size:7.5pt; color:'
            . self::INK_MUTED . ';"><tr>';
        $html .= '<td width="33%" style="text-align:left;">' . s($learnername) . '</td>';
        $html .= '<td width="40%" style="text-align:center;">' . $midline . '</td>';
        $html .= '<td width="27%" style="text-align:right;">' . s($scoretext) . '</td>';
        $html .= '</tr></table>';
        $html .= '<div style="border-bottom:0.2mm solid ' . self::RULE . '; margin-top:1mm;">&nbsp;</div>';
        return $html;
    }

    /**
     * Renders the cover page's top header band: logo | title | attempt info.
     *
     * @param string    $logohtml Optional logo <img> HTML (may be empty).
     * @param \stdClass $attempt  The quiz_attempts DB record.
     * @param array     $config   Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_cover_header_band(string $logohtml, \stdClass $attempt, array $config): string {
        $accent = self::accent_color($config);

        $attemptno = (string) ($attempt->attempt ?? '');
        $datestr   = $attempt->timefinish
            ? userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'))
            : '';
        $rightparts = array_filter([
            $attemptno !== '' ? get_string('pdf_attempt_hash', 'local_eledia_exam2pdf', $attemptno) : '',
            $datestr,
        ], static fn($v) => $v !== '' && $v !== null);
        $rightline = implode(' · ', array_map('s', $rightparts));

        $html  = '<table cellpadding="0" cellspacing="0" style="width:100%; margin:0 0 1mm 0;"><tr>';
        $html .= '<td width="33%" valign="middle" style="text-align:left;">' . $logohtml . '</td>';
        $html .= '<td width="34%" valign="middle" style="text-align:center;'
            . ' font-size:16pt; color:' . $accent . '; font-weight:bold;'
            . ' letter-spacing:0.3pt;">'
            . s(get_string('pdf_cover_title', 'local_eledia_exam2pdf'))
            . '</td>';
        $html .= '<td width="33%" valign="middle" style="text-align:right;'
            . ' font-size:8pt; color:' . self::INK_MUTED
            . '; text-transform:uppercase; letter-spacing:0.4pt;">'
            . $rightline . '</td>';
        $html .= '</tr></table>';
        $html .= '<div style="border-bottom:0.3mm solid ' . self::RULE . '; margin:0 0 4mm 0;">&nbsp;</div>';
        return $html;
    }

    /**
     * Renders the two-column cover grid below the hero.
     *
     * Left column: participant details + attempt details.
     * Right column: compact navigation + quiz context.
     *
     * @param \stdClass    $learner     The user DB record.
     * @param \stdClass    $quiz        The quiz DB record.
     * @param \stdClass    $course      The course DB record.
     * @param \stdClass    $attempt     The quiz_attempts DB record.
     * @param string       $duration    Preformatted duration (HH:MM:SS) or empty string.
     * @param float        $gradepass   Gradebook pass threshold (0 = none configured).
     * @param array        $config      Effective plugin config.
     * @param quiz_attempt $attemptobj  Attempt object used for navigation grid.
     * @return string HTML markup.
     */
    private static function render_cover_grid(
        \stdClass $learner,
        \stdClass $quiz,
        \stdClass $course,
        \stdClass $attempt,
        string $duration,
        float $gradepass,
        array $config,
        quiz_attempt $attemptobj
    ): string {
        $accent = self::accent_color($config);

        // Two balanced columns: LEFT = participant block, RIGHT = attempt meta.
        // Both blocks have roughly the same visual weight (3-5 rows), so the
        // columns end at a similar height. The navigation grid moved out of
        // this grid into its own full-width block below (see
        // render_navigation_fullwidth()).
        $html  = '<table cellpadding="0" cellspacing="0" style="width:100%; margin:0 0 4mm 0;"><tr>';

        // LEFT — TEILNEHMER/IN.
        $html .= '<td width="50%" valign="top" style="padding-right:3mm;">';
        $html .= self::render_meta_block_header(
            get_string('pdf_participant_block', 'local_eledia_exam2pdf'),
            $accent
        );
        $html .= '<div style="font-size:9pt; color:' . self::INK . '; font-weight:bold;">'
            . s(fullname($learner)) . '</div>';
        if (!empty($learner->email)) {
            $html .= '<div style="font-size:8pt; color:' . self::INK_MUTED . ';">'
                . s($learner->email) . '</div>';
        }
        $html .= '<div style="font-size:8pt; color:' . self::INK_MUTED . ';">'
            . get_string('pdf_moodleid', 'local_eledia_exam2pdf') . ': ' . (int) $learner->id . '</div>';
        $html .= '</td>';

        // RIGHT — VERSUCH.
        $attemptrows = '';
        if (!empty($config['show_timestamp']) && $attempt->timefinish) {
            $attemptrows .= self::render_meta_row(
                get_string('pdf_timestamp', 'local_eledia_exam2pdf'),
                userdate($attempt->timefinish)
            );
        }
        if (!empty($config['show_duration']) && $duration !== '') {
            $attemptrows .= self::render_meta_row(
                get_string('pdf_duration', 'local_eledia_exam2pdf'),
                $duration
            );
        }
        if (!empty($config['show_attemptnumber'])) {
            $attemptrows .= self::render_meta_row(
                get_string('pdf_attemptnumber', 'local_eledia_exam2pdf'),
                (string) $attempt->attempt
            );
        }
        if (!empty($config['show_passgrade']) && $gradepass > 0) {
            $attemptrows .= self::render_meta_row(
                get_string('pdf_passgrade', 'local_eledia_exam2pdf'),
                format_float($gradepass, 2)
            );
        }
        if (!empty($config['show_percentage'])) {
            $attemptrows .= self::render_meta_row(
                get_string('pdf_percentage', 'local_eledia_exam2pdf'),
                format_float((float) ($attempt->sumgrades ?? 0) / max(1, (float) $quiz->sumgrades) * 100, 1) . ' %'
            );
        }

        $html .= '<td width="50%" valign="top" style="padding-left:3mm;">';
        if ($attemptrows !== '') {
            $html .= self::render_meta_block_header(
                get_string('pdf_attempt_block', 'local_eledia_exam2pdf'),
                $accent
            );
            $html .= '<table cellpadding="2" cellspacing="0" style="width:100%;">' . $attemptrows . '</table>';
        }
        $html .= '</td>';

        $html .= '</tr></table>';

        // Silence unused params — kept in the signature for forward compat with
        // follow-up phases that may re-introduce a course block.
        unset($course, $attemptobj);

        return $html;
    }

    /**
     * Renders the three-cell hero block: status + score + quiz context.
     *
     * @param \stdClass $quiz The quiz DB record.
     * @param \stdClass $course The course DB record.
     * @param bool $passed Whether the attempt passed per gradebook threshold.
     * @param int $pendingcount Number of slots awaiting manual grading.
     * @param \stdClass $attempt The quiz_attempts DB record.
     * @param float $grade Effective attempt grade (already scaled to $quiz->grade).
     * @param float $percentage Percent of maximum, 0-100.
     * @param array $config Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_hero(
        \stdClass $quiz,
        \stdClass $course,
        bool $passed,
        int $pendingcount,
        \stdClass $attempt,
        float $grade,
        float $percentage,
        array $config
    ): string {
        // Resolve state: pending outranks passed/failed because grading is not final.
        if ($pendingcount > 0) {
            $statelabel = get_string('pdf_status_pending', 'local_eledia_exam2pdf');
            $statebg    = self::PENDING_SOFT;
            $statefg    = self::PENDING;
            $iconchar   = '?';
            $iconfont   = 'helvetica';
            $substr     = get_string('pdf_pending_questions', 'local_eledia_exam2pdf', $pendingcount);
        } else if ($passed) {
            $statelabel = get_string('pdf_status_passed', 'local_eledia_exam2pdf');
            $statebg    = self::SUCCESS_SOFT;
            $statefg    = self::SUCCESS;
            $iconchar   = '3'; // ZapfDingbats check.
            $iconfont   = 'zapfdingbats';
            $substr     = get_string('pdf_status_label', 'local_eledia_exam2pdf');
        } else {
            $statelabel = get_string('pdf_status_failed', 'local_eledia_exam2pdf');
            $statebg    = self::FAIL_SOFT;
            $statefg    = self::FAIL;
            $iconchar   = '7'; // ZapfDingbats cross.
            $iconfont   = 'zapfdingbats';
            $substr     = get_string('pdf_status_label', 'local_eledia_exam2pdf');
        }

        $scorestr = format_float($grade, 1) . ' / ' . format_float((float) $quiz->grade, 1);
        $pctstr   = format_float($percentage, 1) . ' %';

        $quizname      = s($quiz->name);
        $coursename    = s($course->fullname);
        $completedstr  = '';
        if ($attempt->timefinish) {
            $completedstr = userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'));
        }

        $html = '<table cellpadding="0" cellspacing="0"'
            . ' style="width:100%; border:0.3mm solid ' . self::RULE . '; margin:0 0 4mm 0;">'
            . '<tr>';

        // Status cell — eyebrow on TOP, then big icon, then bold label.
        $html .= '<td width="27%"'
            . ' style="background-color:' . $statebg . '; padding:4mm 4mm;'
            . ' text-align:center; vertical-align:middle;'
            . ' border-right:0.3mm solid ' . self::RULE . ';">'
            . '<div style="font-size:7pt; color:' . self::INK_MUTED
            . '; letter-spacing:0.5pt; margin-bottom:1.5mm;">'
            . strtoupper(s($substr)) . '</div>'
            . '<div style="font-size:22pt; color:' . $statefg . '; font-weight:bold; font-family:' . $iconfont . ';">'
            . $iconchar . '</div>'
            . '<div style="font-size:11pt; color:' . $statefg
            . '; font-weight:bold; margin-top:1mm;">' . strtoupper(s($statelabel)) . '</div>'
            . '</td>';

        // Score cell — eyebrow on TOP, then big score.
        $html .= '<td width="26%"'
            . ' style="padding:4mm 4mm; vertical-align:middle; text-align:center;'
            . ' border-right:0.3mm solid ' . self::RULE . ';">'
            . '<div style="font-size:7pt; color:' . self::INK_MUTED
            . '; letter-spacing:0.5pt; margin-bottom:2mm;">'
            . strtoupper(s(get_string('pdf_score_points_label', 'local_eledia_exam2pdf')))
            . '</div>'
            . '<div style="font-size:20pt; color:' . self::INK . '; font-weight:bold;">'
            . s($scorestr) . '</div>'
            . '<div style="font-size:10pt; color:' . self::INK_SOFT . '; margin-top:1mm;">'
            . s($pctstr) . '</div>'
            . '</td>';

        // Quiz-context cell — eyebrow "QUIZ" on TOP, then quiz + course + date.
        $html .= '<td width="47%"'
            . ' style="padding:4mm 4mm; vertical-align:middle;">'
            . '<div style="font-size:7pt; color:' . self::INK_MUTED
            . '; letter-spacing:0.5pt; margin-bottom:1.5mm;">'
            . strtoupper(s(get_string('pdf_context_block', 'local_eledia_exam2pdf')))
            . '</div>'
            . '<div style="font-size:11pt; color:' . self::INK . '; font-weight:bold; margin-bottom:1mm;">'
            . $quizname . '</div>'
            . '<div style="font-size:8pt; color:' . self::INK_MUTED . ';">'
            . get_string('course') . ': ' . $coursename . '</div>';
        if ($completedstr !== '') {
            $html .= '<div style="font-size:8pt; color:' . self::INK_MUTED . '; margin-top:0.5mm;">'
                . get_string('pdf_timestamp', 'local_eledia_exam2pdf') . ': ' . s($completedstr) . '</div>';
        }
        $html .= '</td>';

        $html .= '</tr></table>';

        return $html;
    }

    /**
     * Renders one accent-colored block header (uppercase label + underline).
     *
     * @param string $title The block title.
     * @param string $accent Hex accent color.
     * @return string HTML markup.
     */
    private static function render_meta_block_header(string $title, string $accent): string {
        return '<div style="font-size:8pt; color:' . $accent
            . '; font-weight:bold; text-transform:uppercase; letter-spacing:0.5pt;'
            . ' border-bottom:0.3mm solid ' . self::RULE . '; padding-bottom:1mm; margin-bottom:2mm;">'
            . s($title) . '</div>';
    }

    /**
     * Renders one label/value row inside a meta block.
     *
     * @param string $label The row label.
     * @param mixed $value The row value.
     * @return string HTML <tr> element.
     */
    private static function render_meta_row(string $label, $value): string {
        return '<tr>'
            . '<td style="font-size:9pt; color:' . self::INK_MUTED . ';">' . s($label) . '</td>'
            . '<td align="right" style="font-size:9pt; color:' . self::INK . '; font-weight:bold;">'
            . s((string) $value) . '</td>'
            . '</tr>';
    }

    /**
     * Renders a compact navigation grid (flat layout, 7 badges per row) plus
     * a 4-state legend below the grid. Used inside the cover grid's right column.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param array        $config     Effective plugin config (drives accent color).
     * @return string HTML markup.
     */
    private static function render_navigation_compact(quiz_attempt $attemptobj, array $config): string {
        $slots = $attemptobj->get_slots();
        if (empty($slots)) {
            return '';
        }

        $accent = self::accent_color($config);

        // Tally state counts for the legend.
        $counts = ['correct' => 0, 'partial' => 0, 'wrong' => 0, 'pending' => 0];
        foreach ($slots as $slot) {
            $state = $attemptobj->get_question_state($slot);
            if ($state->get_summary_state() === 'needsgrading') {
                $counts['pending']++;
            } else if ($state->is_correct()) {
                $counts['correct']++;
            } else if ($state->is_partially_correct()) {
                $counts['partial']++;
            } else if ($state->is_incorrect()) {
                $counts['wrong']++;
            }
        }

        // Heading with inline question count — matches the mockup's
        // "FRAGEN-ÜBERSICHT (28 FRAGEN)" two-tone style (accent label + muted count).
        $navtitle = mb_strtoupper(
            (string) get_string('pdf_navigation_heading', 'local_eledia_exam2pdf'),
            'UTF-8'
        );
        $navcount = (string) get_string(
            'pdf_nav_legend_all',
            'local_eledia_exam2pdf',
            (string) count($slots)
        );
        $html  = '<div style="border-bottom:0.3mm solid ' . self::RULE
            . '; padding-bottom:1mm; margin-bottom:2mm;">'
            . '<span style="font-size:8pt; color:' . $accent
            . '; font-weight:bold; letter-spacing:0.5pt;">' . s($navtitle) . '</span>'
            . ' <span style="font-size:7.5pt; color:' . self::INK_MUTED
            . '; font-weight:normal;">(' . s($navcount) . ')</span>'
            . '</div>';

        // Badge grid — full width. Dynamic per-row count so few questions fit
        // neatly in one row, many questions wrap at a sensible width.
        // 14 badges ≈ 95mm wide, fits easily inside the 12mm-margin A4 content.
        $slotcount = count($slots);
        $perrow    = min($slotcount, 14);
        $html  .= '<table cellpadding="2" cellspacing="2" style="border-collapse:separate;">';
        $i = 0;
        foreach ($slots as $slot) {
            if ($i % $perrow === 0) {
                if ($i > 0) {
                    $html .= '</tr>';
                }
                $html .= '<tr>';
            }

            [$bgcolor, $bordercolor, $textcolor, $symbol] = self::resolve_navigation_badge_style(
                $attemptobj->get_question_state($slot)
            );
            $displaynumber = $attemptobj->get_question_number($slot);

            $html .= '<td style="width:22px; border:0.25mm solid ' . $bordercolor
                . '; background-color:' . $bgcolor
                . '; text-align:center; vertical-align:middle;">'
                . '<span style="font-size:8pt; font-weight:bold; color:' . $textcolor . ';">'
                . s($displaynumber) . '</span>'
                . '<br />' . self::render_navigation_symbol_html($symbol, $textcolor)
                . '</td>';
            $i++;
        }
        if ($i > 0) {
            // Pad the last row with blank cells so columns align.
            $remaining = ($perrow - ($i % $perrow)) % $perrow;
            for ($p = 0; $p < $remaining; $p++) {
                $html .= '<td style="width:22px;">&nbsp;</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        // 4-state legend line.
        // Pending glyph: ZapfDingbats "n" = filled square (Unicode ■ U+25A0).
        // Helvetica does not carry that glyph, so we keep it in ZapfDingbats.
        $legendparts = [
            '<span style="color:' . self::SUCCESS . '; font-family:zapfdingbats;">3</span> '
                . s(get_string('pdf_nav_legend_correct', 'local_eledia_exam2pdf', (string) $counts['correct'])),
            '<span style="color:' . self::PARTIAL . '; font-weight:bold;">?</span> '
                . s(get_string('pdf_nav_legend_partial', 'local_eledia_exam2pdf', (string) $counts['partial'])),
            '<span style="color:' . self::FAIL . '; font-family:zapfdingbats;">7</span> '
                . s(get_string('pdf_nav_legend_wrong', 'local_eledia_exam2pdf', (string) $counts['wrong'])),
            '<span style="color:' . self::PENDING . '; font-family:zapfdingbats;">n</span> '
                . s(get_string('pdf_nav_legend_pending', 'local_eledia_exam2pdf', (string) $counts['pending'])),
        ];
        $html .= '<div style="margin-top:2mm; font-size:7.5pt; color:' . self::INK_SOFT . ';">'
            . implode(' &nbsp;·&nbsp; ', $legendparts)
            . '</div>';

        return $html;
    }

    /**
     * Resolves badge colors and symbol for one question state.
     *
     * @param \question_state $state Question state object.
     * @return string[] [background color, border color, text color, symbol type].
     */
    private static function resolve_navigation_badge_style(\question_state $state): array {
        // Returns [background, border, text-color, symbol-glyph].
        // Symbol glyph is rendered by render_navigation_symbol_html().
        // 4-state palette per v1.1 mockup — works in B/W print because
        // each state carries (a) a distinct fill/border pattern AND
        // (b) a distinct symbol.

        if ($state->get_summary_state() === 'needsgrading') {
            // PENDING: solid blue, no symbol — fill itself is the signal.
            return [self::PENDING, self::PENDING, '#ffffff', 'dot'];
        }

        if ($state->is_correct()) {
            return [self::SUCCESS, self::SUCCESS, '#ffffff', 'check'];
        } else if ($state->is_partially_correct()) {
            // PARTIAL: solid amber + "?" — dark ink on yellow for contrast.
            return [self::PARTIAL, self::PARTIAL, self::PARTIAL_INK, 'question'];
        } else if ($state->is_incorrect()) {
            // WRONG: white fill + red outline + red ✗.
            return ['#ffffff', self::FAIL, self::FAIL, 'cross'];
        } else if ($state->is_finished()) {
            return ['#f0f4f8', '#a8b7c7', '#546277', 'dot'];
        }

        return ['#ffffff', '#c4cfdb', '#667788', 'dot'];
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
            // ZapfDingbats "3" = check mark — works without optional TTFs.
            return '<span style="font-size:8pt; color:' . $color . '; font-family:zapfdingbats;">3</span>';
        }
        if ($symbol === 'cross') {
            // ZapfDingbats "7" = cross mark.
            return '<span style="font-size:8pt; color:' . $color . '; font-family:zapfdingbats;">7</span>';
        }
        if ($symbol === 'question') {
            // Plain "?" — bold, state color already matches (amber fill + dark ink).
            return '<span style="font-size:8pt; font-weight:bold; color:' . $color . ';">?</span>';
        }
        if ($symbol === 'dot') {
            // Pending state: no symbol — fill color itself is the signal.
            // A non-breaking space keeps the badge layout stable.
            return '<span style="font-size:8pt; color:' . $color . ';">&nbsp;</span>';
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

        $accent = self::accent_color($config);

        // Uppercase "FRAGEN & ANTWORTEN · N FRAGEN" section heading with bottom rule.
        // The heading is split on " · " into a bold accent-colored main part and
        // a lighter muted suffix — matches the mockup's elegant two-tone title.
        // Uppercase is applied in PHP (NOT via CSS text-transform) because TCPDF
        // uppercases "&amp;" to "&AMP;", which then renders as literal text.
        $heading = mb_strtoupper(get_string(
            'pdf_questions_section_heading',
            'local_eledia_exam2pdf',
            (string) count($slots)
        ), 'UTF-8');
        $headingparts = explode(' · ', $heading, 2);
        $headingmain = $headingparts[0];
        $headingsub  = $headingparts[1] ?? '';

        $html  = '<div style="border-bottom:0.3mm solid ' . self::RULE
            . '; padding-bottom:1.5mm; margin:0 0 3mm 0;">'
            . '<span style="font-size:10pt; color:' . $accent
            . '; font-weight:bold; letter-spacing:0.6pt;">'
            . s($headingmain)
            . '</span>';
        if ($headingsub !== '') {
            $html .= '<span style="font-size:9pt; color:' . self::INK_MUTED
                . '; font-weight:normal; letter-spacing:0.4pt;">'
                . ' · ' . s($headingsub)
                . '</span>';
        }
        $html .= '</div>';

        $num = 1;
        foreach ($slots as $slot) {
            $qa       = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $qtype    = $question->get_type_name();
            $state    = $qa->get_state();

            // Force a page break BEFORE every card from the second one on, so
            // each question sits on its own page (matching the user-requested
            // one-question-per-page flow). Question 1 flows directly from the
            // cover page to use up the remaining space.
            if ($num > 1) {
                $html .= '<br pagebreak="true" />';
            }

            $html .= self::render_question_card($qa, $question, $qtype, $state, $num, $config, $accent);
            $num++;
        }

        return $html;
    }

    /**
     * Renders one question as a card with colored left stripe, header, answers
     * and optional grading comment.
     *
     * TCPDF does not reliably render `border-radius` or `border-left: 1.5mm`
     * on a single element, so the card is emulated with a two-cell outer
     * table: a narrow colored stripe cell on the left plus the content cell
     * on the right (soft state-tinted background).
     *
     * @param \question_attempt     $qa       Question attempt wrapper.
     * @param \question_definition  $question Question definition.
     * @param string                $qtype    Question type (e.g. 'multichoice').
     * @param \question_state       $state    Question state.
     * @param int                   $num      1-based visible question number.
     * @param array                 $config   Effective plugin config.
     * @param string                $accent   Resolved accent color.
     * @return string HTML markup.
     */
    private static function render_question_card(
        \question_attempt $qa,
        \question_definition $question,
        string $qtype,
        \question_state $state,
        int $num,
        array $config,
        string $accent
    ): string {
        [$bgsoft, $stripe, $markbg, $markfg, $marksymbol, $statelabel, $ispending] =
            self::resolve_question_card_style($state);

        $qtext = trim(strip_tags($question->questiontext));

        $mark    = $qa->get_mark();
        $maxmark = $qa->get_max_mark();
        $scoretext = self::format_question_mark($mark) . ' / ' . self::format_question_mark($maxmark);

        // Outer 2-cell table: stripe (2mm) + content (rest).
        // Card body is pure WHITE with a thin RULE border on the outer table
        // — echoes the mockup's elegant, airy look. Only the narrow left
        // stripe and the score pill carry the state color.
        $html = '<table cellpadding="0" cellspacing="0"'
            . ' style="width:100%; margin-bottom:3mm; border:0.25mm solid ' . self::RULE . ';"><tr>';
        $html .= '<td width="2mm" style="background-color:' . $stripe . ';">&nbsp;</td>';
        $html .= '<td style="background-color:#ffffff; padding:3mm 4mm 3mm 4mm;">';

        // Unused — the card background no longer echoes the soft state tint.
        unset($bgsoft);

        // Header: question text (left) + score + mark pill (right).
        $html .= '<table cellpadding="0" cellspacing="0" style="width:100%;"><tr>';
        $html .= '<td style="font-size:11pt;">'
            . '<span style="color:' . self::INK_MUTED . '; font-weight:normal; font-size:9pt;">'
            . get_string('pdf_question', 'local_eledia_exam2pdf') . ' ' . $num . '</span> '
            . '<span style="color:' . self::INK . '; font-weight:bold;">' . s($qtext) . '</span>'
            . '</td>';
        $html .= '<td width="24mm" align="right" style="font-size:8.5pt; color:' . self::INK_SOFT . ';">'
            . s($scoretext)
            . ' <span style="background-color:' . $markbg . '; color:' . $markfg
            . '; font-family:zapfdingbats; font-size:7pt; font-weight:bold;">&nbsp;'
            . $marksymbol . '&nbsp;</span>'
            . '</td>';
        $html .= '</tr></table>';

        // Optional qtype hint row (Single choice / Multiple choice / Free text / ...).
        $hint = self::resolve_qtype_hint($qtype, $state);
        if ($hint !== '') {
            $html .= '<div style="font-size:7.5pt; color:' . self::INK_MUTED
                . '; font-style:italic; margin-top:0.8mm;">'
                . s($hint)
                . '</div>';
        }

        // Answers sub-table.
        $response      = $qa->get_response_summary();
        $answertext    = ($response !== null && $response !== '')
            ? (string) $response
            : '';
        $displayanswer = self::decorate_answer_value($answertext, $state);

        $html .= '<table cellpadding="0" cellspacing="0" style="width:100%; margin-top:1.5mm;">';
        $html .= self::render_q_arow(get_string('pdf_youranswer', 'local_eledia_exam2pdf'), $displayanswer);

        // Optional solution row (SC/MC + short-answer, not essay).
        if (!empty($config['showcorrectanswers']) && $qtype !== 'essay') {
            $correct = self::get_correct_answer_text($question, $qtype);
            if ($correct === '') {
                $correct = get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf');
            }
            // Prefix solution with ZapfDingbats check — green/soft to echo the mockup.
            $solvalue = '<span style="color:' . self::SUCCESS . '; font-family:zapfdingbats; font-weight:bold;">3</span>'
                . ' <span style="color:' . self::SUCCESS . ';">' . s($correct) . '</span>';
            $html .= self::render_q_arow(
                get_string('pdf_correctanswer', 'local_eledia_exam2pdf'),
                $solvalue
            );
        }

        $html .= '</table>';

        // Optional pending note (inside pending-state cards).
        if ($ispending) {
            $html .= '<div style="margin-top:1.5mm; font-size:8pt; color:' . self::PENDING
                . '; font-style:italic;">'
                . get_string('pdf_pending_note', 'local_eledia_exam2pdf')
                . '</div>';
        }

        // Optional grading comment (manual grading — essays etc.).
        if (!empty($config['showquestioncomments'])) {
            [$commenttext, $graderlabel] = self::get_manual_comment_meta($qa);
            if ($commenttext !== '') {
                $html .= self::render_grading_comment_block($commenttext, $graderlabel, $accent);
            }
        }

        $html .= '</td></tr></table>';
        return $html;
    }

    /**
     * Resolves the one-line italic hint shown below a question title.
     *
     * Essays change copy based on the graded/pending state. Other types use a
     * single hint string each. Returns empty string when no hint is defined
     * for the given question type.
     *
     * @param string          $qtype Question type (e.g. 'multichoice').
     * @param \question_state $state Question state object.
     * @return string Plain hint text (empty when no hint applies).
     */
    private static function resolve_qtype_hint(string $qtype, \question_state $state): string {
        $key = null;
        switch ($qtype) {
            case 'multichoice':
                // Differentiate single vs. multiple via question class when available.
                $key = 'pdf_qtype_hint_multichoice_single';
                break;
            case 'truefalse':
                $key = 'pdf_qtype_hint_truefalse';
                break;
            case 'shortanswer':
                $key = 'pdf_qtype_hint_shortanswer';
                break;
            case 'numerical':
                $key = 'pdf_qtype_hint_numerical';
                break;
            case 'essay':
                $key = ($state->get_summary_state() === 'needsgrading')
                    ? 'pdf_qtype_hint_essay_pending'
                    : 'pdf_qtype_hint_essay';
                break;
        }

        if ($key === null) {
            return '';
        }
        return (string) get_string($key, 'local_eledia_exam2pdf');
    }

    /**
     * Decorates the learner's answer value with a state-appropriate color and
     * optional leading glyph. Returns pre-escaped HTML.
     *
     * @param string          $raw   Raw response summary (possibly empty).
     * @param \question_state $state Question state object.
     * @return string HTML fragment safe to embed in a table cell.
     */
    private static function decorate_answer_value(string $raw, \question_state $state): string {
        if ($raw === '') {
            return '<span style="color:' . self::INK_MUTED . '; font-style:italic;">'
                . s(get_string('pdf_noanswer', 'local_eledia_exam2pdf'))
                . '</span>';
        }

        $escaped = s($raw);

        if ($state->is_correct()) {
            return '<span style="color:' . self::SUCCESS . '; font-family:zapfdingbats; font-weight:bold;">3</span>'
                . ' <span style="color:' . self::SUCCESS . ';">' . $escaped . '</span>';
        }

        if ($state->is_partially_correct()) {
            return '<span style="color:' . self::PARTIAL . ';">' . $escaped . '</span>';
        }

        if ($state->is_incorrect()) {
            return '<span style="color:' . self::FAIL . '; font-family:zapfdingbats; font-weight:bold;">7</span>'
                . ' <span style="color:' . self::FAIL . ';">' . $escaped . '</span>';
        }

        // Pending / unknown → neutral ink.
        return '<span style="color:' . self::INK . ';">' . $escaped . '</span>';
    }

    /**
     * Resolves the card's visual style for a given question state.
     *
     * @param \question_state $state Question state object.
     * @return array [bg_soft, stripe, mark_bg, mark_fg, mark_symbol, state_label, is_pending].
     */
    private static function resolve_question_card_style(\question_state $state): array {
        // Pending / needs-grading → blue soft bg, blue stripe, blue pill (no symbol).
        if ($state->get_summary_state() === 'needsgrading') {
            return [
                self::PENDING_SOFT,
                self::PENDING,
                self::PENDING,
                '#ffffff',
                '&nbsp;',
                get_string('pdf_status_pending', 'local_eledia_exam2pdf'),
                true,
            ];
        }

        if ($state->is_correct()) {
            return [
                self::SUCCESS_SOFT,
                self::SUCCESS,
                self::SUCCESS,
                '#ffffff',
                '3', // ZapfDingbats check.
                get_string('pdf_result_correct', 'local_eledia_exam2pdf'),
                false,
            ];
        }

        if ($state->is_partially_correct()) {
            return [
                self::PARTIAL_SOFT,
                self::PARTIAL,
                self::PARTIAL,
                self::PARTIAL_INK,
                '?',
                get_string('pdf_result_partial', 'local_eledia_exam2pdf'),
                false,
            ];
        }

        if ($state->is_incorrect()) {
            return [
                self::FAIL_SOFT,
                self::FAIL,
                self::FAIL,
                '#ffffff',
                '7', // ZapfDingbats cross.
                get_string('pdf_result_incorrect', 'local_eledia_exam2pdf'),
                false,
            ];
        }

        // Fallback (unanswered, finished-but-unknown) → neutral grey.
        return [
            '#f6f6f6',
            self::INK_MUTED,
            self::INK_MUTED,
            '#ffffff',
            '&nbsp;',
            '',
            false,
        ];
    }

    /**
     * Renders one answer row inside a question card.
     *
     * The $value parameter is treated as pre-formatted HTML — callers must
     * escape plain user data via `s()` before passing it in. This lets the
     * solution row inject ZapfDingbats icon markup unchanged.
     *
     * @param string $label Uppercase short label (Antwort / Lösung / ...).
     * @param string $value Pre-escaped HTML value.
     * @return string HTML markup (single `<tr>`).
     */
    private static function render_q_arow(string $label, string $value): string {
        return '<tr>'
            . '<td width="32mm" style="font-size:7pt; color:' . self::INK_MUTED
            . '; font-weight:bold; text-transform:uppercase; vertical-align:top;">'
            . s($label) . '</td>'
            . '<td style="font-size:8.5pt; color:' . self::INK . ';">' . $value . '</td>'
            . '</tr>';
    }

    /**
     * Renders an accent-colored grading-comment block below a question card's
     * answers area.
     *
     * The label shows "BEWERTUNGSKOMMENTAR" in uppercase. When the grader
     * label is non-empty, it is appended in muted ink as " — Grader, Datum".
     *
     * @param string $comment     Plain-text grading comment (already stripped).
     * @param string $graderlabel Pre-formatted "Grader, Datum" suffix (may be empty).
     * @param string $accent      Resolved accent color.
     * @return string HTML markup.
     */
    private static function render_grading_comment_block(
        string $comment,
        string $graderlabel,
        string $accent
    ): string {
        // Inner 2-cell table: 1mm accent stripe + accent-soft content.
        $html = '<table cellpadding="0" cellspacing="0" style="width:100%; margin-top:2mm;"><tr>';
        $html .= '<td width="1mm" style="background-color:' . $accent . ';">&nbsp;</td>';
        $html .= '<td style="background-color:' . self::ACCENT_SOFT . '; padding:1.5mm 2.5mm 1.5mm 2.5mm;">';

        // Label line: "BEWERTUNGSKOMMENTAR" + optional grader suffix.
        $html .= '<div style="font-size:7pt; color:' . $accent
            . '; font-weight:bold; text-transform:uppercase; letter-spacing:0.4pt; margin-bottom:0.8mm;">'
            . s(get_string('pdf_comment_label', 'local_eledia_exam2pdf'));
        if ($graderlabel !== '') {
            $html .= ' <span style="color:' . self::INK_MUTED
                . '; font-weight:normal; text-transform:none; letter-spacing:0;">'
                . s($graderlabel) . '</span>';
        }
        $html .= '</div>';

        $html .= '<div style="font-size:8pt; color:' . self::INK_SOFT . ';">'
            . s($comment) . '</div>';
        $html .= '</td></tr></table>';
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
     * Returns the newest manual grading comment plus a pre-formatted
     * "Grader, Datum" suffix for display.
     *
     * @param \question_attempt $qa Question attempt.
     * @return array{0: string, 1: string} [comment_text, grader_label].
     *         Both strings are plain-text (no HTML); callers must escape on render.
     *         Empty comment indicates "no manual comment available".
     */
    private static function get_manual_comment_meta(\question_attempt $qa): array {
        global $DB;

        // Robust step-walk: inspect every step backwards and find the newest one
        // that carries a non-empty `comment` behaviour var. This is more reliable
        // than `has_manual_comment()` + `get_manual_comment()` which have been
        // observed to return empty for questions that DO have a teacher comment
        // (notably auto-graded questions with a comment attached by the grader
        // UI, where the step's state string does not contain "manualgraded").
        $commenttext = '';
        $grader = '';
        $datestr = '';

        try {
            $numsteps = method_exists($qa, 'get_num_steps') ? (int) $qa->get_num_steps() : 0;
            for ($i = $numsteps - 1; $i >= 0; $i--) {
                $step = $qa->get_step($i);
                if (!$step->has_behaviour_var('comment')) {
                    continue;
                }
                $raw = $step->get_behaviour_var('comment');
                if ($raw === null) {
                    continue;
                }
                $clean = trim((string) strip_tags((string) $raw));
                if ($clean === '') {
                    continue;
                }

                $commenttext = $clean;

                if ($step->get_user_id()) {
                    $user = $DB->get_record('user', ['id' => $step->get_user_id()], '*', IGNORE_MISSING);
                    if ($user) {
                        $grader = fullname($user);
                    }
                }
                $ts = $step->get_timecreated();
                if ($ts) {
                    $datestr = userdate($ts, get_string('strftimedatetimeshort', 'core_langconfig'));
                }
                break;
            }
        } catch (\Throwable $e) {
            debugging('exam2pdf: manual-comment meta lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        if ($commenttext === '') {
            return ['', ''];
        }

        $graderlabel = '';
        if ($grader !== '' && $datestr !== '') {
            $graderlabel = get_string(
                'pdf_comment_by',
                'local_eledia_exam2pdf',
                (object) ['grader' => $grader, 'date' => $datestr]
            );
        } else if ($grader !== '') {
            $graderlabel = '— ' . $grader;
        } else if ($datestr !== '') {
            $graderlabel = '— ' . $datestr;
        }

        return [$commenttext, $graderlabel];
    }

}
