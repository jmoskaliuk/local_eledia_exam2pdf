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
 * Hook callbacks for injecting UI into Moodle output.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\hook;

/**
 * Injects the PDF download button into the quiz review page using the
 * Moodle 4.3+ Hooks API.
 */
class quiz_page_callbacks {
    /**
     * Adds the download button HTML before the page footer on quiz review pages.
     *
     * Dispatches between the student-facing review page and the trainer-facing
     * report overview page based on the current pagetype.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The firing hook instance.
     * @return void
     */
    public static function inject_download_button(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE, $USER, $DB;

        // Trainer/Manager: report overview page gets the bulk PDF section.
        if ($PAGE->pagetype === 'mod-quiz-report') {
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                self::inject_report_section($hook);
            }
            return;
        }

        // Only act on the quiz review page.
        if ($PAGE->pagetype !== 'mod-quiz-review') {
            return;
        }

        // Respect the studentdownload setting — if disabled, no button on the
        // review page. Teachers can still access PDFs via the report page.
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

        // No PDF exists for this attempt (not yet generated, or not in scope).
        if (!$record) {
            return;
        }

        // Check the file still exists (not yet expired / deleted).
        $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
        if (!$file) {
            return;
        }

        $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());

        $html = self::render_download_button($downloadurl->out(false));
        $hook->add_html($html);
    }

    /**
     * Injects the bulk PDF section into the trainer report overview page.
     *
     * Renders a table of all generated PDFs for the quiz with always-visible
     * per-row download icons, plus a single ZIP-download button below.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The firing hook instance.
     * @return void
     */
    private static function inject_report_section(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE;

        if (empty($PAGE->cm)) {
            return;
        }

        $context = \core\context\module::instance($PAGE->cm->id);
        if (!has_capability('local/eledia_exam2pdf:manage', $context)) {
            return;
        }

        $entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($PAGE->cm->id);

        $html = self::render_report_section($PAGE->cm->id, $entries);
        $hook->add_html($html);
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
        return <<<HTML
<div class="local-eledia-exam2pdf-downloadwrap" style="margin:1.5em 0; text-align:center;">
    <a href="{$url}"
       class="btn btn-primary"
       download
       aria-label="{$label}">
        <i class="fa fa-file-pdf-o" aria-hidden="true"></i>&nbsp;{$label}
    </a>
</div>
HTML;
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
        $colact  = get_string('manage_col_actions', 'local_eledia_exam2pdf');
        $dliconlabel = get_string('report_download_one', 'local_eledia_exam2pdf');

        if (empty($entries)) {
            $empty = get_string('manage_norecords', 'local_eledia_exam2pdf');
            return <<<HTML
<section class="local-eledia-exam2pdf-reportwrap" style="margin:2em 0;">
    <h3>{$heading}</h3>
    <p class="text-muted">{$empty}</p>
</section>
HTML;
        }

        $rows = '';
        foreach ($entries as $entry) {
            $name = s($entry->fullname);
            $when = userdate($entry->record->timecreated);
            $url  = $entry->downloadurl->out(false);
            $rows .= <<<HTML
<tr>
    <td>{$name}</td>
    <td>{$when}</td>
    <td class="text-center">
        <a href="{$url}" download
           class="btn btn-sm btn-outline-primary"
           aria-label="{$dliconlabel}"
           title="{$dliconlabel}">
            <i class="fa fa-download" aria-hidden="true"></i>
        </a>
    </td>
</tr>
HTML;
        }

        $zipurl  = (new \moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]))->out(false);
        $ziplabel = get_string('report_download_zip', 'local_eledia_exam2pdf');

        return <<<HTML
<section class="local-eledia-exam2pdf-reportwrap" style="margin:2em 0;">
    <h3>{$heading}</h3>
    <p>{$intro}</p>
    <table class="generaltable">
        <thead>
            <tr>
                <th>{$colname}</th>
                <th>{$coldate}</th>
                <th class="text-center">{$colact}</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
    <div style="margin-top:1em;">
        <a href="{$zipurl}" class="btn btn-primary">
            <i class="fa fa-file-archive-o" aria-hidden="true"></i>&nbsp;{$ziplabel}
        </a>
    </div>
</section>
HTML;
    }

    /**
     * Returns the HTML for a disabled (not-passed) button.
     *
     * @return string HTML.
     */
    private static function render_disabled_button(): string {
        $label = get_string('download_button_notpassed', 'local_eledia_exam2pdf');
        return <<<HTML
<div class="local-eledia-exam2pdf-downloadwrap" style="margin:1.5em 0; text-align:center;">
    <button class="btn btn-secondary" disabled aria-disabled="true" title="{$label}">
        <i class="fa fa-file-pdf-o" aria-hidden="true"></i>&nbsp;{$label}
    </button>
</div>
HTML;
    }
}
