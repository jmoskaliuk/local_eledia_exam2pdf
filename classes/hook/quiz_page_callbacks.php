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
 * @copyright  2025 eLeDia GmbH
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
        global $PAGE;

        // Trainer/Manager: report overview page gets the bulk PDF section.
        if ($PAGE->pagetype === 'mod-quiz-report') {
            $quicklinkhtml = self::get_report_selector_button_html();
            if ($quicklinkhtml !== '') {
                $hook->add_html($quicklinkhtml);
            }

            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                $html = self::get_report_section_html();
                if ($html !== '') {
                    $hook->add_html($html);
                }
            }
            return;
        }

        // Student: quiz review page gets the download button.
        if ($PAGE->pagetype === 'mod-quiz-review') {
            $html = self::get_download_button_html();
            if ($html !== '') {
                $hook->add_html($html);
            }
        }
    }

    /**
     * Returns the HTML to inject before the page footer.
     *
     * Public helper for the legacy before_footer callback in lib.php.
     *
     * @return string HTML fragment, or empty string if nothing to inject.
     */
    public static function get_footer_html(): string {
        global $PAGE;

        // Trainer/Manager: report overview page gets the bulk PDF section.
        if ($PAGE->pagetype === 'mod-quiz-report') {
            $html = self::get_report_selector_button_html();
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                $html .= self::get_report_section_html();
            }
            return $html;
        }

        // Student: quiz review page gets the download button.
        if ($PAGE->pagetype === 'mod-quiz-review') {
            return self::get_download_button_html();
        }

        return '';
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

        // Only expose the download button for the owner of this attempt.
        $attempt = $DB->get_record(
            'quiz_attempts',
            ['id' => $attemptid, 'userid' => $USER->id],
            '*',
            IGNORE_MISSING
        );
        if (!$attempt) {
            return '';
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        if (!\local_eledia_exam2pdf\helper::has_downloadown_capability($context)) {
            return '';
        }

        // Respect the studentdownload setting (global + per-quiz override).
        $config = \local_eledia_exam2pdf\helper::get_effective_config((int) $quiz->id);
        if (empty($config['studentdownload'])) {
            return '';
        }

        // Prefer existing generated file when available.
        $record = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attemptid, 'userid' => $USER->id],
            'id, cmid, quizid, timeexpires',
            IGNORE_MISSING
        );
        if ($record) {
            $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
            if ($file) {
                $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());
                return self::render_download_button($downloadurl->out(false));
            }
        }

        // On-demand mode: show the button even before a PDF exists.
        if (
            ($config['pdfgeneration'] ?? 'auto') === 'ondemand'
            && \local_eledia_exam2pdf\helper::is_in_pdf_scope($attempt, $quiz, $config)
        ) {
            $downloadurl = new \moodle_url('/local/eledia_exam2pdf/download.php', ['attemptid' => $attemptid]);
            return self::render_download_button($downloadurl->out(false));
        }

        return '';
    }

    /**
     * Returns the bulk PDF section HTML for the trainer report overview page.
     *
     * @return string HTML or empty string.
     */
    private static function get_report_section_html(): string {
        global $PAGE;

        // The report page DOES have $PAGE->cm set reliably because quiz
        // report pages extend the quiz module context properly. If it's
        // somehow missing, bail quietly.
        if (empty($PAGE->cm)) {
            return '';
        }

        $context = \core\context\module::instance($PAGE->cm->id);
        if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
            return '';
        }

        $entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($PAGE->cm->id);
        $config = \local_eledia_exam2pdf\helper::get_effective_config((int) $PAGE->cm->instance);
        return self::render_report_section($PAGE->cm->id, $entries, (string) ($config['bulkformat'] ?? 'zip'));
    }

    /**
     * Returns HTML for a quick-link button near the quiz report selector.
     *
     * @return string HTML or empty string.
     */
    private static function get_report_selector_button_html(): string {
        global $PAGE;

        if (empty($PAGE->cm)) {
            return '';
        }

        $context = \core\context\module::instance($PAGE->cm->id);
        if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
            return '';
        }

        return self::render_report_selector_button((int) $PAGE->cm->id);
    }

    // Private HTML renderers.

    /**
     * Returns the HTML for an active download button.
     *
     * @param string $url Download URL.
     * @return string HTML.
     */
    private static function render_download_button(string $url): string {
        $label = get_string('download_button', 'local_eledia_exam2pdf');
        return '<div class="local-eledia-exam2pdf-downloadwrap" style="margin:1.5em 0; text-align:center;">'
            . '<a href="' . $url . '"'
            . ' class="btn btn-primary"'
            . ' download'
            . ' aria-label="' . $label . '">'
            . '<i class="fa fa-file-pdf-o" aria-hidden="true"></i>&nbsp;' . $label
            . '</a>'
            . '</div>';
    }

    /**
     * Returns the HTML for a quick-link button in quiz report pages.
     *
     * The button is injected near the report selector ("Grades" dropdown).
     * If the selector cannot be found, it is prepended to #region-main.
     *
     * @param int $cmid Course module ID.
     * @return string HTML.
     */
    private static function render_report_selector_button(int $cmid): string {
        $url = (new \moodle_url('/local/eledia_exam2pdf/report.php', ['cmid' => $cmid]))->out(false);
        $label = s(get_string('report_nav_link', 'local_eledia_exam2pdf'));
        $holderid = 'local-eledia-exam2pdf-reportquicklink';

        $html = '<div id="' . $holderid . '" style="display:none;">'
            . '<a href="' . $url . '" class="btn btn-outline-primary btn-sm" style="margin-left:.5rem;">'
            . '<i class="fa fa-file-pdf-o" aria-hidden="true"></i>&nbsp;' . $label
            . '</a>'
            . '</div>';

        $html .= '<script>'
            . '(function() {'
            . 'var holder = document.getElementById("' . $holderid . '");'
            . 'if (!holder) { return; }'
            . 'var selectors = ["form.singleselect", ".singleselect", ".urlselect", "#region-main select"];'
            . 'var target = null;'
            . 'for (var i = 0; i < selectors.length; i++) {'
            . 'target = document.querySelector(selectors[i]);'
            . 'if (target) { break; }'
            . '}'
            . 'if (target) {'
            . 'var anchor = target.closest("form") || target;'
            . 'holder.style.display = "inline-block";'
            . 'anchor.insertAdjacentElement("afterend", holder);'
            . 'return;'
            . '}'
            . 'var region = document.querySelector("#region-main .region-content") || document.querySelector("#region-main");'
            . 'if (region) {'
            . 'holder.style.display = "block";'
            . 'holder.style.margin = "0 0 1rem 0";'
            . 'region.insertAdjacentElement("afterbegin", holder);'
            . '}'
            . '})();'
            . '</script>';

        return $html;
    }

    /**
     * Returns the HTML for the trainer report PDF section.
     *
     * @param int   $cmid    Course module ID.
     * @param array $entries Result of helper::get_quiz_pdfs().
     * @param string $bulkformat Configured bulk download format.
     * @return string HTML.
     */
    private static function render_report_section(int $cmid, array $entries, string $bulkformat = 'zip'): string {
        $heading = get_string('report_section_heading', 'local_eledia_exam2pdf');
        $intro   = get_string('report_section_intro', 'local_eledia_exam2pdf');
        $colname = get_string('manage_col_learner', 'local_eledia_exam2pdf');
        $coldate = get_string('manage_col_timecreated', 'local_eledia_exam2pdf');
        $colact  = get_string('actions');
        $dliconlabel = get_string('report_download_one', 'local_eledia_exam2pdf');

        if (empty($entries)) {
            $empty = get_string('manage_norecords', 'local_eledia_exam2pdf');
            return '<section class="local-eledia-exam2pdf-reportwrap" style="margin:2em 0;">'
                . '<h3>' . $heading . '</h3>'
                . '<p class="text-muted">' . $empty . '</p>'
                . '</section>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $name = s($entry->fullname);
            $when = userdate($entry->record->timecreated);
            $url  = $entry->downloadurl->out(false);
            $rows .= '<tr>'
                . '<td>' . $name . '</td>'
                . '<td>' . $when . '</td>'
                . '<td class="text-center">'
                . '<a href="' . $url . '" download'
                . ' class="btn btn-sm btn-outline-primary"'
                . ' aria-label="' . $dliconlabel . '"'
                . ' title="' . $dliconlabel . '">'
                . '<i class="fa fa-download" aria-hidden="true"></i>'
                . '</a>'
                . '</td>'
                . '</tr>';
        }

        $zipurl   = (new \moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]))->out(false);
        $ziplabel = ($bulkformat === 'merged')
            ? get_string('report_download_merged', 'local_eledia_exam2pdf')
            : get_string('report_download_zip', 'local_eledia_exam2pdf');

        return '<section class="local-eledia-exam2pdf-reportwrap" style="margin:2em 0;">'
            . '<h3>' . $heading . '</h3>'
            . '<p>' . $intro . '</p>'
            . '<table class="generaltable">'
            . '<thead><tr>'
            . '<th>' . $colname . '</th>'
            . '<th>' . $coldate . '</th>'
            . '<th class="text-center">' . $colact . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<div style="margin-top:1em;">'
            . '<a href="' . $zipurl . '" class="btn btn-primary">'
            . '<i class="fa fa-file-archive-o" aria-hidden="true"></i>&nbsp;' . $ziplabel
            . '</a>'
            . '</div>'
            . '</section>';
    }
}
