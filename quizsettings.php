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
require_once($CFG->libdir . '/formslib.php');

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

// Define the settings form inline.
class local_eledia_exam2pdf_quizsettings_form extends moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('quizsettings_heading', 'local_eledia_exam2pdf'));

        // Output mode.
        $mform->addElement('select', 'outputmode',
            get_string('setting_outputmode', 'local_eledia_exam2pdf'),
            [
                ''         => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
                'download' => get_string('outputmode_download', 'local_eledia_exam2pdf'),
                'email'    => get_string('outputmode_email', 'local_eledia_exam2pdf'),
                'both'     => get_string('outputmode_both', 'local_eledia_exam2pdf'),
            ]
        );

        // Email recipients.
        $mform->addElement('text', 'emailrecipients',
            get_string('setting_emailrecipients', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->setType('emailrecipients', PARAM_TEXT);
        $mform->addHelpButton('emailrecipients', 'setting_emailrecipients', 'local_eledia_exam2pdf');

        // Email subject.
        $mform->addElement('text', 'emailsubject',
            get_string('setting_emailsubject', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->setType('emailsubject', PARAM_TEXT);

        // Retention days.
        $mform->addElement('text', 'retentiondays',
            get_string('setting_retentiondays', 'local_eledia_exam2pdf'),
            ['size' => 6]
        );
        $mform->setType('retentiondays', PARAM_INT);
        $mform->addHelpButton('retentiondays', 'setting_retentiondays', 'local_eledia_exam2pdf');

        // Show correct answers.
        $mform->addElement('advcheckbox', 'showcorrectanswers',
            get_string('setting_showcorrectanswers', 'local_eledia_exam2pdf')
        );

        // Optional header fields.
        $mform->addElement('header', 'optfields',
            get_string('setting_optionalfields_heading', 'local_eledia_exam2pdf')
        );

        foreach (['score', 'passgrade', 'percentage', 'timestamp', 'duration', 'attemptnumber'] as $field) {
            $mform->addElement('advcheckbox', 'show_' . $field,
                get_string('setting_show_' . $field, 'local_eledia_exam2pdf')
            );
        }

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $this->add_action_buttons();
    }
}

$form = new local_eledia_exam2pdf_quizsettings_form(null, null, 'post', '', null, true, ['cmid' => $cmid]);

// Pre-populate form with current effective config.
$formdefaults = (array) $currentconfig;
$formdefaults['cmid'] = $cmid;
$form->set_data($formdefaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]));

} else if ($data = $form->get_data()) {
    // Save only values that differ from global defaults (null = inherit).
    $globalconfig = [
        'outputmode'         => get_config('local_eledia_exam2pdf', 'outputmode'),
        'emailrecipients'    => get_config('local_eledia_exam2pdf', 'emailrecipients'),
        'emailsubject'       => get_config('local_eledia_exam2pdf', 'emailsubject'),
        'retentiondays'      => get_config('local_eledia_exam2pdf', 'retentiondays'),
        'showcorrectanswers' => get_config('local_eledia_exam2pdf', 'showcorrectanswers'),
    ];

    $toSave = [];
    foreach (['outputmode', 'emailrecipients', 'emailsubject', 'retentiondays',
              'showcorrectanswers', 'show_score', 'show_passgrade', 'show_percentage',
              'show_timestamp', 'show_duration', 'show_attemptnumber'] as $key) {
        $val = $data->$key ?? null;
        // Store empty string to signal "inherit global default".
        $toSave[$key] = ($val === '') ? null : $val;
    }

    \local_eledia_exam2pdf\helper::save_quiz_config($quiz->id, $toSave);

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
