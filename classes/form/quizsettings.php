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
 * Per-quiz PDF certificate settings form.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for the per-quiz PDF certificate settings.
 *
 * Lets teachers override the global PDF defaults for a single quiz
 * (output mode, email recipients, retention period, optional header fields).
 */
class quizsettings extends \moodleform {

    /**
     * Defines all form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('quizsettings_heading', 'local_eledia_exam2pdf'));

        // Output mode.
        $mform->addElement(
            'select',
            'outputmode',
            get_string('setting_outputmode', 'local_eledia_exam2pdf'),
            [
                ''         => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
                'download' => get_string('outputmode_download', 'local_eledia_exam2pdf'),
                'email'    => get_string('outputmode_email', 'local_eledia_exam2pdf'),
                'both'     => get_string('outputmode_both', 'local_eledia_exam2pdf'),
            ]
        );

        // Email recipients.
        $mform->addElement(
            'text',
            'emailrecipients',
            get_string('setting_emailrecipients', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->setType('emailrecipients', PARAM_TEXT);

        // Email subject.
        $mform->addElement(
            'text',
            'emailsubject',
            get_string('setting_emailsubject', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->setType('emailsubject', PARAM_TEXT);

        // Retention days.
        $mform->addElement(
            'text',
            'retentiondays',
            get_string('setting_retentiondays', 'local_eledia_exam2pdf'),
            ['size' => 6]
        );
        $mform->setType('retentiondays', PARAM_INT);

        // Show correct answers.
        $mform->addElement(
            'advcheckbox',
            'showcorrectanswers',
            get_string('setting_showcorrectanswers', 'local_eledia_exam2pdf')
        );

        // Optional header fields.
        $mform->addElement(
            'header',
            'optfields',
            get_string('setting_optionalfields_heading', 'local_eledia_exam2pdf')
        );

        foreach (['score', 'passgrade', 'percentage', 'timestamp', 'duration', 'attemptnumber'] as $field) {
            $mform->addElement(
                'advcheckbox',
                'show_' . $field,
                get_string('setting_show_' . $field, 'local_eledia_exam2pdf')
            );
        }

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $this->add_action_buttons();
    }
}
