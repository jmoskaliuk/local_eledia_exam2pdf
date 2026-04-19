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
            'margin_top'        => 28,
            'margin_bottom'     => 15,
            'margin_left'       => 12,
            'margin_right'      => 12,
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

        $css = 'html,body{margin:0;padding:0;font-family:"dejavusans",sans-serif;'
             . 'font-size:9.5pt;line-height:1.42;color:#1a1a1a;}'

             . '.hdr-table{width:100%;border-collapse:collapse;border-bottom:1.2pt solid ' . $a . ';'
             . 'padding-bottom:4mm;margin-bottom:6mm;}'
             . '.hdr-table td{vertical-align:middle;padding:0;}'
             . '.hdr-title{text-align:center;font-size:12pt;color:' . $a . ';font-weight:600;letter-spacing:-0.2px;}'
             . '.hdr-meta{text-align:right;font-size:8pt;color:#7a7a7a;line-height:1.4;width:40mm;}'
             . '.hdr-meta strong{color:#1a1a1a;font-size:9pt;font-weight:600;text-transform:uppercase;display:block;}'

             . '.hero-table{width:100%;border-spacing:0;border-collapse:collapse;margin-bottom:6mm;}'
             . '.hero-table td{vertical-align:middle;padding:0;}'

             . '.hcell-status{width:32mm;padding:5mm 3mm;text-align:center;background:#e8f3ec;border-radius:1.5mm;}'
             . '.hcell-status-fail{background:#fcebe9;}'
             . '.hcell-status-pending{background:#e6f0fa;}'
             . '.hcell-icon{display:block;text-align:center;margin:0 auto 2mm;'
             . 'color:#1f7a3f;font-size:18pt;font-weight:700;line-height:1;}'
             . '.hcell-icon-fail{color:#b42318;}'
             . '.hcell-icon-pending{color:#1e6fb0;}'
             . '.hcell-badge{font-size:12pt;font-weight:700;color:#1f7a3f;text-transform:uppercase;letter-spacing:0.6px;}'
             . '.hcell-badge-fail{color:#b42318;}'
             . '.hcell-badge-pending{color:#1e6fb0;}'
             . '.hcell-sub{margin-top:1mm;font-size:7.5pt;color:#7a7a7a;text-transform:uppercase;letter-spacing:0.4px;}'

             . '.hcell-score{padding:7mm 0 7mm 0;}'
             . '.hcell-big{font-size:22pt;font-weight:300;line-height:1;color:#1a1a1a;}'
             . '.hcell-lbl{margin-top:2mm;font-size:7.5pt;color:#7a7a7a;text-transform:uppercase;letter-spacing:0.4px;}'
             . '.hcell-lbl-pct{text-transform:none;color:#4a4a4a;font-weight:500;margin-left:1.2mm;letter-spacing:0;}'

             . '.hero-intro{margin-bottom:3mm;}'
             . '.hero-intro-name{font-size:12pt;font-weight:600;color:#1a1a1a;line-height:1.3;}'
             . '.hero-intro-ctx{font-size:9pt;color:#7a7a7a;line-height:1.4;margin-top:1mm;}'

             . '.cmeta-table{width:100%;border-spacing:5mm 0;border-collapse:separate;margin-left:-5mm;margin-right:-5mm;}'
             . '.cmeta-table td{vertical-align:top;width:50%;padding:0;}'

             . '.mblock{margin-bottom:5mm;}'
             . '.mblock-hdr{font-size:7.5pt;text-transform:uppercase;letter-spacing:0.9px;color:' . $a . ';'
             . 'margin:0 0 2mm 0;font-weight:700;padding-bottom:1mm;border-bottom:0.5pt solid #dcdcdc;display:block;}'

             . '.mb-line{font-size:9pt;margin:0 0 1.4mm 0;color:#1a1a1a;line-height:1.45;}'
             . '.mb-line-lbl{color:#7a7a7a;margin-right:2mm;}'

             . '.mb-stack .mb-val{text-align:left;display:block;padding-bottom:0.5mm;}'
             . '.mb-name{color:#1a1a1a;font-weight:700;font-size:9pt;margin:0 0 0.5mm 0;}'
             . '.mb-sub{color:#7a7a7a;font-size:8pt;font-weight:400;margin:0;}'

             . '.sb-table{border-spacing:1.2mm;border-collapse:separate;margin:1mm 0 0 -1.2mm;}'
             . '.sb-table td{width:9mm;height:5.5mm;border-radius:1mm;font-size:7.5pt;font-weight:700;'
             . 'vertical-align:middle;text-align:center;line-height:1;padding:0;}'
             . '.sb-ok{background:#1f7a3f;color:white;border:0.5pt solid #1f7a3f;}'
             . '.sb-partial{background:#c48a00;color:#5a3f00;border:0.5pt solid #c48a00;}'
             . '.sb-wrong{background:white;color:#b42318;border:0.7pt solid #b42318;}'
             . '.sb-pending{background:#1e6fb0;color:white;border:0.5pt solid #1e6fb0;}'

             . '.sb-legend-tbl{margin-top:4mm;margin-bottom:1.5mm;'
             . 'border-spacing:0;border-collapse:separate;font-size:8.5pt;color:#4a4a4a;}'
             . '.sb-legend-tbl td{padding:1.3mm 0;vertical-align:middle;white-space:nowrap;}'
             . '.sb-legend-tbl td.sb-legend-icon{padding-right:3mm;width:6mm;}'
             . '.sb-legend-tbl td.sb-legend-lbl{padding-right:7mm;}'
             . '.sb-li{display:inline-block;width:4.5mm;height:4.5mm;font-size:10pt;'
             . 'font-weight:700;line-height:4.5mm;text-align:center;}'
             . '.sli-ok{color:#1f7a3f;}'
             . '.sli-partial{color:#c48a00;}'
             . '.sli-wrong{color:#b42318;}'
             . '.sli-pending{color:#1e6fb0;}'

             . '.qs-hdr{font-size:10pt;text-transform:uppercase;letter-spacing:0.9px;color:' . $a . ';'
             . 'margin:10mm 0 4mm 0;font-weight:700;padding-bottom:1.5mm;border-bottom:0.5pt solid #dcdcdc;display:block;}'
             . '.qs-cnt{color:#7a7a7a;font-weight:500;}'

             . '.qcard{margin-bottom:3mm;padding:3mm 4mm;background:#fcfcfd;border:0.4pt solid #dcdcdc;'
             . 'border-left:2mm solid #1f7a3f;page-break-inside:avoid;}'
             . '.qcard-wrong{background:#fdf9f8;border-left-color:#b42318;}'
             . '.qcard-partial{background:#fdfaf4;border-left-color:#c48a00;}'
             . '.qcard-pending{background:#e6f0fa;border-left-color:#1e6fb0;}'

             . '.qhdr-table{width:100%;border-spacing:0;border-collapse:collapse;margin-bottom:2mm;}'
             . '.qhdr-table td{vertical-align:top;padding:0;}'
             . '.qnum{font-size:9.5pt;font-weight:600;color:#1a1a1a;line-height:1.3;}'
             . '.qno{color:#7a7a7a;font-weight:500;margin-right:1mm;}'
             . '.qscore{font-size:9.5pt;color:#1a1a1a;font-weight:600;white-space:nowrap;text-align:right;width:22mm;}'
             . '.qmark{color:#1f7a3f;font-size:12pt;font-weight:700;margin-left:2mm;vertical-align:middle;}'
             . '.qmark-fail{color:#b42318;}'
             . '.qmark-partial{color:#c48a00;}'
             . '.qmark-pending{color:#1e6fb0;}'

             . '.qprompt{font-size:8.5pt;color:#4a4a4a;margin-bottom:1.5mm;font-style:italic;}'

             . '.qans-table{width:100%;border-spacing:0;border-collapse:collapse;font-size:8.5pt;}'
             . '.qans-lbl{color:#7a7a7a;text-transform:uppercase;letter-spacing:0.4px;font-size:7pt;font-weight:600;'
             . 'width:22mm;padding:0.5mm 3mm 0.5mm 0;vertical-align:top;}'
             . '.qans-val{color:#1a1a1a;padding:0.5mm 0;}'
             . '.qans-correct{color:#1f7a3f;font-weight:500;}'
             . '.qans-correct::before{content:"\2713\00a0";font-weight:700;}'
             . '.qans-wrong{color:#b42318;}'
             . '.qans-wrong::before{content:"\2717\00a0";font-weight:700;}'

             . '.qcomment{margin-top:2.5mm;padding:2mm 3mm;background:#eff6ff;border-left:1mm solid ' . $a . ';'
             . 'border-radius:0.6mm;font-size:8pt;color:#1a1a1a;line-height:1.45;}'
             . '.qcomment-lbl{font-size:7pt;color:' . $a . ';text-transform:uppercase;letter-spacing:0.5px;font-weight:700;margin-bottom:0.8mm;display:block;}'
             . '.qcomment-by{color:#7a7a7a;font-weight:500;text-transform:none;letter-spacing:0;margin-left:1mm;}'
             . '.qcomment-body{color:#4a4a4a;}'
             . '.pending-note{margin-top:1.5mm;font-size:8pt;color:#1e6fb0;font-style:italic;}';

        return $css;
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
            $quiz,
            $course,
            $passed,
            $pendingcount,
            $attempt,
            $grade,
            $percentage,
            $config
        );
        $body .= self::render_cover_grid(
            $learner,
            $quiz,
            $course,
            $attempt,
            $duration,
            $gradepass,
            $config,
            $attemptobj
        );
        $body .= '</div>';
        $body .= '<sethtmlpageheader name="runhdr" value="on" show-this-page="1" />';

        // Render questions with specific page-break logic:
        // Q1 follows the cover grid on page 1. Q2+ force a page break.
        $body .= '<div class="questions-section">';
        $body .= self::render_questions_heading($attemptobj);
        foreach ($attemptobj->get_slots() as $index => $slot) {
            $body .= self::render_single_question_slot($attemptobj, $slot, $index + 1, $config);
        }
        $body .= '</div>';

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

        $attemptno = (string) ($attempt->attempt ?? '');
        $rightparts = array_filter([
            s($quiz->name),
            $attemptno !== ''
                ? s(get_string('pdf_attempt_hash', 'local_eledia_exam2pdf', $attemptno))
                : '',
        ], static fn($v) => $v !== '');

        unset($config);
        return '<table width="100%" cellpadding="0" cellspacing="0"'
            . ' style="font-size:7.5pt;color:#7a7a7a;'
            . 'border-bottom:0.4pt solid #dcdcdc;padding-bottom:2mm;">'
            . '<tr>'
            . '<td style="width:40%;text-align:left;color:#1a1a1a;font-weight:600;">'
            . s($learnername) . '</td>'
            . '<td style="width:60%;text-align:right;">' . implode(' · ', $rightparts) . '</td>'
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
                    $syscontext->id,
                    'core_admin',
                    $area,
                    0,
                    'filename',
                    false
                );
                if (!empty($files)) {
                    $file = reset($files);
                    $content = $file->get_content();
                    if (!empty($content)) {
                        $src = 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($content);
                        return '<img src="' . $src . '" style="height:32px;width:auto;margin-top:-6px;" />';
                    }
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
        $datetimestr = $attempt->timefinish
            ? s(userdate($attempt->timefinish, '%d.%m.%Y %H:%M'))
            : '';

        unset($config);
        return '<table class="hdr-table" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="width:40mm;">' . $logohtml . '</td>'
            . '<td class="hdr-title">' . s(get_string('pdf_cover_title', 'local_eledia_exam2pdf')) . '</td>'
            . '<td class="hdr-meta">' . $datetimestr . '</td>'
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
            $iconchar    = '&#8987;';
            $badge       = s(get_string('pdf_status_pending', 'local_eledia_exam2pdf'));
        } else if ($passed) {
            $statusclass = 'hcell-status';
            $iconclass   = 'hcell-icon';
            $badgeclass  = 'hcell-badge';
            $iconchar    = '&#10003;';
            $badge       = s(get_string('pdf_status_passed', 'local_eledia_exam2pdf'));
        } else {
            $statusclass = 'hcell-status hcell-status-fail';
            $iconclass   = 'hcell-icon hcell-icon-fail';
            $badgeclass  = 'hcell-badge hcell-badge-fail';
            $iconchar    = '&#10007;';
            $badge       = s(get_string('pdf_status_failed', 'local_eledia_exam2pdf'));
        }

        $scorestr   = format_float($grade, 1) . '&nbsp;/&nbsp;' . format_float((float) $quiz->grade, 1);
        $pctstr     = format_float($percentage, 1) . '&nbsp;%';
        $quizname   = s($quiz->name);
        $coursedisp = s($course->fullname);
        $scorelbl   = strtoupper(s(get_string('pdf_score_points_label', 'local_eledia_exam2pdf')));

        unset($attempt, $config);
        $html  = '<div class="hero-intro">';
        $html .= '<div class="hero-intro-name">' . $quizname . '</div>';
        $html .= '<div class="hero-intro-ctx">'
            . s(get_string('course')) . ': ' . $coursedisp . '</div>';
        $html .= '</div>';

        $html .= '<table class="hero-table" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td class="' . $statusclass . '">'
            . '<span class="' . $iconclass . '">' . $iconchar . '</span>'
            . '<div class="' . $badgeclass . '">' . $badge . '</div>'
            . '</td>';
        $html .= '<td style="width:12mm;">&nbsp;</td>';
        $html .= '<td class="hcell-score">'
            . '<div class="hcell-big" style="font-variant-numeric: tabular-nums;">' . $scorestr . '</div>'
            . '<div class="hcell-lbl">' . $scorelbl
            . '&nbsp;<span class="hcell-lbl-pct">(' . $pctstr . ')</span></div>'
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
            $html .= '<div style="height:12mm;line-height:12mm;">&nbsp;</div>';
            $html .= '<div class="mblock">';
            $html .= '<span class="mblock-hdr">'
                . s(get_string('pdf_attempt_block', 'local_eledia_exam2pdf')) . '</span>';
            $html .= $attemptrows;
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
        return '<p class="mb-line">'
            . '<span class="mb-line-lbl">' . s($label) . '</span>&nbsp;'
            . s((string) $value)
            . '</p>';
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
            'pdf_nav_legend_all',
            'local_eledia_exam2pdf',
            (string) count($slots)
        ));

        $html  = '<div class="mblock">';
        $html .= '<span class="mblock-hdr">' . $navtitle . ' (' . $navcount . ')</span>';

        $slotcount = count($slots);
        $perrow    = min($slotcount, 14);
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
            $html .= '<td class="' . $cssclass . '" style="font-variant-numeric: tabular-nums;">'
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

        $lblcorrect = s(get_string('pdf_nav_legend_correct', 'local_eledia_exam2pdf', (string) $counts['correct']));
        $lblwrong   = s(get_string('pdf_nav_legend_wrong', 'local_eledia_exam2pdf', (string) $counts['wrong']));
        $lblpartial = s(get_string('pdf_nav_legend_partial', 'local_eledia_exam2pdf', (string) $counts['partial']));
        $lblpending = s(get_string('pdf_nav_legend_pending', 'local_eledia_exam2pdf', (string) $counts['pending']));
        $html .= '<table class="sb-legend-tbl" cellpadding="0" cellspacing="0">';
        $html .= '<tr>'
            . '<td class="sb-legend-icon"><i class="sb-li sli-ok">&#10003;</i></td>'
            . '<td class="sb-legend-lbl">' . $lblcorrect . '</td>'
            . '<td class="sb-legend-icon"><i class="sb-li sli-wrong">&#10007;</i></td>'
            . '<td class="sb-legend-lbl">' . $lblwrong . '</td>'
            . '</tr>';
        $html .= '<tr>'
            . '<td class="sb-legend-icon"><i class="sb-li sli-partial">&frac12;</i></td>'
            . '<td class="sb-legend-lbl">' . $lblpartial . '</td>'
            . '<td class="sb-legend-icon"><i class="sb-li sli-pending">?</i></td>'
            . '<td class="sb-legend-lbl">' . $lblpending . '</td>'
            . '</tr>';
        $html .= '</table>';
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
            return ['sb-partial', '&frac12;'];
        }
        if ($state->is_incorrect()) {
            return ['sb-wrong', '&#10007;'];
        }
        return ['sb-wrong', '&#183;'];
    }

    /**
     * Renders the questions section heading.
     *
     * @param quiz_attempt $attemptobj Attempt object.
     * @return string HTML markup.
     */
    private static function render_questions_heading(quiz_attempt $attemptobj): string {
        $slotcount = count($attemptobj->get_slots());
        if ($slotcount === 0) {
            return '';
        }

        $headingmain = (string) get_string('pdf_questions_heading', 'local_eledia_exam2pdf');
        $headingsub  = get_string('pdf_nav_legend_all', 'local_eledia_exam2pdf', (string) $slotcount);

        return '<div class="qs-hdr">' . s($headingmain)
            . ' <span class="qs-cnt">· ' . s($headingsub) . '</span>'
            . '</div>';
    }

    /**
     * Renders a single question slot including the page break logic for Q2+.
     *
     * @param quiz_attempt $attemptobj Attempt object.
     * @param int          $slot       The slot ID.
     * @param int          $num        1-based question number.
     * @param array        $config     Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_single_question_slot(
        quiz_attempt $attemptobj,
        int $slot,
        int $num,
        array $config
    ): string {
        $accent = self::accent_color($config);
        $quba   = \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid());
        $qa     = $quba->get_question_attempt($slot);
        $question = $qa->get_question();
        $qtype    = $question->get_type_name();
        $state    = $qa->get_state();

        $html = '';
        if ($num > 1) {
            $html .= '<pagebreak />';
        }
        $html .= self::render_question_card($qa, $question, $qtype, $state, $num, $config, $accent);
        return $html;
    }

    /**
     * Renders the questions and answers section (Legacy/Compat Wrapper).
     *
     * @param quiz_attempt $attemptobj Attempt object.
     * @param array        $config     Effective plugin config.
     * @return string HTML markup.
     */
    private static function render_questions(quiz_attempt $attemptobj, array $config): string {
        $html = self::render_questions_heading($attemptobj);
        $num = 1;
        foreach ($attemptobj->get_slots() as $slot) {
            $html .= self::render_single_question_slot($attemptobj, (int)$slot, $num, $config);
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

        $mark      = $qa->get_mark();
        $maxmark   = $qa->get_max_mark();
        $scoretext = self::format_question_mark($mark) . ' / ' . self::format_question_mark($maxmark);
        $questiontext = self::render_rich_text_fragment(
            (string) ($question->questiontext ?? ''),
            (int) ($question->questiontextformat ?? FORMAT_HTML)
        );

        $html  = '<div class="qcard ' . $cardclass . '">';
        $html .= '<table class="qhdr-table" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td class="qnum">'
            . '<span class="qno">'
            . s(get_string('pdf_question', 'local_eledia_exam2pdf')) . ' ' . $num
            . '</span>'
            . '<div class="qtext">' . $questiontext . '</div>'
            . '</td>';
        $html .= '<td class="qscore" style="font-variant-numeric: tabular-nums;">'
            . s($scoretext)
            . ' <span class="qmark ' . $markclass . '">' . $markchar . '</span>'
            . '</td>';
        $html .= '</tr></table>';

        $hint = self::resolve_qtype_hint($qtype, $state);
        if ($hint !== '') {
            $html .= '<div class="qprompt">' . s($hint) . '</div>';
        }

        [$answertext, $answerformat] = self::extract_response_text_and_format($qa);
        $displayanswer = self::decorate_answer_value($answertext, $state, $answerformat);

        $html .= '<table class="qans-table" cellpadding="0" cellspacing="0">';
        $html .= self::render_q_arow(
            get_string('pdf_youranswer', 'local_eledia_exam2pdf'),
            $displayanswer
        );
        if (!empty($config['showcorrectanswers']) && $qtype !== 'essay') {
            $solvalue = self::get_correct_answer_html($question, $qtype);
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
            return ['qcard-pending', 'qmark-pending', '&#8943;'];
        }
        if ($state->is_correct()) {
            return ['', '', '&#10003;'];
        }
        if ($state->is_partially_correct()) {
            return ['qcard-partial', 'qmark-partial', '&frac12;'];
        }
        if ($state->is_incorrect()) {
            return ['qcard-wrong', 'qmark-fail', '&#10007;'];
        }
        return ['', '', '&#183;'];
    }

    /**
     * Decorates the learner's answer with state-appropriate color markup.
     *
     * @param string          $raw Raw response text (may be empty).
     * @param \question_state $state Question state.
     * @param int             $format Moodle text format.
     * @return string HTML fragment (pre-escaped).
     */
    private static function decorate_answer_value(string $raw, \question_state $state, int $format = FORMAT_PLAIN): string {
        if ($raw === '') {
            return '<span style="color:#7a7a7a;font-style:italic;">'
                . s(get_string('pdf_noanswer', 'local_eledia_exam2pdf'))
                . '</span>';
        }
        $valuehtml = self::render_value_fragment($raw, $format);
        if ($state->is_correct()) {
            return '<span class="qans-correct">&#10003;&nbsp;</span>' . $valuehtml;
        }
        if ($state->is_partially_correct()) {
            return '<div style="color:#c48a00;">' . $valuehtml . '</div>';
        }
        if ($state->is_incorrect()) {
            return '<span class="qans-wrong">&#10007;&nbsp;</span><div>' . $valuehtml . '</div>';
        }
        return $valuehtml;
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
     * Extracts a display-ready correct answer HTML fragment for common question types.
     *
     * @param \question_definition $question The question definition.
     * @param string               $qtype    The qtype name.
     * @return string Correct answer HTML fragment.
     */
    private static function get_correct_answer_html(\question_definition $question, string $qtype): string {
        if ($qtype === 'truefalse') {
            if (property_exists($question, 'rightanswer') && isset($question->rightanswer)) {
                $label = $question->rightanswer
                    ? get_string('true', 'qtype_truefalse')
                    : get_string('false', 'qtype_truefalse');
                return '<span class="qans-correct">&#10003;&nbsp;</span>' . s($label);
            }
            return s(get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf'));
        }

        $answers = (property_exists($question, 'answers') && is_iterable($question->answers))
            ? $question->answers
            : [];

        switch ($qtype) {
            case 'multichoice':
                $correct = [];
                foreach ($answers as $answer) {
                    if ($answer->fraction > 0) {
                        $correct[] = self::render_value_fragment(
                            (string) ($answer->answer ?? ''),
                            (int) ($answer->answerformat ?? FORMAT_HTML)
                        );
                    }
                }
                return self::wrap_correct_answer_html($correct);

            case 'shortanswer':
            case 'numerical':
                $best = null;
                foreach ($answers as $answer) {
                    if ($best === null || $answer->fraction > $best->fraction) {
                        $best = $answer;
                    }
                }
                if ($best) {
                    return self::wrap_correct_answer_html([
                        self::render_value_fragment(
                            (string) ($best->answer ?? ''),
                            (int) ($best->answerformat ?? FORMAT_HTML)
                        ),
                    ]);
                }
                break;

            default:
                if (method_exists($question, 'get_correct_response')) {
                    $cr = $question->get_correct_response();
                    if ($cr) {
                        $items = [];
                        foreach ((array) $cr as $value) {
                            $plain = trim((string) $value);
                            if ($plain !== '') {
                                $items[] = self::render_value_fragment($plain, FORMAT_PLAIN);
                            }
                        }
                        return self::wrap_correct_answer_html($items);
                    }
                }
                break;
        }

        return s(get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf'));
    }

    /**
     * Wraps one or more correct-answer fragments with the success marker.
     *
     * @param string[] $items Pre-rendered HTML fragments.
     * @return string HTML fragment.
     */
    private static function wrap_correct_answer_html(array $items): string {
        $items = array_values(array_filter($items, static fn(string $item): bool => trim($item) !== ''));
        if (empty($items)) {
            return s(get_string('pdf_nocorrectanswer', 'local_eledia_exam2pdf'));
        }

        return '<span class="qans-correct">&#10003;&nbsp;</span>' . implode('<br>', $items);
    }

    /**
     * Extracts the best available answer text plus its Moodle text format.
     *
     * Essay responses keep their stored HTML formatting; other qtypes fall back
     * to the response summary, which is already a plain-text representation.
     *
     * @param \question_attempt $qa Question attempt.
     * @return array{0: string, 1: int} [answer text, format]
     */
    private static function extract_response_text_and_format(\question_attempt $qa): array {
        $response = $qa->get_response_summary();
        $answertext = ($response !== null && $response !== '') ? trim((string) $response) : '';
        $format = FORMAT_PLAIN;

        $qtdata = $qa->get_last_qt_data();
        if (is_array($qtdata) && !empty($qtdata['answer'])) {
            $answertext = (string) $qtdata['answer'];
            $format = isset($qtdata['answerformat']) ? (int) $qtdata['answerformat'] : FORMAT_HTML;
        }

        return [$answertext, $format];
    }

    /**
     * Renders a text fragment for value cells while preserving safe formatting.
     *
     * @param string $text Source text.
     * @param int    $format Moodle text format.
     * @return string HTML fragment.
     */
    private static function render_value_fragment(string $text, int $format): string {
        if ($format === FORMAT_PLAIN) {
            return nl2br(s(trim($text)));
        }

        return self::render_rich_text_fragment($text, $format);
    }

    /**
     * Renders a Moodle rich-text fragment without stripping author formatting.
     *
     * @param string $text Source text.
     * @param int    $format Moodle text format.
     * @return string HTML fragment.
     */
    private static function render_rich_text_fragment(string $text, int $format = FORMAT_HTML): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return format_text($text, $format, [
            'noclean' => true,
            'para' => false,
            'newlines' => false,
            'filter' => false,
        ]);
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
            $graderlabel = '— ' . $grader;
        } else if ($datestr !== '') {
            $graderlabel = '— ' . $datestr;
        }

        return [$commenttext, $graderlabel];
    }
}
