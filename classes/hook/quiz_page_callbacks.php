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
    /** @var bool Prevent duplicate actions-column JS injection per request. */
    private static bool $overviewactionsinjected = false;
    /** @var bool Prevent duplicate review-download output per request. */
    private static bool $reviewdownloadinjected = false;

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
     * Returns the HTML to inject before the page footer.
     *
     * Public helper for the legacy before_footer callback in lib.php.
     *
     * @return string HTML fragment, or empty string if nothing to inject.
     */
    public static function get_footer_html(): string {
        // Trainer/Manager: report overview page gets the bulk PDF section.
        if (self::is_quiz_report_page()) {
            $html = '';
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode === 'overview' || $mode === '') {
                $cm = self::resolve_report_cm();
                if ($cm) {
                    self::queue_overview_actions_column_js((int) $cm->id);
                }
                $html .= self::get_report_section_html();
            }
            return $html;
        }

        // Student: quiz review page gets the download button.
        if (self::is_quiz_review_page()) {
            return self::get_download_button_html();
        }

        return '';
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
     * Queues JavaScript to add an "Actions" column to the quiz overview report
     * table directly after the grade column.
     *
     * @param int $cmid Course module ID.
     * @return void
     */
    private static function queue_overview_actions_column_js(int $cmid): void {
        global $PAGE;

        if (self::$overviewactionsinjected) {
            return;
        }
        self::$overviewactionsinjected = true;

        $context = \core\context\module::instance($cmid);
        if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
            return;
        }

        $canregenerate = \local_eledia_exam2pdf\helper::has_generatepdf_capability($context);
        $actionslabel = get_string('actions');
        $downloadlabel = get_string('report_download_one', 'local_eledia_exam2pdf');
        $regeneratelabel = get_string('report_regenerate_one', 'local_eledia_exam2pdf');
        $gradelabel = \core_text::strtolower((string) get_string('gradenoun'));

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

        $actionslabeljson = json_encode($actionslabel);
        $downloadlabeljson = json_encode($downloadlabel);
        $regeneratelabeljson = json_encode($regeneratelabel);
        $gradelabeljson = json_encode($gradelabel);
        $downloadbaseurljson = json_encode($downloadbaseurl);
        $regeneratebaseurljson = json_encode($regeneratebaseurl);
        $canregeneratejson = $canregenerate ? 'true' : 'false';

        $js = <<<JS
(function() {
    var table = document.querySelector('table.generaltable.grades');
    if (!table) { return; }

    var headrow = table.querySelector('thead tr');
    if (!headrow) { return; }

    if (headrow.querySelector('.local-eledia-exam2pdf-col-actions')) {
        return;
    }

    var gradelabel = {$gradelabeljson};
    var headers = Array.prototype.slice.call(headrow.children);
    var gradeindex = -1;
    for (var i = 0; i < headers.length; i++) {
        var htext = (headers[i].textContent || '').trim().toLowerCase();
        if (!htext) { continue; }
        if (htext.indexOf(gradelabel) === 0 || htext.indexOf(gradelabel + '/') === 0) {
            gradeindex = i;
            break;
        }
    }
    if (gradeindex < 0) {
        return;
    }

    var actionslabel = {$actionslabeljson};
    var downloadlabel = {$downloadlabeljson};
    var regeneratelabel = {$regeneratelabeljson};
    var downloadbaseurl = {$downloadbaseurljson};
    var regeneratebaseurl = {$regeneratebaseurljson};
    var canregenerate = {$canregeneratejson};

    function insertAfter(row, index, cell) {
        var ref = row.children[index];
        if (!ref) {
            row.appendChild(cell);
            return;
        }
        if (ref.nextSibling) {
            row.insertBefore(cell, ref.nextSibling);
        } else {
            row.appendChild(cell);
        }
    }

    function findCellIndexForLogicalColumn(row, logicalindex) {
        var cells = row.children;
        var cursor = 0;
        for (var i = 0; i < cells.length; i++) {
            var span = parseInt(cells[i].getAttribute('colspan') || '1', 10);
            if (!span || span < 1) { span = 1; }
            var end = cursor + span - 1;
            if (logicalindex >= cursor && logicalindex <= end) {
                return i;
            }
            cursor += span;
        }
        return -1;
    }

    function normalizeRowCellAlignment(row) {
        var cells = row.children;
        for (var i = 0; i < cells.length; i++) {
            // Force visual parity with "Finished / Not yet graded" columns.
            cells[i].classList.remove('align-top');
            cells[i].classList.add('align-middle');
            cells[i].style.setProperty('vertical-align', 'middle', 'important');
        }

        var questions = row.querySelectorAll('td span.que');
        for (var qi = 0; qi < questions.length; qi++) {
            var qcell = questions[qi].closest('td');
            if (qcell) {
                qcell.style.textAlign = 'center';
                qcell.style.setProperty('vertical-align', 'middle', 'important');
            }

            var qlink = questions[qi].closest('a');
            if (qlink) {
                qlink.style.display = 'inline-flex';
                qlink.style.alignItems = 'center';
                qlink.style.justifyContent = 'center';
                qlink.style.width = '100%';
                qlink.style.minHeight = '1.75rem';
            }

            questions[qi].style.display = 'inline-flex';
            questions[qi].style.alignItems = 'center';
            questions[qi].style.justifyContent = 'center';
            questions[qi].style.gap = '0.25rem';
            questions[qi].style.flexWrap = 'nowrap';
            questions[qi].style.whiteSpace = 'nowrap';
            questions[qi].style.lineHeight = '1.2';
            questions[qi].style.setProperty('vertical-align', 'middle', 'important');
        }

        var qicons = row.querySelectorAll('td span.que img.icon, td span.que i.icon, td span.que .questionflag');
        for (var ii = 0; ii < qicons.length; ii++) {
            qicons[ii].style.setProperty('vertical-align', 'middle', 'important');
        }
    }

    function expandRowAtGradePosition(row) {
        var anchoridx = findCellIndexForLogicalColumn(row, gradeindex);
        if (anchoridx < 0) {
            return false;
        }

        var anchorcell = row.children[anchoridx];
        var span = parseInt(anchorcell.getAttribute('colspan') || '1', 10);
        if (!span || span < 1) { span = 1; }

        if (span > 1) {
            anchorcell.setAttribute('colspan', String(span + 1));
            return true;
        }

        var td = document.createElement('td');
        td.className = 'cell local-eledia-exam2pdf-cell-actions';
        td.style.setProperty('vertical-align', 'middle', 'important');
        td.innerHTML = '&nbsp;';
        insertAfter(row, anchoridx, td);
        return true;
    }

    function extractAttemptId(row) {
        var checkbox = row.querySelector('input[type="checkbox"][name="attemptid[]"]');
        if (checkbox && checkbox.value) {
            var id = parseInt(checkbox.value, 10);
            if (id > 0) { return id; }
        }

        var links = row.querySelectorAll('a[href*="/mod/quiz/review.php?"]');
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            var match = href.match(/[?&]attempt=(\\d+)/);
            if (match && match[1]) {
                var aid = parseInt(match[1], 10);
                if (aid > 0) { return aid; }
            }
        }
        return 0;
    }

    function buildActionsHtml(attemptid) {
        if (!attemptid) { return '-'; }

        var downloadurl = downloadbaseurl.replace(/([?&]attemptid=)0(?!\\d)/, '$1' + attemptid);
        var html = '<a href="' + downloadurl + '" class="btn btn-sm btn-outline-primary"'
            + ' aria-label="' + downloadlabel + '" title="' + downloadlabel + '">'
            + '<i class="fa fa-download" aria-hidden="true"></i></a>';

        if (canregenerate) {
            var regenerateurl = regeneratebaseurl.replace(/([?&]attemptid=)0(?!\\d)/, '$1' + attemptid);
            html += ' <a href="' + regenerateurl + '" class="btn btn-sm btn-outline-secondary"'
                + ' aria-label="' + regeneratelabel + '" title="' + regeneratelabel + '">'
                + '<i class="fa fa-refresh" aria-hidden="true"></i></a>';
        }

        return html;
    }

    var th = document.createElement('th');
    th.className = 'header local-eledia-exam2pdf-col-actions text-center';
    th.style.whiteSpace = 'nowrap';
    th.style.width = '5.5rem';
    th.textContent = actionslabel;
    insertAfter(headrow, gradeindex, th);

    var bodyrows = table.querySelectorAll('tbody tr');
    for (var bi = 0; bi < bodyrows.length; bi++) {
        var row = bodyrows[bi];
        normalizeRowCellAlignment(row);
        if (row.querySelector('.local-eledia-exam2pdf-cell-actions')) {
            continue;
        }
        var attemptid = extractAttemptId(row);
        if (!attemptid) {
            // Keep colspan-based summary rows aligned with the new actions column.
            expandRowAtGradePosition(row);
            normalizeRowCellAlignment(row);
            continue;
        }
        var anchoridx = findCellIndexForLogicalColumn(row, gradeindex);
        if (anchoridx < 0) {
            continue;
        }
        var td = document.createElement('td');
        td.className = 'cell text-center local-eledia-exam2pdf-cell-actions';
        td.style.whiteSpace = 'nowrap';
        td.style.setProperty('vertical-align', 'middle', 'important');
        td.innerHTML = buildActionsHtml(attemptid);
        insertAfter(row, anchoridx, td);
        normalizeRowCellAlignment(row);
    }

    var footrows = table.querySelectorAll('tfoot tr');
    for (var fi = 0; fi < footrows.length; fi++) {
        var row = footrows[fi];
        normalizeRowCellAlignment(row);
        if (row.querySelector('.local-eledia-exam2pdf-cell-actions')) {
            continue;
        }
        expandRowAtGradePosition(row);
        normalizeRowCellAlignment(row);
    }

    // Some report scripts or redraws may reapply top-aligned utility classes.
    window.setTimeout(function() {
        var rerows = table.querySelectorAll('tbody tr, tfoot tr');
        for (var ri = 0; ri < rerows.length; ri++) {
            normalizeRowCellAlignment(rerows[ri]);
        }
    }, 120);
})();
JS;
        $PAGE->requires->js_init_code($js);
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
        if (self::$reviewdownloadinjected) {
            return '';
        }
        self::$reviewdownloadinjected = true;

        $holderid = 'local-eledia-exam2pdf-reviewdownload-' . $attemptid;
        self::queue_review_download_button_js($holderid);
        return self::render_download_button($url, $holderid);
    }

    /**
     * Queues JavaScript to add a second download button near quiz review actions.
     *
     * @param string $holderid DOM id of the original footer button wrapper.
     * @return void
     */
    private static function queue_review_download_button_js(string $holderid): void {
        global $PAGE;

        $holderidjson = json_encode($holderid);
        $js = <<<JS
(function() {
    var holder = document.getElementById({$holderidjson});
    if (!holder) { return; }

    var finish = document.querySelector('button[name="finishreview"], input[name="finishreview"]');
    if (finish) {
        holder.style.display = 'inline-block';
        holder.style.margin = '0 0 0 .5rem';
        holder.style.textAlign = 'left';
        finish.insertAdjacentElement('afterend', holder);
        return;
    }

    var header = document.querySelector('#page-header .page-header-headings')
        || document.querySelector('#page-header .page-header-content')
        || document.querySelector('#page-header');
    if (header) {
        holder.style.display = 'block';
        holder.style.margin = '0 0 1rem 0';
        holder.style.textAlign = 'left';
        header.insertAdjacentElement('beforeend', holder);
    }
})();
JS;
        $PAGE->requires->js_init_code($js);
    }

    /**
     * Queues JavaScript to place the ZIP/merged button next to "Regrade attempts".
     *
     * @param string $sectionid DOM id of the report section wrapper.
     * @return void
     */
    private static function queue_report_section_button_js(string $sectionid): void {
        global $PAGE;

        $sectionidjson = json_encode($sectionid);
        $js = <<<JS
(function() {
    var section = document.getElementById({$sectionidjson});
    if (!section) { return; }

    var moveNextTo = document.getElementById('regradeattempts')
        || document.querySelector('input[name="regradeattempts"], button[name="regradeattempts"]');

    if (moveNextTo) {
        section.style.display = 'inline-block';
        section.style.margin = '0 0 0 .5rem';
        section.style.verticalAlign = 'middle';
        moveNextTo.insertAdjacentElement('afterend', section);
        return;
    }

    // Fallback when "Regrade attempts" is unavailable.
    section.style.margin = '1rem 0 0 0';
})();
JS;
        $PAGE->requires->js_init_code($js);
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
        $label = get_string('download_button', 'local_eledia_exam2pdf');
        $idattr = $holderid !== '' ? ' id="' . $holderid . '"' : '';
        return '<div' . $idattr . ' class="local-eledia-exam2pdf-downloadwrap" style="margin:1.5em 0; text-align:center;">'
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
     * @param string $bulkformat Configured bulk download format.
     * @return string HTML.
     */
    private static function render_report_section(
        int $cmid,
        array $entries,
        string $bulkformat = 'zip'
    ): string {
        $sectionid = self::get_report_section_id($cmid);

        $zipurl   = (new \moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]))->out(false);
        $ziplabel = ($bulkformat === 'merged')
            ? get_string('report_download_merged', 'local_eledia_exam2pdf')
            : get_string('report_download_zip', 'local_eledia_exam2pdf');

        $html = '<section id="' . $sectionid . '" class="local-eledia-exam2pdf-reportwrap" style="margin:1.5em 0;">'
            . '<div class="local-eledia-exam2pdf-reportbuttonwrap">'
            . '<a href="' . $zipurl . '" class="btn btn-primary"'
            . (empty($entries) ? ' aria-disabled="true"' : '')
            . '>'
            . '<i class="fa fa-file-archive-o" aria-hidden="true"></i>&nbsp;' . $ziplabel
            . '</a>'
            . '</div>'
            . '</section>';

        if (empty($entries)) {
            $html .= '<p class="text-muted" style="margin-top:.5rem;">'
                . s(get_string('report_zip_nofiles', 'local_eledia_exam2pdf'))
                . '</p>';
        }

        return $html;
    }
}
