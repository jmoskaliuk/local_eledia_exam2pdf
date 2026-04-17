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
 * PDF certificate download page.
 *
 * URL:
 * - /local/eledia_exam2pdf/download.php?id=<record_id>
 * - /local/eledia_exam2pdf/download.php?attemptid=<attempt_id> (on-demand mode)
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

if (empty($id) && empty($attemptid)) {
    throw new \invalid_parameter_exception('Missing required parameter: id or attemptid.');
}

// Resolve record and attempt.
$record = null;
if ($id) {
    $record = $DB->get_record('local_eledia_exam2pdf', ['id' => $id], '*', MUST_EXIST);
    $attemptid = (int) $record->attemptid;
}

$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);

if (!$record) {
    $record = $DB->get_record(
        'local_eledia_exam2pdf',
        ['attemptid' => $attemptid],
        '*',
        IGNORE_MISSING
    );
}

$cm      = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
$context = \core\context\module::instance($cm->id);
$course  = get_course($cm->course);

require_login($course, false, $cm);

$config = \local_eledia_exam2pdf\helper::get_effective_config((int) $quiz->id);
$candownloadall = \local_eledia_exam2pdf\helper::has_downloadall_capability($context);
$candownloadown = \local_eledia_exam2pdf\helper::has_downloadown_capability($context);
$isownerattempt = ((int) $attempt->userid === (int) $USER->id);

// Enforce owner/downloadown + studentdownload for learner self-service.
if (!$candownloadall) {
    if (!$isownerattempt || !$candownloadown) {
        notice(get_string('download_nopermission', 'local_eledia_exam2pdf'));
    }
    if (empty($config['studentdownload'])) {
        notice(get_string('download_nopermission', 'local_eledia_exam2pdf'));
    }
}

// On-demand fallback: create missing PDF on click (if enabled).
if (!$record) {
    $mode = $config['pdfgeneration'] ?? 'auto';
    $cangenerate = \local_eledia_exam2pdf\helper::has_generatepdf_capability($context)
        || ($isownerattempt && $candownloadown);

    if ($mode !== 'ondemand' || !$cangenerate) {
        notice(get_string('download_notavailable', 'local_eledia_exam2pdf'));
    }

    if (!\local_eledia_exam2pdf\helper::is_in_pdf_scope($attempt, $quiz, $config)) {
        notice(get_string('download_button_notpassed', 'local_eledia_exam2pdf'));
    }

    $record = \local_eledia_exam2pdf\observer::ensure_pdf_for_attempt((int) $attempt->id, true);
    if (!$record) {
        notice(get_string('download_notavailable', 'local_eledia_exam2pdf'));
    }
}

// Final ownership guard for record-based downloads.
if (!$candownloadall && (int) $record->userid !== (int) $USER->id) {
    notice(get_string('download_nopermission', 'local_eledia_exam2pdf'));
}

// Retrieve the stored file.
$file = \local_eledia_exam2pdf\helper::get_stored_file($record);
if (!$file) {
    notice(get_string('download_notavailable', 'local_eledia_exam2pdf'));
}

// Serve the file as a forced download.
send_stored_file($file, 0, 0, true);
