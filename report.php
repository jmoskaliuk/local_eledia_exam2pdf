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
 * PDF certificates report page — paginated, sortable, filterable table.
 *
 * URL: /local/eledia_exam2pdf/report.php?cmid=<cmid>
 *
 * Modelled after the quiz grades report (mod/quiz/report). Provides
 * pagination, A-Z name initials filter, sortable columns, and per-row
 * download actions.
 *
 * Requires download-all access (downloadall/manage capability fallback).
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid     = required_param('cmid', PARAM_INT);
$pagesize = optional_param('pagesize', 30, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = \core\context\module::instance($cm->id);

require_login($course, false, $cm);
if (!\local_eledia_exam2pdf\helper::has_downloadall_capability($context)) {
    throw new required_capability_exception(
        $context,
        'local/eledia_exam2pdf:downloadall',
        'nopermissions',
        ''
    );
}

$baseurl = new moodle_url('/local/eledia_exam2pdf/report.php', ['cmid' => $cmid]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(
    format_string($quiz->name) . ': ' . get_string('report_heading', 'local_eledia_exam2pdf')
);
$PAGE->set_pagelayout('incourse');

// Set up the table.
$table = new \local_eledia_exam2pdf\output\report_table('exam2pdf_report_' . $cmid, $quiz, $cmid, $baseurl);

// Support CSV / Excel download.
$table->is_downloading($download, 'exam2pdf_' . $quiz->id, get_string('report_heading', 'local_eledia_exam2pdf'));

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('report_heading', 'local_eledia_exam2pdf'));
    echo html_writer::tag('p', format_string($quiz->name), ['class' => 'lead']);
}

// Populate and render the table.
$table->query_db($pagesize);
$table->out($pagesize, true);

if (!$table->is_downloading()) {
    // Bulk ZIP download button below the table.
    $zipurl   = new moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]);
    $config = \local_eledia_exam2pdf\helper::get_effective_config((int) $quiz->id);
    $ziplabel = (($config['bulkformat'] ?? 'zip') === 'merged')
        ? get_string('report_download_merged', 'local_eledia_exam2pdf')
        : get_string('report_download_zip', 'local_eledia_exam2pdf');
    echo html_writer::div(
        html_writer::link($zipurl, $ziplabel, ['class' => 'btn btn-primary']),
        'mt-3'
    );

    echo $OUTPUT->footer();
}
