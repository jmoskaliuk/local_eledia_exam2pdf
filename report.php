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
 * PDF certificates report page — lists all generated PDFs for a given quiz.
 *
 * URL: /local/eledia_exam2pdf/report.php?cmid=<cmid>
 *
 * Requires the local/eledia_exam2pdf:manage capability (editing teacher,
 * manager). Students are directed to the quiz review page instead.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = \core\context\module::instance($cm->id);

require_login($course, false, $cm);
// Use :manage (same capability checked in the nav link) so teachers always
// have access without needing a separate :downloadall assignment in the DB.
require_capability('local/eledia_exam2pdf:manage', $context);

$PAGE->set_url('/local/eledia_exam2pdf/report.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(
    format_string($quiz->name) . ': ' . get_string('report_heading', 'local_eledia_exam2pdf')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('report_heading', 'local_eledia_exam2pdf'));
echo html_writer::tag('p', format_string($quiz->name), ['class' => 'lead']);

$entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($cm->id);

if (empty($entries)) {
    echo $OUTPUT->notification(
        get_string('manage_norecords', 'local_eledia_exam2pdf'),
        'info'
    );
} else {
    // Build the PDF-certificates table.
    $table              = new html_table();
    $table->head        = [
        get_string('manage_col_learner', 'local_eledia_exam2pdf'),
        get_string('manage_col_timecreated', 'local_eledia_exam2pdf'),
        get_string('manage_col_actions', 'local_eledia_exam2pdf'),
    ];
    $table->attributes  = ['class' => 'generaltable'];
    $table->colclasses  = ['', '', 'text-center'];

    foreach ($entries as $entry) {
        $label = get_string('report_download_one', 'local_eledia_exam2pdf');
        $icon  = $OUTPUT->pix_icon('i/download', $label);
        $btn   = html_writer::link(
            $entry->downloadurl,
            $icon,
            [
                'class'      => 'btn btn-sm btn-outline-primary',
                'aria-label' => $label,
                'title'      => $label,
            ]
        );

        $table->data[] = [
            s($entry->fullname),
            userdate($entry->record->timecreated),
            $btn,
        ];
    }

    echo html_writer::table($table);

    // Bulk ZIP download button below the table.
    $zipurl   = new moodle_url('/local/eledia_exam2pdf/zip.php', ['cmid' => $cmid]);
    $ziplabel = get_string('report_download_zip', 'local_eledia_exam2pdf');
    echo html_writer::div(
        html_writer::link($zipurl, $ziplabel, ['class' => 'btn btn-primary']),
        'mt-3'
    );
}

echo $OUTPUT->footer();
