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
 * PDF generator for local_eledia_exam2pdf using mPDF.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\pdf;

use mod_quiz\quiz_attempt;

/**
 * Generates the PDF certificate for a passed quiz attempt using mPDF.
 */
class generator {

    /** @var string Brand accent color (configurable via plugin settings). */
    private const ACCENT_DEFAULT = '#2a5d8a';

    /** @var string Soft accent background. */
    private const ACCENT_SOFT    = '#eaf1f8';

    /** @var string Success state foreground. */
    private const SUCCESS        = '#1f7a3f';

    /** @var string Success state soft background. */
    private const SUCCESS_SOFT   = '#e8f3ec';

    /** @var string Fail state foreground. */
    private const FAIL           = '#b42318';

    /** @var string Fail state soft background. */
    private const FAIL_SOFT      = '#fcebe9';

    /** @var string Partial state foreground (amber). */
    private const PARTIAL        = '#c48a00';

    /** @var string Dark ink for text on partial (amber) background. */
    private const PARTIAL_INK    = '#5a3f00';

    /** @var string Partial state soft background. */
    private const PARTIAL_SOFT   = '#fdf3e1';

    /** @var string Pending state foreground (blue). */
    private const PENDING        = '#1e6fb0';

    /** @var string Pending state soft background. */
    private const PENDING_SOFT   = '#e6f0fa';

    /** @var string Primary ink color. */
    private const INK            = '#1a1a1a';

    /** @var string Secondary ink color. */
    private const INK_SOFT       = '#4a4a4a';

    /** @var string Muted ink for labels and captions. */
    private const INK_MUTED      = '#7a7a7a';

    /** @var string Rule line color. */
    private const RULE           = '#dcdcdc';

    /** @var string Soft rule line color. */
    private const RULE_SOFT      = '#ededed';

    /**
     * Returns the effective accent colour.
     *
     * @param array $config Effective plugin config.
     * @return string Hex color string.
     */
    private static function accent_color(array $config): string {
        $c = trim((string) ($config['accentcolor'] ?? ''));
        return preg_match('/^#[0-9a-fA-F]{3,6}$/', $c) ? $c : self::ACCENT_DEFAULT;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generates PDF bytes for a single quiz attempt.
     *
     * @param quiz_attempt $attemptobj The fully initialised quiz_attempt object.
     * @param \stdClass    $quiz       The quiz DB record.
     * @param array        $config     Effective plugin config.
     * @return string Raw PDF bytes.
     */
    public static function generate(quiz_attempt $attemptobj, \stdClass $quiz, array $config): string {
        global $CFG;

        require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');
        require_once($CFG->libdir . '/gradelib.php');

        $originallanguage = self::force_pdf_language($config);
        try {
            $mpdf = self::create_mpdf_document($config);
            $html = self::build_html_document([$attemptobj], $quiz, $config);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } finally {
            self::restore_forced_language($originallanguage);
        }
    }

    /**
     * Generates one merged PDF for multiple attempts of the same quiz.
     *
     * @param int[]     $attemptids List of quiz_attempt IDs.
     * @param \stdClass $quiz       The quiz DB record.
     * @param array     $config     Effective config (global + per-quiz overrides).
     * @return string Raw PDF bytes.
     */
    public static function generate_merged(array $attemptids, \stdClass $quiz, array $config): string {
        global $CFG;

        require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attemptids = array_values(array_unique(array_map('intval', $attemptids)));
        if (empty($attemptids)) {
            return '';
        }

        $originallanguage = self::force_pdf_language($config);
        try {
            $attemptobjs = array_map(
                static fn(int $id) => \mod_quiz\quiz_attempt::create($id),
                $attemptids
            );
            $mpdf = self::create_mpdf_document($config);
            $html = self::build_html_document($attemptobjs, $quiz, $config);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } finally {
            self::restore_forced_language($originallanguage);
        }
    }

    // ── Private infrastructure ────────────────────────────────────────────────

    /**
     * Creates a configured mPDF document instance.
     *
     * @param array $config Effective plugin config.
     * @return \Mpdf\Mpdf
     */
    private static function create_mpdf_document(array $config): \Mpdf\Mpdf {
        $tempdir = rtrim(sys_get_temp_dir(), '/') . '/eledia_exam2pdf_mpdf';
        if (!is_dir($tempdir)) {
            mkdir($tempdir, 0775, true);
        }
        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_top'        => 22,
            'margin_bottom'     => 18,
            'margin_left'       => 14,
            'margin_right'      => 14,
            'margin_header'     => 6,
            'margin_footer'     => 6,
            'default_font'      => 'dejavusans',
            'default_font_size' => 9.5,
            'tempDir'           => $tempdir,
        ]);
        $mpdf->SetCreator('Moodle / eLeDia exam2pdf');
        $mpdf->SetAuthor('eLeDia GmbH');
        unset($config);
        return $mpdf;
    }

    /**
     * Builds a complete HTML document for one or more attempts.
     *
     * @param quiz_attempt[] $attemptobjs Array of attempt objects.
     * @param \stdClass      $quiz        The quiz DB record.
     * @param array          $config      Effective plugin config.
     * @return string Complete HTML document string.
     */
    private static function build_html_document(array $attemptobjs, \stdClass $quiz, array $config): string {
        $css        = self::get_pdf_css($config);
        $footertext = s((string) ($config['pdffootertext'] ?? ''));

        $footer = '<htmlpagefooter name="pgfooter">'
            . '<table width="100%" cellpadding="0" cellspacing="0"'
            . ' style="border-top:0.4pt solid #dcdcdc;padding-top:2mm;">'
            . '<tr>'
            . '<td style="font-size:7.5pt;color:#7a7a7a;text-align:left;">' . $footertext . '</td>'
            . '<td style="font-size:7.5pt;color:#7a7a7a;text-align:right;">'
            . 'Seite {PAGENO}&nbsp;/&nbsp;{nbpg}</td>'
            . '</tr></table>'
            . '</htmlpagefooter>';

        $body = $footer;
        $body .= '<sethtmlpagefooter name="pgfooter" value="on" show-this-page="1" />';
        foreach ($attemptobjs as $i => $attemptobj) {
            $body .= self::render_attempt_document($attemptobj, $quiz, $config, $i);
        }

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<style>' . $css . '</style></head>'
            . '<body>' . $body . '</body></html>';
    }

    /**
     * Returns the embedded CSS stylesheet for the PDF.
     *
     * All color values are inlined from PHP constants for maximum mPDF
     * compatibility (no CSS custom properties).
     *
     * @param array $config Effective plugin config.
     * @return string CSS text.
     */
    private static function get_pdf_css(array $config): string {
        $a = self::accent_color($config);

        return 'html,body{margin:0;padding:0;font-family:"dejavusans",sans-serif;'
            . 'font-size:9.5pt;line-height:1.42;color:#1a1a1a;}'

            . '.cover{page-break-after:always;}'

            . '.hdr-table{width:100%;border-collapse:collapse;border-spacing:0;'
            . 'border-bottom:1.2pt solid ' . $a . ';padding-bottom:4mm;margin-bottom:5mm;}'
            . '.hdr-table td{vertical-align:middle;padding:0;}'
            . '.hdr-title{text-align:center;font-size:12pt;color:' . $a . ';'
            . 'font-weight:700;letter-spacing:-0.2px;}'
            . '.hdr-meta{text-align:right;font-size:8pt;color:#7a7a7a;line-height:1.4;width:42mm;}'

            . '.hero-table{width:100%;border:0.5pt solid #dcdcdc;border-radius:1mm;'
            . 'border-spacing:0;border-collapse:collapse;margin-bottom:5mm;}'
            . '.hero-table td{vertical-align:middle;border-right:0.5pt solid #dcdcdc;padding:0;}'
            . '.hero-table td:last-child{border-right:none;}'
            . '.hcell-status{width:42mm;padding:4mm 5mm;text-align:center;background:#e8f3ec;}'
            . '.hcell-status-fail{background:#fcebe9;}'
            . '.hcell-status-pending{background:#e6f0fa;}'
            . '.hcell-icon{display:block;width:10mm;height:10mm;line-height:10mm;'
            . 'margin:0 auto 1.5mm auto;border:1pt solid #1f7a3f;border-radius:50%;'
            . 'background:white;color:#1f7a3f;font-size:12pt;font-weight:700;text-align:center;}'
            . '.hcell-icon-fail{border-color:#b42318;color:#b42318;}'
            . '.hcell-icon-pending{border-color:#1e6fb0;color:#1e6fb0;}'
            . '.hcell-badge{font-size:12pt;font-weight:700;color:#1f7a3f;'
            . 'text-transform:uppercase;letter-spacing:0.6px;line-height:1;}'
            . '.hcell-badge-fail{color:#b42318;}'
            . '.hcell-badge-pending{color:#1e6fb0;}'
            . '.hcell-sub{margin-top:1mm;font-size:7.5pt;color:#7a7a7a;'
            . 'text-transform:uppercase;letter-spacing:0.4px;}'
            . '.hcell-score{width:44mm;padding:4mm 6mm;}'
            . '.hcell-big{font-size:22pt;font-weight:300;line-height:1;color:#1a1a1a;}'
            . '.hcell-pct{font-size:11pt;color:#4a4a4a;font-weight:500;margin-left:2mm;}'
            . '.hcell-lbl{margin-top:1.5mm;font-size:7.5pt;color:#7a7a7a;'
            . 'text-transform:uppercase;letter-spacing:0.4px;}'
            . '.hcell-quiz{padding:4mm 5mm;}'
            . '.hcell-name{font-size:11pt;font-weight:700;color:#1a1a1a;'
            . 'line-height:1.2;margin-bottom:1mm;}'
            . '.hcell-ctx{font-size:8pt;color:#7a7a7a;line-height:1.35;}'

            . '.cmeta-table{width:100%;border-spacing:6mm 0;border-collapse:separate;}'
            . '.cmeta-table td{vertical-align:top;width:50%;padding:0;}'
            . '.mblock{margin-bottom:5mm;}'
            . '.mblock-hdr{font-size:7.5pt;text-transform:uppercase;letter-spacing:0.9px;'
            . 'color:' . $a . ';margin:0 0 1.5mm 0;font-weight:700;padding-bottom:0.8mm;'
            . 'border-bottom:0.5pt solid #dcdcdc;display:block;}'
            . '.mbrow-table{width:100%;border-spacing:0;border-collapse:collapse;}'
            . '.mbrow-table td{font-size:8.5pt;padding:0.8mm 0;vertical-align:top;}'
            . '.mbrow-table tr + tr td{border-top:0.3pt solid #ededed;}'
            . '.mb-lbl{color:#7a7a7a;width:40mm;}'
            . '.mb-val{color:#1a1a1a;font-weight:500;text-align:right;}'
            . '.mb-name{color:#1a1a1a;font-weight:700;font-size:10pt;'
            . 'margin:0 0 0.5mm 0;padding:0;}'
            . '.mb-sub{color:#7a7a7a;font-size:8pt;font-weight:400;'
            . 'margin:0 0 0.3mm 0;padding:0;}'

            . '.sb-table{border-spacing:1.2mm;border-collapse:separate;margin:1mm 0 0 -1.2mm;}'
            . '.sb-table td{width:9mm;height:5.5mm;border-radius:1mm;font-size:7pt;'
            . 'font-weight:700;vertical-align:middle;text-align:center;'
            . 'line-height:5.5mm;padding:0;white-space:nowrap;}'
            . '.sb-ok{background:#1f7a3f;color:white;border:0.5pt solid #1f7a3f;}'
            . '.sb-partial{background:#c48a00;color:#5a3f00;border:0.5pt solid #c48a00;}'
            . '.sb-wrong{background:white;color:#b42318;border:0.7pt solid #b42318;}'
            . '.sb-pending{background:#1e6fb0;color:white;border:0.5pt solid #1e6fb0;}'
            . '.sb-legend{margin-top:2mm;font-size:7pt;color:#7a7a7a;}'
            . '.sb-legend span{margin-right:4mm;white-space:nowrap;}'
            . '.sb-li{display:inline-block;width:3.5mm;height:3.5mm;border-radius:0.5mm;'
            . 'font-size:6pt;font-weight:700;line-height:3.5mm;text-align:center;'
            . 'vertical-align:-0.8mm;margin-right:0.8mm;}'
            . '.sli-ok{background:#1f7a3f;color:white;border:0.3pt solid #1f7a3f;}'
            . '.sli-partial{background:#c48a00;color:#5a3f00;border:0.3pt solid #c48a00;}'
            . '.sli-wrong{background:white;color:#b42318;border:0.5pt solid #b42318;}'
            . '.sli-pending{background:#1e6fb0;color:white;border:0.3pt solid #1e6fb0;}'

            . '.qs-hdr{font-size:7.5pt;text-transform:uppercase;letter-spacing:0.9px;'
            . 'color:' . $a . ';font-weight:700;padding-bottom:0.8mm;'
            . 'border-bottom:0.5pt solid #dcdcdc;margin:0 0 2.5mm 0;display:block;}'
            . '.qs-cnt{color:#7a7a7a;font-weight:500;}'
            . '.qcard{margin-bottom:3mm;padding:3mm 4mm;background:#fcfcfd;'
            . 'border:0.4pt solid #dcdcdc;border-left:1.5mm solid #1f7a3f;'
            . 'border-radius:0.8mm;}'
            . '.qcard-wrong{background:#fdf9f8;border-left:1.5mm double #b42318;}'
            . '.qcard-partial{background:#fdfaf4;border-left:1.5mm dashed #c48a00;}'
            . '.qcard-pending{background:#e6f0fa;border-left:1.5mm dotted #1e6fb0;}'
            . '.qhdr-table{width:100%;border-spacing:0;border-collapse:collapse;'
            . 'margin-bottom:2mm;}'
            . '.qhdr-table td{vertical-align:top;padding:0;}'
            . '.qnum{font-size:9.5pt;font-weight:700;color:#1a1a1a;line-height:1.3;}'
            . '.qno{color:#7a7a7a;font-weight:500;margin-right:1mm;}'
            . '.qscore{font-size:8.5pt;color:#4a4a4a;font-weight:500;'
            . 'white-space:nowrap;text-align:right;width:22mm;}'
            . '.qmark{display:inline-block;width:4mm;height:4mm;line-height:4mm;'
            . 'text-align:center;background:#1f7a3f;color:white;border-radius:50%;'
            . 'font-size:7pt;font-weight:700;margin-left:1.5mm;vertical-align:middle;}'
            . '.qmark-fail{background:#b42318;}'
            . '.qmark-partial{background:#c48a00;color:#5a3f00;}'
            . '.qmark-pending{background:#1e6fb0;}'
            . '.qprompt{font-size:8.5pt;color:#4a4a4a;margin-bottom:1.5mm;font-style:italic;}'
            . '.qans-table{width:100%;border-spacing:0;border-collapse:collapse;'
            . 'font-size:8.5pt;margin-top:1.5mm;}'
            . '.qans-lbl{color:#7a7a7a;text-transform:uppercase;letter-spacing:0.4px;'
            . 'font-size:7pt;font-weight:700;width:28mm;padding:0.5mm 3mm 0.5mm 0;'
            . 'vertical-align:top;white-space:nowrap;}'
            . '.qans-val{color:#1a1a1a;padding:0.5mm 0;}'
            . '.qans-correct{color:#1f7a3f;font-weight:500;}'
            . '.qans-wrong{color:#b42318;}'
            . '.qcomment{margin-top:2.5mm;padding:2mm 3mm;background:#eaf1f8;'
            . 'border-left:1mm solid ' . $a . ';border-radius:0.6mm;'
            . 'font-size:8pt;color:#1a1a1a;line-height:1.45;}'
            . '.qcomment-lbl{font-size:7pt;color:' . $a . ';text-transform:uppercase;'
            . 'letter-spacing:0.5px;font-weight:700;margin-bottom:0.8mm;display:block;}'
            . '.qcomment-by{color:#7a7a7a;font-weight:500;text-transform:none;'
            . 'letter-spacing:0;margin-left:1mm;}'
            . '.qcomment-body{color:#4a4a4a;}'
            . '.pending-note{margin-top:1.5mm;font-size:8pt;color:#1e6fb0;font-style:italic;}';
    }

    // ── Language helpers ──────────────────────────────────────────────────────

    /**
     * Forces the PDF rendering language.
     *
     * @param array $config Effective plugin config.
     * @return string|null Original language to restore, or null when no switch needed.
     */
    private static function force_pdf_language(array $config): ?string {
        $targetlanguage  = self::resolve_pdf_language($config);
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
        $selectedlanguage   = (string) ($config['pdflanguage'] ?? 'site');

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

    // ── Document renderer ─────────────────────────────────────────────────────

    /**
     * Renders one attempt as an HTML body fragment.
     *
     * For attemptindex=0 CSS @page :first suppresses the running header on the
     * cover page automatically. For attemptindex>0 explicit mPDF header-control
     * tags handle suppression around the forced page break.
     *
     * @param quiz_attempt $attemptobj   Fully initialised quiz_attempt object.
     * @param \stdClass    $quiz         The quiz DB record.
     * @param array        $config       Effective plugin config.
     * @param int          $attemptindex 0-based position inside the merged document.
     * @return string HTML body fragment.
     */
    private static function render_attempt_document(
        quiz_attempt $attemptobj,
        \stdClass $quiz,
        array $config,
        int $attemptindex
    ): string {
        global $DB;

        $attempt  = $attemptobj->get_attempt();
        $learner  = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);
        $course   = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'quiz',
            'iteminstance' => $quiz->id,
            'courseid'     => $quiz->course,
        ]);
        $gradepass = ($gradeitem && !empty($gradeitem->gradepass))
            ? (float) $gradeitem->gradepass : 0.0;

        $grade      = ($quiz->sumgrades > 0)
            ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;
        $percentage = ($quiz->grade > 0)
            ? round($grade / $quiz->grade * 100, 1) : 0;
        $passed     = $gradepass <= 0 || $quiz->sumgrades <= 0 || ($grade >= $gradepass);

        $duration = '';
        if ($attempt->timestart && $attempt->timefinish) {
            $duration = gmdate('H:i:s', $attempt->timefinish - $attempt->timestart);
        }

        $pendingcount = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            $state = $attemptobj->get_question_state($slot);
            if ($state->get_summary_state() === 'needsgrading') {
                $pendingcount++;
            }
        }

        $runhdrhtml = self::build_running_header_html($attemptobj, $quiz, $config);
        $body = '<htmlpageheader name="runhdr">' . $runhdrhtml . '</htmlpageheader>';

        if ($attemptindex > 0) {
            $body .= '<sethtmlpageheader name="_blank" value="on" show-this-page="0" />';
            $body .= '<pagebreak />';
        }

        $logohtml = self::get_logo_html();
        $body .= '<div class="cover">';
        $body .= self::render_cover_header_band($logohtml, $attempt, $config);
        $body .= self::render_hero(
            $quiz, $course, $passed, $pendingcount, $attempt, $grade, $percentage, $config
        );
        $body .= self::render_cover_grid(
            $learner, $quiz, $course, $attempt, $duration, $gradepass, $config, $attemptobj
        );
        $body .= '</div>';
        $body .= '<sethtmlpageheader name="runhdr" value="on" show-this-page="1" />';

        $body .= self::render_questions($attemptobj, $config);

        unset($course);
        return $body;
    }

    // ── Section renderers ─────────────────────────────────────────────────────

    /**
     * Builds running header HTML for use inside the mPDF htmlpageheader tag.
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

        $attempt     = $attemptobj->get_attempt();
        $learner     = $DB->get_record('user', ['id' => $attempt->userid], '*', IGNORE_MISSING);
        $learnername = $learner ? fullname($learner) : '';

        $grade      = ($quiz->sumgrades > 0)
            ? ($attempt->sumgrades / $quiz->sumgrades * $quiz->grade) : 0;
        $percentage = ($quiz->grade > 0) ? round($grade / $quiz->grade * 100, 1) : 0;
        $scoretext  = format_float($grade, 1) . ' / ' . format_float((float) $quiz->grade, 1)
            . ' Â· ' . format_float($percentage, 1) . ' %';

        $attemptno  = (string) ($attempt->attempt ?? '');
        $datestr    = $attempt->timefinish
            ? userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'))
            : '';
        $midparts   = array_filter([
            s($quiz->name),
            $attemptno !== ''
                ? s(get_string('pdf_attempt_hash', 'local_eledia_exam2pdf', $attemptno))
                : '',
            s($datestr),
        ], static fn($v) => $v !== '');

        unset($config);
        return '<table width="100%" cellpadding="0" cellspacing="0"'
            . ' style="font-size:7.5pt;color:#7a7a7a;'
            . 'border-bottom:0.4pt solid #dcdcdc;padding-bottom:2mm;">'
            . '<tr>'
            . '<td style="width:33%;text-align:left;color:#1a1a1a;font-weight:600;">'
            . s($learnername) . '</td>'
            . '<td style="width:40%;text-align:center;">' . implode(' Â· ', $midparts) . '</td>'
            . '<td style="width:27%;text-align:right;">' . s($scoretext) . '</td>'
            . '</tr></table>';
    }

    /**
     * Fetches the site logo and returns an HTML img element (base64 data-URI).
     *
     * @return string HTML img element, or empty string when no logo is configured.
     */
    private static function get_logo_html(): string {
        try {
            $syscontext = \core\context\system::instance();
            $fs         = get_file_storage();
            foreach (['logo', 'logocompact'] as $area) {
                $files = $fs->get_area_files(
                    $syscontext->id, 'core_admin', $area, 0, 'filename', false
                );
                if (!empty($files)) {
                    $file = reset($files);
                    $src  = 'data:' . $file->get_mimetype() . ';base64,'
                        . base64_encode($file->get_content());
                    return '<img src="' . $src . '" style="height:24px;width:auto;margin-top:-4px;" />';
                }
            }
        } catch (\Throwable $e) {
            debugging('exam2pdf: logo fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return '';
    }

    /**
     * Renders the cover header band: logo | title | attempt info.
     *
     * @param string    $logohtml Optional logo img HTML (may be empty).
     * @param \stdClass $attempt  The quiz_attempts DB record.
     * @param array     $config   Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_cover_header_band(
        string $logohtml,
        \stdClass $attempt,
        array $config
    ): string {
        $attemptno  = (string) ($attempt->attempt ?? '');
        $datestr    = $attempt->timefinish
            ? userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'))
            : '';
        $rightparts = array_filter([
            $attemptno !== ''
                ? s(get_string('pdf_attempt_hash', 'local_eledia_exam2pdf', $attemptno))
                : '',
            s($datestr),
        ], static fn($v) => $v !== '');

        unset($config);
        return '<table class="hdr-table" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="width:33%;">' . $logohtml . '</td>'
            . '<td class="hdr-title">'
            . s(get_string('pdf_cover_title', 'local_eledia_exam2pdf'))
            . '</td>'
            . '<td class="hdr-meta">' . implode('<br>', $rightparts) . '</td>'
            . '</tr></table>';
    }

    /**
     * Renders the hero block: status icon | score | quiz context.
     *
     * @param \stdClass $quiz         The quiz DB record.
     * @param \stdClass $course       The course DB record.
     * @param bool      $passed       Whether the attempt passed per gradebook threshold.
     * @param int       $pendingcount Number of slots awaiting manual grading.
     * @param \stdClass $attempt      The quiz_attempts DB record.
     * @param float     $grade        Effective attempt grade (scaled to quiz->grade).
     * @param float     $percentage   Percentage of maximum, 0-100.
     * @param array     $config       Effective plugin config.
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
        if ($pendingcount > 0) {
            $statusclass = 'hcell-status hcell-status-pending';
            $iconclass   = 'hcell-icon hcell-icon-pending';
            $badgeclass  = 'hcell-badge hcell-badge-pending';
            $iconchar    = '?';
            $badge       = s(get_string('pdf_status_pending', 'local_eledia_exam2pdf'));
            $sub         = s(get_string('pdf_pending_questions', 'local_eledia_exam2pdf', $pendingcount));
        } else if ($passed) {
            $statusclass = 'hcell-status';
            $iconclass   = 'hcell-icon';
            $badgeclass  = 'hcell-badge';
            $iconchar    = '&#10003;';
            $badge       = s(get_string('pdf_status_passed', 'local_eledia_exam2pdf'));
            $sub         = s(get_string('pdf_status_label', 'local_eledia_exam2pdf'));
        } else {
            $statusclass = 'hcell-status hcell-status-fail';
            $iconclass   = 'hcell-icon hcell-icon-fail';
            $badgeclass  = 'hcell-badge hcell-badge-fail';
            $iconchar    = '&#10007;';
            $badge       = s(get_string('pdf_status_failed', 'local_eledia_exam2pdf'));
            $sub         = s(get_string('pdf_status_label', 'local_eledia_exam2pdf'));
        }

        $scorestr   = format_float($grade, 1) . '&nbsp;/&nbsp;' . format_float((float) $quiz->grade, 1);
        $pctstr     = format_float($percentage, 1) . '&nbsp;%';
        $quizname   = s($quiz->name);
        $coursedisp = s(get_string('course') . ': ' . $course->fullname);
        $scorelbl   = strtoupper(s(get_string('pdf_score_points_label', 'local_eledia_exam2pdf')));
        $quizlbl    = strtoupper(s(get_string('pdf_context_block', 'local_eledia_exam2pdf')));

        $completedstr = '';
        if ($attempt->timefinish) {
            $completedstr = s(
                get_string('pdf_timestamp', 'local_eledia_exam2pdf') . ': '
                . userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'core_langconfig'))
            );
        }

        unset($config);
        $html  = '<table class="hero-table" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td class="' . $statusclass . '">'
            . '<span class="' . $iconclass . '">' . $iconchar . '</span>'
            . '<div class="' . $badgeclass . '">' . $badge . '</div>'
            . '<div class="hcell-sub">' . $sub . '</div>'
            . '</td>';
        $html .= '<td class="hcell-score">'
            . '<div class="hcell-big">' . $scorestr
            . '<span class="hcell-pct">&nbsp;&middot;&nbsp;' . $pctstr . '</span></div>'
            . '<div class="hcell-lbl">' . $scorelbl . '</div>'
            . '</td>';
        $html .= '<td class="hcell-quiz">'
            . '<div class="hcell-name">' . $quizname . '</div>'
            . '<div class="hcell-ctx">' . $coursedisp
            . ($completedstr !== '' ? '<br>' . $completedstr : '')
            . '</div>'
            . '</td>';
        $html .= '</tr></table>';
        return $html;
    }

    /**
     * Renders the two-column cover grid.
     *
     * Left: participant block and attempt meta rows.
     * Right: question navigation dot grid.
     *
     * @param \stdClass    $learner    User DB record.
     * @param \stdClass    $quiz       Quiz DB record.
     * @param \stdClass    $course     Course DB record (unused; kept for signature symmetry).
     * @param \stdClass    $attempt    Quiz attempt record.
     * @param string       $duration   Pre-formatted duration (HH:MM:SS) or empty.
     * @param float        $gradepass  Gradebook pass threshold (0 = none configured).
     * @param array        $config     Effective plugin config.
     * @param quiz_attempt $attemptobj Attempt object for navigation grid.
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
        $html = '<table class="cmeta-table" cellpadding="0" cellspacing="0"><tr>';

        $html .= '<td style="padding-right:0;">';
        $html .= '<div class="mblock">';
        $html .= '<span class="mblock-hdr">'
            . s(get_string('pdf_participant_block', 'local_eledia_exam2pdf')) . '</span>';
        $html .= '<table class="mbrow-table" cellpadding="0" cellspacing="0">'
            . '<tr><td colspan="2">'
            . '<p class="mb-name">' . s(fullname($learner)) . '</p>';
        if (!empty($learner->email)) {
            $html .= '<p class="mb-sub">' . s($learner->email) . '</p>';
        }
        $html .= '<p class="mb-sub">'
            . s(get_string('pdf_moodleid', 'local_eledia_exam2pdf') . ': ' . (int) $learner->id)
            . '</p></td></tr></table>';
        $html .= '</div>';

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
                format_float(
                    (float) ($attempt->sumgrades ?? 0) / max(1, (float) $quiz->sumgrades) * 100,
                    1
                ) . ' %'
            );
        }
        if ($attemptrows !== '') {
            $html .= '<div class="mblock">';
            $html .= '<span class="mblock-hdr">'
                . s(get_string('pdf_attempt_block', 'local_eledia_exam2pdf')) . '</span>';
            $html .= '<table class="mbrow-table" cellpadding="0" cellspacing="0">'
                . $attemptrows . '</table>';
            $html .= '</div>';
        }
        $html .= '</td>';

        $html .= '<td style="padding-left:0;">';
        $html .= self::render_navigation_dots($attemptobj, $config);
        $html .= '</td>';

        $html .= '</tr></table>';
        unset($course);
        return $html;
    }

    /**
     * Renders a single label/value meta row.
     *
     * @param string $label Row label.
     * @param mixed  $value Row value.
     * @return string HTML tr element.
     */
    private static function render_meta_row(string $label, $value): string {
        return '<tr>'
            . '<td class="mb-lbl">' . s($label) . '</td>'
            . '<td class="mb-val">' . s((string) $value) . '</td>'
            . '</tr>';
    }

    /**
     * Renders the question navigation dot grid plus legend.
     *
     * @param quiz_attempt $attemptobj Attempt object.
     * @param array        $config     Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_navigation_dots(quiz_attempt $attemptobj, array $config): string {
        $slots = $attemptobj->get_slots();
        if (empty($slots)) {
            return '';
        }

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

        $navtitle = s(get_string('pdf_navigation_heading', 'local_eledia_exam2pdf'));
        $navcount = s(get_string(
            'pdf_nav_legend_all', 'local_eledia_exam2pdf', (string) count($slots)
        ));

        $html  = '<div class="mblock">';
        $html .= '<span class="mblock-hdr">' . $navtitle . ' (' . $navcount . ')</span>';

        $slotcount = count($slots);
        $perrow    = min($slotcount, 7);
        $html .= '<table class="sb-table" cellpadding="0" cellspacing="0">';
        $i = 0;
        foreach ($slots as $slot) {
            if ($i % $perrow === 0) {
                if ($i > 0) {
                    $html .= '</tr>';
                }
                $html .= '<tr>';
            }
            [$cssclass, $symbol] = self::resolve_navigation_badge_style(
                $attemptobj->get_question_state($slot)
            );
            $num   = s($attemptobj->get_question_number($slot));
            $html .= '<td class="' . $cssclass . '">'
                . '<span style="padding-right:0.5mm;">' . $num . '</span>'
                . '<span>' . $symbol . '</span>'
                . '</td>';
            $i++;
        }
        if ($i > 0) {
            $remaining = ($perrow - ($i % $perrow)) % $perrow;
            for ($p = 0; $p < $remaining; $p++) {
                $html .= '<td>&nbsp;</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<div class="sb-legend">';
        $html .= '<span><i class="sb-li sli-ok">&#10003;</i>'
            . s(get_string('pdf_nav_legend_correct', 'local_eledia_exam2pdf', (string) $counts['correct']))
            . '</span>';
        $html .= '<span><i class="sb-li sli-partial">?</i>'
            . s(get_string('pdf_nav_legend_partial', 'local_eledia_exam2pdf', (string) $counts['partial']))
            . '</span>';
        $html .= '<span><i class="sb-li sli-wrong">&#10007;</i>'
            . s(get_string('pdf_nav_legend_wrong', 'local_eledia_exam2pdf', (string) $counts['wrong']))
            . '</span>';
        $html .= '<span><i class="sb-li sli-pending">&nbsp;</i>'
            . s(get_string('pdf_nav_legend_pending', 'local_eledia_exam2pdf', (string) $counts['pending']))
            . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        unset($config);
        return $html;
    }

    /**
     * Resolves navigation badge CSS class and Unicode symbol for one question state.
     *
     * @param \question_state $state Question state object.
     * @return array{0: string, 1: string} [css_class, symbol_html].
     */
    private static function resolve_navigation_badge_style(\question_state $state): array {
        if ($state->get_summary_state() === 'needsgrading') {
            return ['sb-pending', '&nbsp;'];
        }
        if ($state->is_correct()) {
            return ['sb-ok', '&#10003;'];
        }
        if ($state->is_partially_correct()) {
            return ['sb-partial', '?'];
        }
        if ($state->is_incorrect()) {
            return ['sb-wrong', '&#10007;'];
        }
        return ['sb-wrong', '&#183;'];
    }

    /**
     * Renders the questions and answers section.
     *
     * @param quiz_attempt $attemptobj Attempt object.
     * @param array        $config     Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_questions(quiz_attempt $attemptobj, array $config): string {
        $quba  = \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid());
        $slots = $attemptobj->get_slots();
        if (empty($slots)) {
            return '';
        }

        $accent = self::accent_color($config);

        $heading      = mb_strtoupper(
            (string) get_string(
                'pdf_questions_section_heading', 'local_eledia_exam2pdf', (string) count($slots)
            ),
            'UTF-8'
        );
        $headingparts = explode(' Â· ', $heading, 2);
        $headingmain  = s($headingparts[0]);
        $headingsub   = isset($headingparts[1]) ? s($headingparts[1]) : '';

        $html  = '<div class="qs-hdr">' . $headingmain;
        if ($headingsub !== '') {
            $html .= ' <span class="qs-cnt">Â· ' . $headingsub . '</span>';
        }
        $html .= '</div>';

        $num = 1;
        foreach ($slots as $slot) {
            $qa       = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $qtype    = $question->get_type_name();
            $state    = $qa->get_state();
            $html .= self::render_question_card($qa, $question, $qtype, $state, $num, $config, $accent);
            $num++;
        }

        return $html;
    }

    /**
     * Renders one question as a styled card with left-border state indicator.
     *
     * @param \question_attempt    $qa       Question attempt wrapper.
     * @param \question_definition $question Question definition.
     * @param string               $qtype    Question type (e.g. 'multichoice').
     * @param \question_state      $state    Question state.
     * @param int                  $num      1-based visible question number.
     * @param array                $config   Effective plugin config.
     * @param string               $accent   Resolved accent color.
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
        [$cardclass, $markclass, $markchar] = self::resolve_question_card_style($state);
        $ispending = ($state->get_summary_state() === 'needsgrading');

        $qtext     = trim(strip_tags($question->questiontext));
        $mark      = $qa->get_mark();
        $maxmark   = $qa->get_max_mark();
        $scoretext = self::format_question_mark($mark) . ' / ' . self::format_question_mark($maxmark);

        $html  = '<div class="qcard ' . $cardclass . '">';
        $html .= '<table class="qhdr-table" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td class="qnum">'
            . '<span class="qno">'
            . s(get_string('pdf_question', 'local_eledia_exam2pdf')) . ' ' . $num
            . '</span> '
            . s($qtext)
            . '</td>';
        $html .= '<td class="qscore">'
            . s($scoretext)
            . ' <span class="qmark ' . $markclass . '">' . $markchar . '</span>'
            . '</td>';
        $html .= '</tr></table>';

        $hint = self::resolve_qtype_hint($qtype, $state);
        if ($hint !== '') {
            $html .= '<div class="qprompt">' . s($hint) . '</div>';
        }

        $response      = $qa->get_response_summary();
        $answertext    = ($response !== null && $response !== '') ? (string) $response : '';
        $displayanswer = self::decorate_answer_value($answertext, $state);

        $html .= '<table class="qans-table" cellpadding="0" cellspacing="0">';
        $html .= self::render_q_arow(
            get_string('pdf_youranswer', 'local_eledia_exam2pdf'),
            $displayanswer
        );
        if (!empty($config['showcorrectanswers']) && $qtype !== 'essay') {
            $correct = self::get_correct_answer_text($question, $qtype);
            if ($correct === '') {
                $correct = get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf');
            }
            $solvalue = '<span class="qans-correct">&#10003;&nbsp;' . s($correct) . '</span>';
            $html .= self::render_q_arow(
                get_string('pdf_correctanswer', 'local_eledia_exam2pdf'),
                $solvalue
            );
        }
        $html .= '</table>';

        if ($ispending) {
            $html .= '<div class="pending-note">'
                . s(get_string('pdf_pending_note', 'local_eledia_exam2pdf'))
                . '</div>';
        }

        if (!empty($config['showquestioncomments'])) {
            [$commenttext, $graderlabel] = self::get_manual_comment_meta($qa);
            if ($commenttext !== '') {
                $html .= self::render_grading_comment_block($commenttext, $graderlabel, $accent);
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Resolves question card CSS class, mark pill class, and mark character.
     *
     * @param \question_state $state Question state.
     * @return array{0: string, 1: string, 2: string} [card_class, mark_class, mark_char].
     */
    private static function resolve_question_card_style(\question_state $state): array {
        if ($state->get_summary_state() === 'needsgrading') {
            return ['qcard-pending', 'qmark-pending', '&nbsp;'];
        }
        if ($state->is_correct()) {
            return ['', '', '&#10003;'];
        }
        if ($state->is_partially_correct()) {
            return ['qcard-partial', 'qmark-partial', '?'];
        }
        if ($state->is_incorrect()) {
            return ['qcard-wrong', 'qmark-fail', '&#10007;'];
        }
        return ['', '', '&nbsp;'];
    }

    /**
     * Decorates the learner's answer with state-appropriate color markup.
     *
     * @param string          $raw   Raw response summary (may be empty).
     * @param \question_state $state Question state.
     * @return string HTML fragment (pre-escaped).
     */
    private static function decorate_answer_value(string $raw, \question_state $state): string {
        if ($raw === '') {
            return '<span style="color:#7a7a7a;font-style:italic;">'
                . s(get_string('pdf_noanswer', 'local_eledia_exam2pdf'))
                . '</span>';
        }
        $escaped = s($raw);
        if ($state->is_correct()) {
            return '<span class="qans-correct">&#10003;&nbsp;' . $escaped . '</span>';
        }
        if ($state->is_partially_correct()) {
            return '<span style="color:#c48a00;">' . $escaped . '</span>';
        }
        if ($state->is_incorrect()) {
            return '<span class="qans-wrong">&#10007;&nbsp;' . $escaped . '</span>';
        }
        return '<span>' . $escaped . '</span>';
    }

    /**
     * Renders one answer row inside a question card answers table.
     *
     * The $value parameter is treated as pre-formatted HTML; callers must
     * escape plain text via s() before passing it in.
     *
     * @param string $label Row label string.
     * @param string $value Pre-escaped HTML value.
     * @return string HTML tr element.
     */
    private static function render_q_arow(string $label, string $value): string {
        return '<tr>'
            . '<td class="qans-lbl">' . s($label) . '</td>'
            . '<td class="qans-val">' . $value . '</td>'
            . '</tr>';
    }

    /**
     * Renders an accent-colored grading-comment block.
     *
     * @param string $comment     Plain-text grading comment.
     * @param string $graderlabel Pre-formatted "Grader, Datum" suffix (may be empty).
     * @param string $accent      Resolved accent color (passed for future extensibility).
     * @return string HTML markup.
     */
    private static function render_grading_comment_block(
        string $comment,
        string $graderlabel,
        string $accent
    ): string {
        $html  = '<div class="qcomment">';
        $html .= '<span class="qcomment-lbl">'
            . s(get_string('pdf_comment_label', 'local_eledia_exam2pdf'));
        if ($graderlabel !== '') {
            $html .= ' <span class="qcomment-by">' . s($graderlabel) . '</span>';
        }
        $html .= '</span>';
        $html .= '<div class="qcomment-body">' . s($comment) . '</div>';
        $html .= '</div>';
        unset($accent);
        return $html;
    }

    /**
     * Resolves the italic hint shown below a question title.
     *
     * @param string          $qtype Question type (e.g. 'multichoice').
     * @param \question_state $state Question state.
     * @return string Plain hint text (empty = no hint applies).
     */
    private static function resolve_qtype_hint(string $qtype, \question_state $state): string {
        $key = match ($qtype) {
            'multichoice' => 'pdf_qtype_hint_multichoice_single',
            'truefalse'   => 'pdf_qtype_hint_truefalse',
            'shortanswer' => 'pdf_qtype_hint_shortanswer',
            'numerical'   => 'pdf_qtype_hint_numerical',
            'essay'       => ($state->get_summary_state() === 'needsgrading')
                                 ? 'pdf_qtype_hint_essay_pending'
                                 : 'pdf_qtype_hint_essay',
            default       => null,
        };
        return $key !== null ? (string) get_string($key, 'local_eledia_exam2pdf') : '';
    }

    /**
     * Extracts a human-readable correct answer string for common question types.
     *
     * @param \question_definition $question The question definition.
     * @param string               $qtype    The qtype name.
     * @return string Correct answer text, or empty string when not determinable.
     */
    private static function get_correct_answer_text(\question_definition $question, string $qtype): string {
        if ($qtype === 'truefalse') {
            if (property_exists($question, 'rightanswer') && isset($question->rightanswer)) {
                return $question->rightanswer
                    ? get_string('true', 'qtype_truefalse')
                    : get_string('false', 'qtype_truefalse');
            }
            return '';
        }

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
     * @param float|null $mark Mark value from a question attempt.
     * @return string Formatted mark string.
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
     * Returns the newest manual grading comment and a formatted grader label.
     *
     * @param \question_attempt $qa Question attempt.
     * @return array{0: string, 1: string} [comment_text, grader_label].
     *         Both strings are plain text; callers must escape them on render.
     */
    private static function get_manual_comment_meta(\question_attempt $qa): array {
        global $DB;

        $commenttext = '';
        $grader      = '';
        $datestr     = '';

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
            $graderlabel = 'â ' . $grader;
        } else if ($datestr !== '') {
            $graderlabel = 'â ' . $datestr;
        }

        return [$commenttext, $graderlabel];
    }
}
