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
 * URL: /local/eledia_exam2pdf/download.php?id=<record_id>
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_eledia_exam2pdf', ['id' => $id], '*', MUST_EXIST);

// Determine context from the CM ID stored with the record.
$cm      = get_coursemodule_from_id('quiz', $record->cmid, 0, false, MUST_EXIST);
$context = \core\context\module::instance($cm->id);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);

// Access check: own record OR manage capability.
$canmanage = has_capability('local/eledia_exam2pdf:manage', $context);
if (!$canmanage && $record->userid != $USER->id) {
    notice(get_string('download_nopermission', 'local_eledia_exam2pdf'));
}

// Retrieve the stored file.
$file = \local_eledia_exam2pdf\helper::get_stored_file($record);
if (!$file) {
    notice(get_string('download_notavailable', 'local_eledia_exam2pdf'));
}

// Serve the file as a forced download.
send_stored_file($file, 0, 0, true);
