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
 * Bulk PDF certificate ZIP download for the quiz overview report.
 *
 * URL: /local/eledia_exam2pdf/zip.php?cmid=<cmid>
 *
 * Streams a ZIP archive containing all PDF certificates that exist for the
 * given quiz course module. Requires the manage capability.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$context = \core\context\module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/eledia_exam2pdf:manage', $context);

$entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($cm->id);
if (empty($entries)) {
    notice(get_string('report_zip_nofiles', 'local_eledia_exam2pdf'));
}

// Build the ZIP in a temp file, then stream it.
$tmpzip = tempnam(make_request_directory(), 'exam2pdf_');
$zip = new \ZipArchive();
if ($zip->open($tmpzip, \ZipArchive::OVERWRITE) !== true) {
    throw new \moodle_exception('error_pdf_generation_failed', 'local_eledia_exam2pdf');
}

// Track filenames to avoid duplicates inside the archive.
$used = [];
foreach ($entries as $entry) {
    $file = $entry->file;
    $base = clean_filename($entry->fullname . '_' . $file->get_filename());
    if (isset($used[$base])) {
        $used[$base]++;
        $info = pathinfo($base);
        $base = $info['filename'] . '_' . $used[$base]
            . (!empty($info['extension']) ? '.' . $info['extension'] : '');
    } else {
        $used[$base] = 0;
    }
    $zip->addFromString($base, $file->get_content());
}
$zip->close();

// Build a friendly archive filename: certificates_<quizname>_<YYYY-MM-DD>.zip.
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name', MUST_EXIST);
$archivename = clean_filename(
    'certificates_' . $quiz->name . '_' . userdate(time(), '%Y-%m-%d') . '.zip'
);

send_temp_file($tmpzip, $archivename);
