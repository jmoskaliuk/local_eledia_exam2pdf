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

defined('MOODLE_INTERNAL') || die();

/**
 * Serve plugin files (PDFs) via Moodle's pluginfile mechanism.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context  $context Context of the file.
 * @param string   $filearea Filearea name.
 * @param array    $args Extra arguments (item ID + filename).
 * @param bool     $forcedownload Whether to force download.
 * @param array    $options Options array.
 * @return bool    False if file not found, does not return if file served.
 */
function local_eledia_exam2pdf_pluginfile(
    $course, $cm, $context, $filearea, $args, $forcedownload, array $options = []
): bool {
    global $DB, $USER;

    if ($filearea !== 'attempt_pdf') {
        return false;
    }

    $recordid = (int) array_shift($args);
    $filename  = array_shift($args);

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
}

/**
 * Add a navigation node to quiz activities so learners/trainers can access
 * the PDF overview page.
 *
 * @param global_navigation $navigation The navigation tree.
 */
function local_eledia_exam2pdf_extend_navigation(global_navigation $navigation): void {
    // Navigation extension handled via hooks and dedicated pages.
}

/**
 * Extend the quiz settings navigation.
 *
 * @param settings_navigation $settingsnav Settings navigation node.
 * @param context             $context Current context.
 */
function local_eledia_exam2pdf_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
): void {
    global $PAGE;

    if ($PAGE->cm && $PAGE->cm->modname === 'quiz') {
        $quizcontext = \core\context\module::instance($PAGE->cm->id);
        if (has_capability('local/eledia_exam2pdf:manage', $quizcontext)) {
            if ($quiznode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url(
                    '/local/eledia_exam2pdf/quizsettings.php',
                    ['cmid' => $PAGE->cm->id]
                );
                $quiznode->add(
                    get_string('quizsettings', 'local_eledia_exam2pdf'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'exam2pdf_quizsettings'
                );
            }
        }
    }
}
