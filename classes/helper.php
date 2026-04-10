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
 * General helper utilities for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf;

/**
 * Provides shared utility methods for the plugin.
 */
class helper {

    /**
     * Returns the effective configuration for a given quiz.
     *
     * Global admin settings are used as defaults; per-quiz overrides from
     * local_eledia_exam2pdf_cfg take precedence.
     *
     * @param int $quizid The quiz ID whose effective config should be computed.
     * @return array Associative array of config key => value.
     */
    public static function get_effective_config(int $quizid): array {
        global $DB;

        // Start with global defaults.
        $config = [
            'outputmode'          => get_config('local_eledia_exam2pdf', 'outputmode') ?: 'download',
            'emailrecipients'     => get_config('local_eledia_exam2pdf', 'emailrecipients') ?: '',
            'emailsubject'        => get_config('local_eledia_exam2pdf', 'emailsubject')
                                        ?: get_string('email_subject_default', 'local_eledia_exam2pdf'),
            'retentiondays'       => (int) (get_config('local_eledia_exam2pdf', 'retentiondays') ?: 365),
            'showcorrectanswers'  => (bool) get_config('local_eledia_exam2pdf', 'showcorrectanswers'),
            'show_score'          => (bool) get_config('local_eledia_exam2pdf', 'show_score'),
            'show_passgrade'      => (bool) get_config('local_eledia_exam2pdf', 'show_passgrade'),
            'show_percentage'     => (bool) get_config('local_eledia_exam2pdf', 'show_percentage'),
            'show_timestamp'      => (bool) get_config('local_eledia_exam2pdf', 'show_timestamp'),
            'show_duration'       => (bool) get_config('local_eledia_exam2pdf', 'show_duration'),
            'show_attemptnumber'  => (bool) get_config('local_eledia_exam2pdf', 'show_attemptnumber'),
        ];

        // Apply per-quiz overrides.
        $overrides = $DB->get_records_menu(
            'local_eledia_exam2pdf_cfg',
            ['quizid' => $quizid],
            '',
            'name, value'
        );

        foreach ($overrides as $name => $value) {
            if ($value !== null && $value !== '') {
                // Cast booleans stored as '0'/'1'.
                if (in_array($name, ['showcorrectanswers', 'show_score', 'show_passgrade',
                    'show_percentage', 'show_timestamp', 'show_duration', 'show_attemptnumber'], true)) {
                    $config[$name] = (bool) $value;
                } else if ($name === 'retentiondays') {
                    $config[$name] = (int) $value;
                } else {
                    $config[$name] = $value;
                }
            }
        }

        return $config;
    }

    /**
     * Saves per-quiz configuration overrides.
     *
     * @param int   $quizid The quiz ID whose overrides should be updated.
     * @param array $values Associative array of name => value. Pass null or '' to remove an override.
     * @return void
     */
    public static function save_quiz_config(int $quizid, array $values): void {
        global $DB;

        foreach ($values as $name => $value) {
            $existing = $DB->get_record(
                'local_eledia_exam2pdf_cfg',
                ['quizid' => $quizid, 'name' => $name]
            );

            if ($value === null || $value === '') {
                // Remove override — fall back to global default.
                if ($existing) {
                    $DB->delete_records('local_eledia_exam2pdf_cfg', ['id' => $existing->id]);
                }
            } else if ($existing) {
                $existing->value = $value;
                $DB->update_record('local_eledia_exam2pdf_cfg', $existing);
            } else {
                $DB->insert_record('local_eledia_exam2pdf_cfg', (object) [
                    'quizid' => $quizid,
                    'name'   => $name,
                    'value'  => $value,
                ]);
            }
        }
    }

    /**
     * Returns the pluginfile URL for a PDF record.
     *
     * @param \stdClass $record   Row from local_eledia_exam2pdf.
     * @param string    $filename The filename to serve to the client.
     * @return \moodle_url
     */
    public static function get_download_url(\stdClass $record, string $filename): \moodle_url {
        $context = \core\context\module::instance($record->cmid);
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_eledia_exam2pdf',
            'attempt_pdf',
            $record->id,
            '/',
            $filename,
            true
        );
    }

    /**
     * Returns the stored file for a PDF record, or null if not found.
     *
     * @param \stdClass $record Row from local_eledia_exam2pdf.
     * @return \stored_file|null
     */
    public static function get_stored_file(\stdClass $record): ?\stored_file {
        $context = \core\context\module::instance($record->cmid);
        $fs      = get_file_storage();
        $files   = $fs->get_area_files(
            $context->id,
            'local_eledia_exam2pdf',
            'attempt_pdf',
            $record->id,
            'filename',
            false
        );
        return !empty($files) ? reset($files) : null;
    }
}
