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
 * Bulk PDF certificate download for the quiz overview report.
 *
 * URL: /local/eledia_exam2pdf/zip.php?cmid=<cmid>
 *
 * Streams either a ZIP archive or one merged PDF (depending on config)
 * for all PDF certificates of a quiz. In on-demand mode, missing PDFs can
 * be generated during the download flow.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$quiz    = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, course', MUST_EXIST);
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

$config = \local_eledia_exam2pdf\helper::get_effective_config((int) $quiz->id);
$bulkformat = $config['bulkformat'] ?? 'zip';

$entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($cm->id);

// In on-demand mode, generate any missing PDFs on bulk-download click.
if (
    ($config['pdfgeneration'] ?? 'auto') === 'ondemand'
    && \local_eledia_exam2pdf\helper::has_generatepdf_capability($context)
) {
    $attempts = $DB->get_records_sql(
        'SELECT * FROM {quiz_attempts}
          WHERE quiz = :quizid
            AND state = :state
            AND preview = 0
       ORDER BY id ASC',
        [
            'quizid' => $quiz->id,
            'state' => 'finished',
        ]
    );

    foreach ($attempts as $attempt) {
        if (!\local_eledia_exam2pdf\helper::is_in_pdf_scope($attempt, $quiz, $config)) {
            continue;
        }
        try {
            \local_eledia_exam2pdf\observer::ensure_pdf_for_attempt((int) $attempt->id, true);
        } catch (\Throwable $e) {
            debugging(
                'local_eledia_exam2pdf: on-demand bulk generation failed for attempt '
                    . $attempt->id . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    $entries = \local_eledia_exam2pdf\helper::get_quiz_pdfs($cm->id);
}

if (empty($entries)) {
    notice(get_string('report_zip_nofiles', 'local_eledia_exam2pdf'));
}

$datesuffix = userdate(time(), '%Y-%m-%d');

if ($bulkformat === 'merged') {
    $attemptids = array_map(static fn($entry): int => (int) $entry->record->attemptid, $entries);
    $mergedcontent = \local_eledia_exam2pdf\pdf\generator::generate_merged($attemptids, $quiz, $config);
    if ($mergedcontent === '') {
        notice(get_string('report_zip_nofiles', 'local_eledia_exam2pdf'));
    }

    $tmppdf = tempnam(make_request_directory(), 'exam2pdf_');
    file_put_contents($tmppdf, $mergedcontent);
    $mergedname = clean_filename('certificates_' . $quiz->name . '_' . $datesuffix . '.pdf');
    send_temp_file($tmppdf, $mergedname);
    exit;
}

// Default: ZIP download.
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

$archivename = clean_filename('certificates_' . $quiz->name . '_' . $datesuffix . '.zip');
send_temp_file($tmpzip, $archivename);
