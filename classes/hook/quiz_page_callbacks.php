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
 * Callbacks for injecting UI into Moodle output.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\hook;

use core\hook\output\before_footer_html_generation;

/**
 * Provides HTML fragments that are injected into the quiz review and
 * quiz report pages via the before_footer_html_generation hook.
 *
 * NOTE: $PAGE->cm is NOT reliable during this hook's dispatch in
 * Moodle 4.5+/5.x on mod-quiz-review pages. The callbacks below
 * derive cmid/quizid from the PDF record in the database instead.
 */
class quiz_page_callbacks {
    /**
     * Hook callback: injects download button / report section before the footer.
     *
     * Registered in db/hooks.php for the before_footer_html_generation hook.
     *
     * @param before_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function inject_footer_html(before_footer_html_generation $hook): void {
        // Trainer/Manager: report overview page gets the bulk PDF section.
        if (self::is_quiz_report_page()) {
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                $cm = self::resolve_report_cm();
                if ($cm) {
                    self::queue_overview_actions_column_js((int) $cm->id);
                }
                $html = self::get_report_section_html();
                if ($html !== '') {
                    $hook->add_html($html);
                }
            }
            return;
        }

        // Student: quiz review page gets the download button.
        if (self::is_quiz_review_page()) {
            $html = self::get_download_button_html();
            if ($html !== '') {
                $hook->add_html($html);
            }
        }
    }

    /**
     * Returns true when the current page is a quiz report page.
     *
     * @return bool
     */
    private static function is_quiz_report_page(): bool {
        global $PAGE;

        $pagetype = (string) ($PAGE->pagetype ?? '');
        if (strpos($pagetype, 'mod-quiz-report') === 0) {
            return true;
        }

        $path = method_exists($PAGE->url, 'get_path') ? (string) $PAGE->url->get_path() : '';
        return $path === '/mod/quiz/report.php';
    }

    /**
     * Returns true when the current page is a quiz review page.
     *
     * @return bool
     */
    private static function is_quiz_review_page(): bool {
        global $PAGE;

        $pagetype = (string) ($PAGE->pagetype ?? '');
        if (strpos($pagetype, 'mod-quiz-review') === 0) {
            return true;
        }

        $path = method_exists($PAGE->url, 'get_path') ? (string) $PAGE->url->get_path() : '';
        return $path === '/mod/quiz/review.php';
    }

    /**
     * Returns the download-button HTML for the quiz review page.
     *
     * Derives cmid/quizid from the PDF record, not from $PAGE->cm, because
     * $PAGE->cm is unreliable during the before_footer hook dispatch.
     *
     * @return string HTML or empty string.
     */
    private static function get_download_button_html(): string {
        global $USER, $DB;

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid || empty($USER->id)) {
            return '';
        }

        $attempt = $DB->get_record(
            'quiz_attempts',
            ['id' => $attemptid],
            '*',
            IGNORE_MISSING
        );
        if (!$attempt) {
            return '';
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $candownloadall = \local_eledia_exam2pdf\helper::has_downloadall_capability($context);
        $candownloadown = \local_eledia_exam2pdf\helper::has_downloadown_capability($context);

        // Learners may only access their own attempt; trainers/managers with
        // download-all can also access foreign attempts.
        if (!$candownloadall) {
            if (!$candownloadown || (int) $attempt->userid !== (int) $USER->id) {
                return '';
            }
        }

        // Respect the studentdownload setting (global + per-quiz override).
        $config = \local_eledia_exam2pdf\helper::get_effective_config((int) $quiz->id);
        if (!$candownloadall && empty($config['studentdownload'])) {
            return '';
        }

        // Prefer existing generated file when available.
        $record = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attemptid],
            'id, cmid, quizid, timeexpires',
            IGNORE_MISSING
        );
        if ($record) {
            $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
            if ($file) {
                $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());
                return self::render_review_download_button($downloadurl->out(false), (int) $attemptid);
            }
        }

        // On-demand mode: show the button even before a PDF exists.
        if (
            ($config['pdfgeneration'] ?? 'auto') === 'ondemand'
            && \local_eledia_exam2pdf\helper::is_in_pdf_scope($attempt, $quiz, $config)
        ) {
            $downloadurl = new \moodle_url('/local/eledia_exam2pdf/download.php', ['attemptid' => $attemptid]);
            return self::render_review_download_button($downloadurl->out(false), (int) $attemptid);
        }

        return '';
    }

    /**
     * Returns the bulk PDF section HTML for the trainer report overview page.
     *
     * @return string HTML or empty string.
     */
    private static function get_report_section_html(): string {
        $cm = self::resolve_report_cm();
        if (!$cm) {
            return '';
        }

        $context = \core\context\module::instance($cm->id);
        if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
            return '';
        }

        $entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($cm->id);
        $config = \local_eledia_exam2pdf\helper::get_effective_config((int) $cm->instance);
        self::queue_report_section_button_js(self::get_report_section_id((int) $cm->id));
        return self::render_report_section(
            $cm->id,
            $entries,
            (string) ($config['bulkformat'] ?? 'zip')
        );
    }

    /**
     * Resolves the quiz course module for report pages.
     *
     * @return \stdClass|null
     */
    private static function resolve_report_cm(): ?\stdClass {
        global $PAGE;

        if (!empty($PAGE->cm) && ($PAGE->cm->modname ?? '') === 'quiz') {
            return $PAGE->cm;
        }

        $cmid = optional_param('id', 0, PARAM_INT);
        if ($cmid <= 0) {
            return null;
        }

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        return $cm ?: null;
    }

    /**
     * Queues the AMD module that adds an "Actions" column to the quiz
     * overview report table directly after the grade column.
     *
     * @param int $cmid Course module ID.
     * @return void
     */
    private static function queue_overview_actions_column_js(int $cmid): void {
        global $PAGE, $OUTPUT;

        $context = \core\context\module::instance($cmid);
        if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
            return;
        }

        $canregenerate = \local_eledia_exam2pdf\helper::has_generatepdf_capability($context);

        $downloadbaseurl = (new \moodle_url('/local/eledia_exam2pdf/download.php', [
            'attemptid' => 0,
        ]))->out(false);

        $returnurl = $PAGE->url->out_as_local_url(false);
        $regeneratebaseurl = (new \moodle_url('/local/eledia_exam2pdf/regenerate.php', [
            'attemptid' => 0,
            'cmid' => $cmid,
            'sesskey' => sesskey(),
            'returnurl' => $returnurl,
        ]))->out(false);

        $downloadicon   = $OUTPUT->pix_icon('t/download', '', 'moodle', ['class' => 'icon m-0']);
        $regenerateicon = $OUTPUT->pix_icon('i/reload', '', 'moodle', ['class' => 'icon m-0']);

        $PAGE->requires->js_call_amd(
            'local_eledia_exam2pdf/quiz_overview_actions',
            'init',
            [[
                'actionsLabel'      => get_string('actions'),
                'downloadLabel'     => get_string('report_download_one', 'local_eledia_exam2pdf'),
                'regenerateLabel'   => get_string('report_regenerate_one', 'local_eledia_exam2pdf'),
                'gradeLabel'        => \core_text::strtolower((string) get_string('gradenoun')),
                'downloadBaseUrl'   => $downloadbaseurl,
                'regenerateBaseUrl' => $regeneratebaseurl,
                'canRegenerate'     => $canregenerate,
                'downloadIcon'      => $downloadicon,
                'regenerateIcon'    => $regenerateicon,
            ]]
        );
    }

    /**
     * Returns a stable DOM id for the injected overview section.
     *
     * @param int $cmid Course module ID.
     * @return string
     */
    private static function get_report_section_id(int $cmid): string {
        return 'local-eledia-exam2pdf-section-' . $cmid;
    }

    /**
     * Returns review-page download button HTML with additional top placement.
     *
     * @param string $url Download URL.
     * @param int $attemptid Attempt ID (for unique DOM id).
     * @return string HTML.
     */
    private static function render_review_download_button(string $url, int $attemptid): string {
        $holderid = 'local-eledia-exam2pdf-reviewdownload-' . $attemptid;
        self::queue_review_download_button_js($holderid);
        return self::render_download_button($url, $holderid);
    }

    /**
     * Queues the AMD module that places the review-page download button
     * next to "Finish review" or into the page header.
     *
     * @param string $holderid DOM id of the original footer button wrapper.
     * @return void
     */
    private static function queue_review_download_button_js(string $holderid): void {
        global $PAGE;

        $PAGE->requires->js_call_amd(
            'local_eledia_exam2pdf/review_download_button',
            'init',
            [['holderId' => $holderid]]
        );
    }

    /**
     * Queues the AMD module that places the ZIP/merged button next to
     * "Regrade attempts" (or below the table when that control is missing).
     *
     * @param string $sectionid DOM id of the report section wrapper.
     * @return void
     */
    private static function queue_report_section_button_js(string $sectionid): void {
        global $PAGE;

        $PAGE->requires->js_call_amd(
            'local_eledia_exam2pdf/report_section_button',
            'init',
            [['sectionId' => $sectionid]]
        );
    }

    // Private HTML renderers.

    /**
     * Returns the HTML for an active download button.
     *
     * @param string $url Download URL.
     * @param string $holderid Optional DOM id for wrapper.
     * @return string HTML.
     */
    private static function render_download_button(string $url, string $holderid = ''): string {
        global $OUTPUT;

        $label = get_string('download_button', 'local_eledia_exam2pdf');
        $idattr = $holderid !== '' ? ' id="' . $holderid . '"' : '';
        $icon = $OUTPUT->pix_icon('f/pdf', '', 'moodle', ['class' => 'icon']);
        return '<div' . $idattr . ' class="local-eledia-exam2pdf-downloadwrap my-4 text-center">'
            . '<a href="' . $url . '"'
            . ' class="btn btn-primary"'
            . ' download'
            . ' aria-label="' . $label . '">'
            . $icon . $label
            . '</a>'
            . '</div>';
    }

    /**
     * Returns the HTML for the trainer report PDF section.
     *
     * @param int   $cmid    Course module ID.
     * @param array $entries Result of helper::get_quiz_pdfs().
     * @param string $bulkformat Configured bulk download format.
     * @return string HTML.
     */
    private static function render_report_section(
        int $cmid,
        array $entries,
        string $bulkformat = 'zip'
    ): string {
        global $OUTPUT;

        $sectionid = self::get_report_section_id($cmid);

        $zipurl   = (new \moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]))->out(false);
        $ziplabel = ($bulkformat === 'merged')
            ? get_string('report_download_merged', 'local_eledia_exam2pdf')
            : get_string('report_download_zip', 'local_eledia_exam2pdf');
        $icon = $OUTPUT->pix_icon('f/archive', '', 'moodle', ['class' => 'icon']);

        $html = '<section id="' . $sectionid . '" class="local-eledia-exam2pdf-reportwrap my-4">'
            . '<div class="local-eledia-exam2pdf-reportbuttonwrap">'
            . '<a href="' . $zipurl . '" class="btn btn-primary"'
            . (empty($entries) ? ' aria-disabled="true"' : '')
            . '>'
            . $icon . $ziplabel
            . '</a>'
            . '</div>'
            . '</section>';

        if (empty($entries)) {
            $html .= '<p class="text-muted mt-1">'
                . s(get_string('report_zip_nofiles', 'local_eledia_exam2pdf'))
                . '</p>';
        }

        return $html;
    }
}
