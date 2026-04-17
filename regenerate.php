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
 * Force-regenerate one quiz attempt PDF.
 *
 * URL: /local/eledia_exam2pdf/regenerate.php?attemptid=<attemptid>&cmid=<cmid>&sesskey=<sesskey>
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$attemptid = required_param('attemptid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$returnurlraw = optional_param('returnurl', '', PARAM_LOCALURL);
require_sesskey();

$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);

if ($cmid > 0) {
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    if ((int) $cm->instance !== (int) $quiz->id) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else {
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
}

$course = get_course($cm->course);
$context = \core\context\module::instance($cm->id);
require_login($course, false, $cm);

if (!\local_eledia_exam2pdf\helper::has_generatepdf_capability($context)) {
    throw new required_capability_exception(
        $context,
        'local/eledia_exam2pdf:generatepdf',
        'nopermissions',
        ''
    );
}

$defaultreturnurl = new \moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'overview']);
$returnurl = $returnurlraw !== '' ? new \moodle_url($returnurlraw) : $defaultreturnurl;

if (($attempt->state ?? '') !== 'finished') {
    redirect(
        $returnurl,
        get_string('download_notavailable', 'local_eledia_exam2pdf'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

try {
    $record = \local_eledia_exam2pdf\observer::ensure_pdf_for_attempt((int) $attempt->id, true, true);
    if ($record) {
        redirect(
            $returnurl,
            get_string('report_regenerate_success', 'local_eledia_exam2pdf'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
} catch (\Throwable $e) {
    debugging(
        'local_eledia_exam2pdf: regenerate failed for attempt '
            . (int) $attempt->id . ': ' . $e->getMessage(),
        DEBUG_DEVELOPER
    );
}

redirect(
    $returnurl,
    get_string('report_regenerate_failed', 'local_eledia_exam2pdf'),
    null,
    \core\output\notification::NOTIFY_ERROR
);
