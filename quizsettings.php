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
 * Per-quiz PDF certificate configuration page.
 *
 * URL: /local/eledia_exam2pdf/quizsettings.php?cmid=<cmid>
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$context = \core\context\module::instance($cm->id);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('local/eledia_exam2pdf:configure', $context);

$PAGE->set_url('/local/eledia_exam2pdf/quizsettings.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('quizsettings', 'local_eledia_exam2pdf'));
$PAGE->set_heading(format_string($quiz->name));

// Load current per-quiz config (already merged with globals for display).
$currentconfig = \local_eledia_exam2pdf\helper::get_effective_config($quiz->id);

$form = new \local_eledia_exam2pdf\form\quizsettings(null, null, 'post', '', null, true, ['cmid' => $cmid]);

// Pre-populate form with current effective config.
$formdefaults = (array) $currentconfig;
$formdefaults['cmid'] = $cmid;
$form->set_data($formdefaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]));
} else if ($data = $form->get_data()) {
    $tosave = [];
    $keys = [
        'outputmode',
        'emailrecipients',
        'emailsubject',
        'retentiondays',
        'showcorrectanswers',
        'show_score',
        'show_passgrade',
        'show_percentage',
        'show_timestamp',
        'show_duration',
        'show_attemptnumber',
    ];
    foreach ($keys as $key) {
        $val = $data->$key ?? null;
        // Store empty string to signal "inherit global default".
        $tosave[$key] = ($val === '') ? null : $val;
    }

    \local_eledia_exam2pdf\helper::save_quiz_config($quiz->id, $tosave);

    redirect(
        new moodle_url('/local/eledia_exam2pdf/quizsettings.php', ['cmid' => $cmid]),
        get_string('quizsettings_saved', 'local_eledia_exam2pdf'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizsettings_heading', 'local_eledia_exam2pdf'));
$form->display();
echo $OUTPUT->footer();
