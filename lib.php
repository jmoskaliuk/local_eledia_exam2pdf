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
 * Library functions for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve plugin files (PDFs) via Moodle's pluginfile mechanism.
 *
 * @param stdClass $course        Course object.
 * @param stdClass $cm            Course module object.
 * @param context  $context       Context of the file.
 * @param string   $filearea      Filearea name.
 * @param array    $args          Extra arguments (item ID + filename).
 * @param bool     $forcedownload Whether to force download.
 * @param array    $options       Options array.
 * @return bool False if file not found, does not return if file served.
 */
function local_eledia_exam2pdf_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
): bool {
    global $DB, $USER;

    if ($filearea !== 'attempt_pdf') {
        return false;
    }

    $recordid = (int) array_shift($args);
    $filename = array_shift($args);

    // Load the PDF record.
    $record = $DB->get_record('local_eledia_exam2pdf', ['id' => $recordid], '*', IGNORE_MISSING);
    if (!$record) {
        return false;
    }

    // Access control: learners may only download their own PDFs.
    // Trainers / admins with the manage capability may download any PDF.
    $quizcontext = \core\context\module::instance($cm->id);
    $canmanage   = has_capability('local/eledia_exam2pdf:manage', $quizcontext);

    if (!$canmanage && $record->userid != $USER->id) {
        return false;
    }

    require_login($course, false, $cm);

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $recordid, '/', $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Inject the PDF download button on the quiz review page and the bulk
 * PDF section on the quiz report overview page.
 *
 * This is a legacy before_footer callback called from
 * core_renderer::footer() via the before_footer_html_generation hook's
 * process_legacy_callbacks() mechanism.
 *
 * @return string HTML to inject before the page footer, or empty string.
 */
function local_eledia_exam2pdf_before_footer(): string {
    return \local_eledia_exam2pdf\hook\quiz_page_callbacks::get_footer_html();
}

/**
 * Extend the quiz module's secondary navigation with exam2pdf links.
 *
 * In Moodle 4+, items added here appear in the activity's secondary
 * navigation bar (the tabbed "More" dropdown).
 *
 * @param navigation_node $navref  The module's navigation node.
 * @param stdClass        $course  The course record.
 * @param stdClass        $module  The module record (quiz row).
 * @param cm_info         $cm      The course module info object.
 * @return void
 */
function local_eledia_exam2pdf_extend_navigation_module(
    navigation_node $navref,
    stdClass $course,
    stdClass $module,
    cm_info $cm
): void {
    if ($cm->modname !== 'quiz') {
        return;
    }

    $context = \core\context\module::instance($cm->id);

    // PDF Certificates report — visible to teachers / managers.
    if (has_capability('local/eledia_exam2pdf:manage', $context)) {
        $reporturl = new moodle_url(
            '/local/eledia_exam2pdf/report.php',
            ['cmid' => $cm->id]
        );
        $navref->add(
            get_string('report_nav_link', 'local_eledia_exam2pdf'),
            $reporturl,
            navigation_node::TYPE_CUSTOM,
            null,
            'exam2pdf_report'
        );
    }

    // Per-quiz PDF settings — visible to users with the configure capability.
    if (has_capability('local/eledia_exam2pdf:configure', $context)) {
        $settingsurl = new moodle_url(
            '/local/eledia_exam2pdf/quizsettings.php',
            ['cmid' => $cm->id]
        );
        $navref->add(
            get_string('quizsettings', 'local_eledia_exam2pdf'),
            $settingsurl,
            navigation_node::TYPE_CUSTOM,
            null,
            'exam2pdf_quizsettings'
        );
    }
}

/**
 * Extend the quiz settings navigation with a link to the per-quiz config page.
 *
 * Kept alongside extend_navigation_module for the settings gear menu.
 *
 * @param settings_navigation $settingsnav Settings navigation node.
 * @param context             $context     Current context.
 * @return void
 */
function local_eledia_exam2pdf_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
): void {
    global $PAGE;

    if ($PAGE->cm && $PAGE->cm->modname === 'quiz') {
        $quizcontext = \core\context\module::instance($PAGE->cm->id);
        $quiznode    = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);

        if ($quiznode) {
            // PDF Certificates report — visible to teachers / managers.
            if (has_capability('local/eledia_exam2pdf:manage', $quizcontext)) {
                $reporturl = new moodle_url(
                    '/local/eledia_exam2pdf/report.php',
                    ['cmid' => $PAGE->cm->id]
                );
                $quiznode->add(
                    get_string('report_nav_link', 'local_eledia_exam2pdf'),
                    $reporturl,
                    navigation_node::TYPE_SETTING,
                    null,
                    'exam2pdf_report'
                );
            }

            // Per-quiz PDF settings — visible to teachers / managers with configure.
            if (has_capability('local/eledia_exam2pdf:configure', $quizcontext)) {
                $settingsurl = new moodle_url(
                    '/local/eledia_exam2pdf/quizsettings.php',
                    ['cmid' => $PAGE->cm->id]
                );
                $quiznode->add(
                    get_string('quizsettings', 'local_eledia_exam2pdf'),
                    $settingsurl,
                    navigation_node::TYPE_SETTING,
                    null,
                    'exam2pdf_quizsettings'
                );
            }
        }
    }
}
