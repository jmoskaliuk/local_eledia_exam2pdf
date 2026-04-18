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
$string['download_button'] = 'Download evaluation';
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
$string['manage_col_expires'] = 'Expires';
$string['manage_col_learner'] = 'Learner';
$string['manage_col_timecreated'] = 'Generated';
$string['manage_expires_never'] = 'Never';
$string['manage_heading'] = 'exam2pdf for this quiz';
$string['manage_norecords'] = 'No PDF certificates have been generated yet.';
$string['outputmode_both'] = 'Download and email';
$string['outputmode_download'] = 'Download';
$string['outputmode_email'] = 'Email';
$string['pdf_attempt_block'] = 'Attempt';
$string['pdf_attempt_hash'] = 'Attempt #{$a}';
$string['pdf_attemptnumber'] = 'Attempt number';
$string['pdf_comment_by'] = '— {$a->grader}, {$a->date}';
$string['pdf_comment_label'] = 'Grading comment';
$string['pdf_context_block'] = 'Quiz context';
$string['pdf_correctanswer'] = 'Correct answer';
$string['pdf_cover_title'] = 'Quiz evaluation';
$string['pdf_duration'] = 'Duration';
$string['pdf_moodleid'] = 'Moodle ID';
$string['pdf_name'] = 'Learner';
$string['pdf_nav_legend_all'] = '{$a} questions';
$string['pdf_nav_legend_correct'] = 'correct {$a}';
$string['pdf_nav_legend_partial'] = 'partial {$a}';
$string['pdf_nav_legend_pending'] = 'awaiting {$a}';
$string['pdf_nav_legend_wrong'] = 'wrong {$a}';
$string['pdf_navigation_heading'] = 'Questions overview';
$string['pdf_noanswer'] = '(no answer given)';
$string['pdf_nocorrectanswer'] = '(no correct answer defined)';
$string['pdf_participant_block'] = 'Participant';
$string['pdf_passed'] = 'Passed';
$string['pdf_passgrade'] = 'Pass threshold';
$string['pdf_pending_note'] = 'This question is awaiting manual grading.';
$string['pdf_pending_questions'] = '{$a} question(s) awaiting grading';
$string['pdf_percentage'] = 'Percentage';
$string['pdf_qtype_hint_essay'] = 'Free-text answer — graded manually by the teacher.';
$string['pdf_qtype_hint_essay_pending'] = 'Free-text answer — awaiting manual grading by the teacher.';
$string['pdf_qtype_hint_multichoice_multi'] = 'Multiple choice — select all correct options.';
$string['pdf_qtype_hint_multichoice_single'] = 'Single choice.';
$string['pdf_qtype_hint_numerical'] = 'Numerical answer.';
$string['pdf_qtype_hint_shortanswer'] = 'Short answer.';
$string['pdf_qtype_hint_truefalse'] = 'True or false?';
$string['pdf_question'] = 'Question';
$string['pdf_question_comment'] = 'Grading comment';
$string['pdf_question_score'] = 'Question score';
$string['pdf_questions_heading'] = 'Questions and Answers';
$string['pdf_questions_section_heading'] = 'Questions & Answers · {$a} questions';
$string['pdf_quiz'] = 'Quiz';
$string['pdf_result_correct'] = 'Correct';
$string['pdf_result_incorrect'] = 'Incorrect';
$string['pdf_result_partial'] = 'Partially correct';
$string['pdf_score'] = 'Score';
$string['pdf_score_points_label'] = 'Points earned';
$string['pdf_status_failed'] = 'Not passed';
$string['pdf_status_label'] = 'Status';
$string['pdf_status_passed'] = 'Passed';
$string['pdf_status_pending'] = 'Awaiting grading';
$string['pdf_timestamp'] = 'Completed on';
$string['pdf_title'] = 'Quiz Completion Certificate';
$string['pdf_youranswer'] = 'Learner answer';
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
$string['quizsettings'] = 'exam2pdf Settings';
$string['quizsettings_heading'] = 'exam2pdf Settings for this quiz';
$string['quizsettings_info'] = 'About exam2pdf Settings';
$string['quizsettings_info_help'] = 'These settings override the global exam2pdf defaults for this quiz only. '
    . 'Choose "Use global default" to inherit the site-wide settings.';
$string['quizsettings_inherit'] = 'Use global default';
$string['quizsettings_saved'] = 'Settings saved successfully.';
$string['report_col_completed'] = 'Completed';
$string['report_col_duration'] = 'Duration';
$string['report_col_grade'] = 'Grade';
$string['report_col_started'] = 'Started';
$string['report_download_merged'] = 'Download all as merged PDF';
$string['report_download_one'] = 'Download PDF';
$string['report_download_zip'] = 'Download all as ZIP';
$string['report_heading'] = 'exam2pdf';
$string['report_regenerate_failed'] = 'PDF could not be regenerated.';
$string['report_regenerate_one'] = 'Regenerate PDF';
$string['report_regenerate_success'] = 'PDF regenerated successfully.';
$string['report_section_heading'] = 'exam2pdf';
$string['report_section_intro'] = 'Download evaluations as .zip';
$string['report_zip_filename'] = 'certificates_{quizname}_{date}.zip';
$string['report_zip_nofiles'] = 'No PDF certificates available to download.';
$string['setting_bulkformat'] = 'Bulk download format';
$string['setting_bulkformat_desc'] = 'What format is used when downloading multiple PDF certificates at once.';
$string['setting_emailrecipients'] = 'Default e-mail recipients';
$string['setting_emailrecipients_desc'] = 'Comma-separated list of e-mail addresses. '
    . 'The learner\'s own address is always included. Can be overridden per quiz.';
$string['setting_emailrecipients_help'] = 'Comma-separated list of e-mail addresses that receive the PDF '
    . 'certificate in addition to the learner. The learner\'s own address is always included.';
$string['setting_emailsubject'] = 'Default e-mail subject';
$string['setting_emailsubject_desc'] = 'Subject line for the notification e-mail. '
    . 'Supports {quizname} and {username} placeholders.';
$string['setting_emailsubject_help'] = 'Subject line used for the notification e-mail that includes the PDF '
    . 'certificate. You can use the placeholders {quizname} and {username}.';
$string['setting_heading_generation'] = 'PDF generation';
$string['setting_heading_generation_desc'] = 'Configure when and for which attempts PDF certificates are generated.';
$string['setting_optionalfields_heading'] = 'Optional PDF header fields';
$string['setting_optionalfields_heading_desc'] = 'Choose which optional data points appear in the PDF header.';
$string['setting_outputmode'] = 'Output mode';
$string['setting_outputmode_desc'] = 'How the PDF is made available after a passed attempt. Can be overridden per quiz.';
$string['setting_outputmode_help'] = 'Defines how the PDF certificate is delivered after a passed attempt. '
    . '"Download" makes it available on the review page. "E-mail" sends it to the learner '
    . '(and additional recipients). "Download and email" combines both.';
$string['setting_pdffootertext'] = 'PDF footer text';
$string['setting_pdffootertext_desc'] = 'Optional text printed in the footer area of generated PDFs. '
    . 'Can be overridden per quiz.';
$string['setting_pdffootertext_help'] = 'Optional footer text shown on every page of generated PDFs. '
    . 'Leave empty to inherit the global default on quiz level.';
$string['setting_pdfgeneration'] = 'PDF generation mode';
$string['setting_pdfgeneration_desc'] = 'When to generate the PDF certificate. '
    . '"On submission" creates it automatically when a quiz attempt is submitted. '
    . '"On demand" only creates it when a teacher clicks the download button.';
$string['setting_pdflanguage'] = 'PDF language';
$string['setting_pdflanguage_desc'] = 'Language used for generated PDF texts and labels. Can be overridden per quiz.';
$string['setting_pdflanguage_help'] = 'Choose which language is used in generated PDFs. '
    . 'Only language packs installed in Moodle are available here.';
$string['setting_pdflanguage_site'] = 'Use site default language';
$string['setting_pdfscope'] = 'PDF scope';
$string['setting_pdfscope_desc'] = 'Which attempts are eligible for PDF generation. '
    . '"Passed only" requires the attempt to meet the pass grade. '
    . '"All finished" generates a PDF for every completed attempt.';
$string['setting_retentiondays'] = 'Retention period (days)';
$string['setting_retentiondays_desc'] = 'Number of days the PDF is stored after the attempt was passed. '
    . 'Set to 0 to keep indefinitely. Can be overridden per quiz.';
$string['setting_retentiondays_help'] = 'Number of days generated PDFs are stored. After that period, a '
    . 'scheduled cleanup task deletes them automatically. Set to 0 to keep PDFs indefinitely.';
$string['setting_show_attemptnumber'] = 'Show attempt number on PDF';
$string['setting_show_duration'] = 'Show duration on PDF';
$string['setting_show_passgrade'] = 'Show pass grade on PDF';
$string['setting_show_percentage'] = 'Show percentage on PDF';
$string['setting_show_score'] = 'Show score on PDF';
$string['setting_show_timestamp'] = 'Show timestamp on PDF';
$string['setting_showcorrectanswers'] = 'Show correct answers in PDF';
$string['setting_showcorrectanswers_desc'] = 'If enabled, the correct answer is shown '
    . 'alongside the learner\'s response where available.';
$string['setting_showquestioncomments'] = 'Include grading comments in PDF';
$string['setting_showquestioncomments_desc'] = 'If enabled, manual grading comments are shown per question in generated PDFs.';
$string['setting_showquestioncomments_help'] = 'Controls whether comments entered during manual grading are included in the PDF for each question.';
$string['setting_studentdownload'] = 'Student may download evaluation';
$string['setting_studentdownload_desc'] = 'If enabled, students can download their own evaluation from the quiz '
    . 'review page. If disabled, only teachers can access evaluations via the report page.';
$string['setting_studentdownload_help'] = 'Controls whether learners see a download button on their quiz '
    . 'review page. If disabled, only teachers and managers can access generated evaluations via the exam2pdf '
    . 'report page.';
$string['setting_studentemail'] = 'Student receives evaluation by e-mail';
$string['setting_studentemail_desc'] = 'If enabled, the evaluation is sent by e-mail to the student. '
    . 'Can be overridden per quiz.';
$string['setting_studentemail_help'] = 'Controls whether learners receive the evaluation by e-mail in addition to '
    . 'download. On quiz level, this can override the global default.';
$string['task_cleanup'] = 'Delete expired PDF certificates';
