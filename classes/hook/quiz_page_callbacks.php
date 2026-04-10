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

defined('MOODLE_INTERNAL') || die();

/**
 * Injects the PDF download button into the quiz review page using the
 * Moodle 4.3+ Hooks API.
 */
class quiz_page_callbacks {

    /**
     * Adds the download button HTML before the page footer on quiz review pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function inject_download_button(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE, $USER, $DB;

        // Only act on the quiz review page.
        if ($PAGE->pagetype !== 'mod-quiz-review') {
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

        // Also allow managers to see the button for any user.
        if (!$record && $PAGE->cm) {
            $context   = \core\context\module::instance($PAGE->cm->id);
            $canmanage = has_capability('local/eledia_exam2pdf:manage', $context);
            if ($canmanage) {
                $record = $DB->get_record(
                    'local_eledia_exam2pdf',
                    ['attemptid' => $attemptid],
                    'id, cmid, timeexpires',
                    IGNORE_MISSING
                );
            }
        }

        if (!$record) {
            // Attempt is either not passed or no PDF yet — show disabled button.
            $html = self::render_disabled_button();
            $hook->add_html($html);
            return;
        }

        // Check file still exists (not yet expired/deleted).
        $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
        if (!$file) {
            $html = self::render_disabled_button();
            $hook->add_html($html);
            return;
        }

        $downloadurl = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());

        $html = self::render_download_button($downloadurl->out(false));
        $hook->add_html($html);
    }

    // -----------------------------------------------------------------------
    // Private HTML renderers
    // -----------------------------------------------------------------------

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
