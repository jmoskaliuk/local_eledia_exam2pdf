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
        global $PAGE, $USER, $DB;

        // --- DIAGNOSTIC: temporary, remove after CI debugging ---
        if ($PAGE->pagetype === 'mod-quiz-review') {
            $diag = [];
            $diag[] = 'pt=mod-quiz-review';
            $diag[] = 'cm=' . (!empty($PAGE->cm) ? 'yes(inst=' . $PAGE->cm->instance . ')' : 'NO');

            if (!empty($PAGE->cm)) {
                $diagconfig = \local_eledia_exam2pdf\helper::get_effective_config(
                    $PAGE->cm->instance
                );
                $diag[] = 'sd=' . var_export($diagconfig['studentdownload'] ?? null, true);
            }

            $diagattemptid = optional_param('attempt', 0, PARAM_INT);
            $diag[] = 'att=' . $diagattemptid;
            $diag[] = 'uid=' . ($USER->id ?? 0);

            if ($diagattemptid > 0 && !empty($USER->id)) {
                $recrec = $DB->get_record(
                    'local_eledia_exam2pdf',
                    ['attemptid' => $diagattemptid, 'userid' => $USER->id],
                    'id, cmid',
                    IGNORE_MISSING
                );
                $diag[] = 'rec_uid=' . ($recrec ? $recrec->id : 'null');

                $recany = $DB->get_record(
                    'local_eledia_exam2pdf',
                    ['attemptid' => $diagattemptid],
                    'id, userid',
                    IGNORE_MISSING
                );
                $diag[] = 'rec_any=' . ($recany ? 'id=' . $recany->id . ',uid=' . $recany->userid : 'null');

                if ($recrec) {
                    $diagfile = \local_eledia_exam2pdf\helper::get_stored_file($recrec);
                    $diag[] = 'file=' . ($diagfile ? 'ok(' . $diagfile->get_filesize() . ')' : 'null');
                }
            }

            $hook->add_html(
                '<div id="exam2pdf-diag" style="background:#ffe0e0;border:2px solid red;'
                . 'padding:8px;margin:10px 0;">'
                . 'EXAM2PDF_DIAG: ' . implode(', ', $diag)
                . '</div>'
            );
        }
        // --- END DIAGNOSTIC ---

        // Trainer/Manager: report overview page gets the bulk PDF section.
        if ($PAGE->pagetype === 'mod-quiz-report') {
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
        if ($PAGE->pagetype !== 'mod-quiz-review') {
            return;
        }

        if (empty($PAGE->cm)) {
            return;
        }

        $config = \local_eledia_exam2pdf\helper::get_effective_config($PAGE->cm->instance);
        if (empty($config['studentdownload'])) {
            return;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }

        // Look up the PDF record for the current user / attempt.
        $record = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attemptid, 'userid' => $USER->id],
            'id, cmid, timeexpires',
            IGNORE_MISSING
        );

        if (!$record) {
            return;
        }

        // Check the file still exists (not yet expired / deleted).
        $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
        if (!$file) {
            return;
        }

        $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());
        $hook->add_html(self::render_download_button($downloadurl->out(false)));
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
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                return self::get_report_section_html();
            }
            return '';
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
     * @return string HTML or empty string.
     */
    private static function get_download_button_html(): string {
        global $PAGE, $USER, $DB;

        // Respect the studentdownload setting.
        if (empty($PAGE->cm)) {
            return '';
        }
        $config = \local_eledia_exam2pdf\helper::get_effective_config($PAGE->cm->instance);
        if (empty($config['studentdownload'])) {
            return '';
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return '';
        }

        // Look up the PDF record for the current user / attempt.
        $record = $DB->get_record(
            'local_eledia_exam2pdf',
            ['attemptid' => $attemptid, 'userid' => $USER->id],
            'id, cmid, timeexpires',
            IGNORE_MISSING
        );

        if (!$record) {
            return '';
        }

        // Check the file still exists (not yet expired / deleted).
        $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
        if (!$file) {
            return '';
        }

        $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());

        return self::render_download_button($downloadurl->out(false));
    }

    /**
     * Returns the bulk PDF section HTML for the trainer report overview page.
     *
     * @return string HTML or empty string.
     */
    private static function get_report_section_html(): string {
        global $PAGE;

        if (empty($PAGE->cm)) {
            return '';
        }

        $context = \core\context\module::instance($PAGE->cm->id);
        if (!has_capability('local/eledia_exam2pdf:manage', $context)) {
            return '';
        }

        $entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($PAGE->cm->id);

        return self::render_report_section($PAGE->cm->id, $entries);
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
     * Returns the HTML for the trainer report PDF section.
     *
     * @param int   $cmid    Course module ID.
     * @param array $entries Result of helper::get_quiz_pdfs().
     * @return string HTML.
     */
    private static function render_report_section(int $cmid, array $entries): string {
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
        $ziplabel = get_string('report_download_zip', 'local_eledia_exam2pdf');

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
