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
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for the per-quiz PDF certificate settings.
 *
 * Lets teachers override the global PDF defaults for a single quiz
 * (output mode, student download, email recipients, retention period,
 * optional header fields).
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
        $mform->addHelpButton('general', 'quizsettings_info', 'local_eledia_exam2pdf');

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
        $mform->addHelpButton('outputmode', 'setting_outputmode', 'local_eledia_exam2pdf');

        // PDF language — 3-state: inherit global / site default / explicit language pack.
        $pdflanguageoptions = [
            '' => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
            'site' => get_string('setting_pdflanguage_site', 'local_eledia_exam2pdf'),
        ] + get_string_manager()->get_list_of_translations();
        $mform->addElement(
            'select',
            'pdflanguage',
            get_string('setting_pdflanguage', 'local_eledia_exam2pdf'),
            $pdflanguageoptions
        );
        $mform->addHelpButton('pdflanguage', 'setting_pdflanguage', 'local_eledia_exam2pdf');
        $mform->setType('pdflanguage', PARAM_ALPHANUMEXT);

        // PDF footer text.
        $mform->addElement(
            'textarea',
            'pdffootertext',
            get_string('setting_pdffootertext', 'local_eledia_exam2pdf'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->addHelpButton('pdffootertext', 'setting_pdffootertext', 'local_eledia_exam2pdf');
        $mform->setType('pdffootertext', PARAM_TEXT);

        // Student may download — 3-state: inherit global / yes / no.
        $mform->addElement(
            'select',
            'studentdownload',
            get_string('setting_studentdownload', 'local_eledia_exam2pdf'),
            [
                ''  => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
                '1' => get_string('yes'),
                '0' => get_string('no'),
            ]
        );
        $mform->addHelpButton('studentdownload', 'setting_studentdownload', 'local_eledia_exam2pdf');

        // Student receives evaluation by e-mail — 3-state: inherit global / yes / no.
        $mform->addElement(
            'select',
            'studentemail',
            get_string('setting_studentemail', 'local_eledia_exam2pdf'),
            [
                ''  => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
                '1' => get_string('yes'),
                '0' => get_string('no'),
            ]
        );
        $mform->addHelpButton('studentemail', 'setting_studentemail', 'local_eledia_exam2pdf');

        // Include grading comments per question in PDF — 3-state override.
        $mform->addElement(
            'select',
            'showquestioncomments',
            get_string('setting_showquestioncomments', 'local_eledia_exam2pdf'),
            [
                ''  => get_string('quizsettings_inherit', 'local_eledia_exam2pdf'),
                '1' => get_string('yes'),
                '0' => get_string('no'),
            ]
        );
        $mform->addHelpButton('showquestioncomments', 'setting_showquestioncomments', 'local_eledia_exam2pdf');

        // Email recipients.
        $mform->addElement(
            'text',
            'emailrecipients',
            get_string('setting_emailrecipients', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->addHelpButton('emailrecipients', 'setting_emailrecipients', 'local_eledia_exam2pdf');
        $mform->setType('emailrecipients', PARAM_TEXT);

        // Email subject.
        $mform->addElement(
            'text',
            'emailsubject',
            get_string('setting_emailsubject', 'local_eledia_exam2pdf'),
            ['size' => 60]
        );
        $mform->addHelpButton('emailsubject', 'setting_emailsubject', 'local_eledia_exam2pdf');
        $mform->setType('emailsubject', PARAM_TEXT);

        // Retention days.
        $mform->addElement(
            'text',
            'retentiondays',
            get_string('setting_retentiondays', 'local_eledia_exam2pdf'),
            ['size' => 6]
        );
        $mform->addHelpButton('retentiondays', 'setting_retentiondays', 'local_eledia_exam2pdf');
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
