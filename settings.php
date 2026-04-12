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
 * Global admin settings for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_eledia_exam2pdf',
        get_string('pluginname', 'local_eledia_exam2pdf')
    );

    $ADMIN->add('localplugins', $settings);

    // PDF generation section.
    $settings->add(new admin_setting_heading(
        'local_eledia_exam2pdf/heading_generation',
        get_string('setting_heading_generation', 'local_eledia_exam2pdf'),
        get_string('setting_heading_generation_desc', 'local_eledia_exam2pdf')
    ));

    // PDF generation mode (auto / ondemand).
    $settings->add(new admin_setting_configselect(
        'local_eledia_exam2pdf/pdfgeneration',
        get_string('setting_pdfgeneration', 'local_eledia_exam2pdf'),
        get_string('setting_pdfgeneration_desc', 'local_eledia_exam2pdf'),
        'auto',
        [
            'auto'     => get_string('pdfgeneration_auto', 'local_eledia_exam2pdf'),
            'ondemand' => get_string('pdfgeneration_ondemand', 'local_eledia_exam2pdf'),
        ]
    ));

    // PDF scope (passed / all).
    $settings->add(new admin_setting_configselect(
        'local_eledia_exam2pdf/pdfscope',
        get_string('setting_pdfscope', 'local_eledia_exam2pdf'),
        get_string('setting_pdfscope_desc', 'local_eledia_exam2pdf'),
        'passed',
        [
            'passed' => get_string('pdfscope_passed', 'local_eledia_exam2pdf'),
            'all'    => get_string('pdfscope_all', 'local_eledia_exam2pdf'),
        ]
    ));

    // Student may download.
    $settings->add(new admin_setting_configcheckbox(
        'local_eledia_exam2pdf/studentdownload',
        get_string('setting_studentdownload', 'local_eledia_exam2pdf'),
        get_string('setting_studentdownload_desc', 'local_eledia_exam2pdf'),
        1
    ));

    // Bulk download format.
    $settings->add(new admin_setting_configselect(
        'local_eledia_exam2pdf/bulkformat',
        get_string('setting_bulkformat', 'local_eledia_exam2pdf'),
        get_string('setting_bulkformat_desc', 'local_eledia_exam2pdf'),
        'zip',
        [
            'zip'    => get_string('bulkformat_zip', 'local_eledia_exam2pdf'),
            'merged' => get_string('bulkformat_merged', 'local_eledia_exam2pdf'),
        ]
    ));

    // Output mode.
    $settings->add(new admin_setting_configselect(
        'local_eledia_exam2pdf/outputmode',
        get_string('setting_outputmode', 'local_eledia_exam2pdf'),
        get_string('setting_outputmode_desc', 'local_eledia_exam2pdf'),
        'download',
        [
            'download' => get_string('outputmode_download', 'local_eledia_exam2pdf'),
            'email'    => get_string('outputmode_email', 'local_eledia_exam2pdf'),
            'both'     => get_string('outputmode_both', 'local_eledia_exam2pdf'),
        ]
    ));

    // Default email recipients (comma-separated).
    $settings->add(new admin_setting_configtext(
        'local_eledia_exam2pdf/emailrecipients',
        get_string('setting_emailrecipients', 'local_eledia_exam2pdf'),
        get_string('setting_emailrecipients_desc', 'local_eledia_exam2pdf'),
        '',
        PARAM_TEXT
    ));

    // Default email subject.
    $settings->add(new admin_setting_configtext(
        'local_eledia_exam2pdf/emailsubject',
        get_string('setting_emailsubject', 'local_eledia_exam2pdf'),
        get_string('setting_emailsubject_desc', 'local_eledia_exam2pdf'),
        get_string('email_subject_default', 'local_eledia_exam2pdf'),
        PARAM_TEXT
    ));

    // Retention period in days.
    $settings->add(new admin_setting_configtext(
        'local_eledia_exam2pdf/retentiondays',
        get_string('setting_retentiondays', 'local_eledia_exam2pdf'),
        get_string('setting_retentiondays_desc', 'local_eledia_exam2pdf'),
        '365',
        PARAM_INT
    ));

    // Optional PDF fields.
    $settings->add(new admin_setting_heading(
        'local_eledia_exam2pdf/optionalfields_heading',
        get_string('setting_optionalfields_heading', 'local_eledia_exam2pdf'),
        get_string('setting_optionalfields_heading_desc', 'local_eledia_exam2pdf')
    ));

    foreach (['score', 'passgrade', 'percentage', 'timestamp', 'duration', 'attemptnumber'] as $field) {
        $settings->add(new admin_setting_configcheckbox(
            'local_eledia_exam2pdf/show_' . $field,
            get_string('setting_show_' . $field, 'local_eledia_exam2pdf'),
            '',
            1
        ));
    }

    // Show correct answers in PDF.
    $settings->add(new admin_setting_configcheckbox(
        'local_eledia_exam2pdf/showcorrectanswers',
        get_string('setting_showcorrectanswers', 'local_eledia_exam2pdf'),
        get_string('setting_showcorrectanswers_desc', 'local_eledia_exam2pdf'),
        1
    ));
}
