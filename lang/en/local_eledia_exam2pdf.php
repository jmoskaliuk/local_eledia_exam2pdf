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
 * English language strings for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bulkformat_merged'] = 'One merged PDF';
$string['bulkformat_zip'] = 'ZIP with individual PDFs';
$string['download_button'] = 'Download certificate';
$string['download_button_notpassed'] = 'Certificate not available (attempt not passed)';
$string['download_heading'] = 'Download PDF Certificate';
$string['download_nopermission'] = 'You do not have permission to download this certificate.';
$string['download_notavailable'] = 'The PDF certificate is not available. '
    . 'The attempt may not have been passed, or the file may have been deleted.';
$string['eledia_exam2pdf:configure'] = 'Configure per-quiz PDF settings';
$string['eledia_exam2pdf:downloadall'] = 'Download all PDF certificates for a quiz';
$string['eledia_exam2pdf:downloadown'] = 'Download own quiz PDF certificate';
$string['eledia_exam2pdf:generatepdf'] = 'Generate or regenerate PDF certificates';
$string['eledia_exam2pdf:manage'] = 'Manage all quiz PDF certificates';
$string['email_body'] = 'Dear {fullname},

please find attached the PDF certificate for your passed attempt in quiz "{quizname}".

This certificate was generated automatically on {date}.';
$string['email_subject_default'] = 'Quiz certificate: {quizname}';
$string['error_attempt_not_found'] = 'Attempt not found.';
$string['error_pdf_generation_failed'] = 'PDF generation failed. Please contact your administrator.';
$string['error_quiz_not_found'] = 'Quiz not found.';
$string['manage_col_actions'] = 'Actions';
$string['manage_col_attempt'] = 'Attempt';
$string['manage_col_expires'] = 'Expires';
$string['manage_col_learner'] = 'Learner';
$string['manage_col_timecreated'] = 'Generated';
$string['manage_expires_never'] = 'Never';
$string['manage_heading'] = 'PDF Certificates for this quiz';
$string['manage_norecords'] = 'No PDF certificates have been generated yet.';
$string['outputmode_both'] = 'Download and email';
$string['outputmode_download'] = 'Download';
$string['outputmode_email'] = 'Email';
$string['pdf_attemptnumber'] = 'Attempt number';
$string['pdf_correctanswer'] = 'Correct answer';
$string['pdf_duration'] = 'Duration';
$string['pdf_name'] = 'Learner';
$string['pdf_noanswer'] = '(no answer given)';
$string['pdf_nocorrectanswer'] = '(no correct answer defined)';
$string['pdf_passed'] = 'Passed';
$string['pdf_passed_no'] = 'No';
$string['pdf_passed_yes'] = 'Yes';
$string['pdf_passgrade'] = 'Pass threshold';
$string['pdf_percentage'] = 'Percentage';
$string['pdf_question'] = 'Question';
$string['pdf_questions_heading'] = 'Questions and Answers';
$string['pdf_quiz'] = 'Quiz';
$string['pdf_result_correct'] = 'Correct';
$string['pdf_result_incorrect'] = 'Incorrect';
$string['pdf_result_partial'] = 'Partially correct';
$string['pdf_score'] = 'Score';
$string['pdf_timestamp'] = 'Completed on';
$string['pdf_title'] = 'Quiz Completion Certificate';
$string['pdf_youranswer'] = 'Your answer';
$string['pdfgeneration_auto'] = 'On submission (automatic)';
$string['pdfgeneration_ondemand'] = 'On demand (on click)';
$string['pdfscope_all'] = 'All finished attempts';
$string['pdfscope_passed'] = 'Passed attempts only';
$string['pluginname'] = 'eLeDia | exam2pdf';
$string['privacy:metadata:core_files'] = 'The PDF file containing the learner\'s name '
    . 'and quiz answers is stored in the Moodle file system.';
$string['privacy:metadata:local_eledia_exam2pdf'] = 'Stores one record per passed quiz attempt, '
    . 'linking the learner to the generated PDF certificate.';
$string['privacy:metadata:local_eledia_exam2pdf:attemptid'] = 'The ID of the quiz attempt.';
$string['privacy:metadata:local_eledia_exam2pdf:quizid'] = 'The ID of the quiz.';
$string['privacy:metadata:local_eledia_exam2pdf:timecreated'] = 'The time the PDF certificate was generated.';
$string['privacy:metadata:local_eledia_exam2pdf:timeexpires'] = 'The time after which the PDF '
    . 'will be automatically deleted (0 = never).';
$string['privacy:metadata:local_eledia_exam2pdf:userid'] = 'The ID of the learner who completed the attempt.';
$string['quizsettings'] = 'PDF Certificate settings';
$string['quizsettings_heading'] = 'PDF Certificate settings for this quiz';
$string['quizsettings_inherit'] = 'Use global default';
$string['quizsettings_saved'] = 'Settings saved successfully.';
$string['report_download_one'] = 'Download PDF';
$string['report_download_zip'] = 'Download all as ZIP';
$string['report_heading'] = 'PDF Certificates';
$string['report_nav_link'] = 'PDF Certificates';
$string['report_section_heading'] = 'PDF certificates';
$string['report_section_intro'] = 'Generated PDF certificates for this quiz. '
    . 'Click an icon to download a single certificate, '
    . 'or use the button below to download all certificates as a ZIP archive.';
$string['report_zip_filename'] = 'certificates_{quizname}_{date}.zip';
$string['report_zip_nofiles'] = 'No PDF certificates available to download.';
$string['setting_bulkformat'] = 'Bulk download format';
$string['setting_bulkformat_desc'] = 'What format is used when downloading multiple PDF certificates at once.';
$string['setting_emailrecipients'] = 'Default e-mail recipients';
$string['setting_emailrecipients_desc'] = 'Comma-separated list of e-mail addresses. '
    . 'The learner\'s own address is always included. Can be overridden per quiz.';
$string['setting_emailsubject'] = 'Default e-mail subject';
$string['setting_emailsubject_desc'] = 'Subject line for the notification e-mail. '
    . 'Supports {quizname} and {username} placeholders.';
$string['setting_heading_generation'] = 'PDF generation';
$string['setting_heading_generation_desc'] = 'Configure when and for which attempts PDF certificates are generated.';
$string['setting_optionalfields_heading'] = 'Optional PDF header fields';
$string['setting_optionalfields_heading_desc'] = 'Choose which optional data points appear in the PDF header.';
$string['setting_outputmode'] = 'Output mode';
$string['setting_outputmode_desc'] = 'How the PDF is made available after a passed attempt. Can be overridden per quiz.';
$string['setting_pdfgeneration'] = 'PDF generation mode';
$string['setting_pdfgeneration_desc'] = 'When to generate the PDF certificate. '
    . '"On submission" creates it automatically when a quiz attempt is submitted. '
    . '"On demand" only creates it when a teacher clicks the download button.';
$string['setting_pdfscope'] = 'PDF scope';
$string['setting_pdfscope_desc'] = 'Which attempts are eligible for PDF generation. '
    . '"Passed only" requires the attempt to meet the pass grade. '
    . '"All finished" generates a PDF for every completed attempt.';
$string['setting_retentiondays'] = 'Retention period';
$string['setting_retentiondays_desc'] = 'Number of days the PDF is stored after the attempt was passed. '
    . 'Set to 0 to keep indefinitely. Can be overridden per quiz.';
$string['setting_show_attemptnumber'] = 'Show attempt number on PDF';
$string['setting_show_duration'] = 'Show duration on PDF';
$string['setting_show_passgrade'] = 'Show pass grade on PDF';
$string['setting_show_percentage'] = 'Show percentage on PDF';
$string['setting_show_score'] = 'Show score on PDF';
$string['setting_show_timestamp'] = 'Show timestamp on PDF';
$string['setting_showcorrectanswers'] = 'Show correct answers in PDF';
$string['setting_showcorrectanswers_desc'] = 'If enabled, the correct answer is shown '
    . 'alongside the learner\'s response where available.';
$string['setting_studentdownload'] = 'Student may download';
$string['setting_studentdownload_desc'] = 'If enabled, students can download their own PDF certificate '
    . 'from the quiz review page. '
    . 'If disabled, only teachers can access PDFs via the report page.';
$string['task_cleanup'] = 'Delete expired PDF certificates';
