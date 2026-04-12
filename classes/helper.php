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
        // NOTE: retentiondays intentionally does NOT use the short-ternary `?:` fallback
        // because '0' is a valid, meaningful value (never expire). `?: 365` would silently
        // rewrite zero to the default.
        $retentionraw = get_config('local_eledia_exam2pdf', 'retentiondays');
        $config = [
            'pdfgeneration'       => get_config('local_eledia_exam2pdf', 'pdfgeneration') ?: 'auto',
            'pdfscope'            => get_config('local_eledia_exam2pdf', 'pdfscope') ?: 'passed',
            'studentdownload'     => (bool) (get_config('local_eledia_exam2pdf', 'studentdownload') ?? true),
            'bulkformat'          => get_config('local_eledia_exam2pdf', 'bulkformat') ?: 'zip',
            'outputmode'          => get_config('local_eledia_exam2pdf', 'outputmode') ?: 'download',
            'emailrecipients'     => get_config('local_eledia_exam2pdf', 'emailrecipients') ?: '',
            'emailsubject'        => get_config('local_eledia_exam2pdf', 'emailsubject')
                                        ?: get_string('email_subject_default', 'local_eledia_exam2pdf'),
            'retentiondays'       => ($retentionraw === false || $retentionraw === '' || $retentionraw === null)
                                        ? 365
                                        : (int) $retentionraw,
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
                $boolfields = [
                    'studentdownload',
                    'showcorrectanswers',
                    'show_score',
                    'show_passgrade',
                    'show_percentage',
                    'show_timestamp',
                    'show_duration',
                    'show_attemptnumber',
                ];
                if (in_array($name, $boolfields, true)) {
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
     * Determines whether a quiz attempt is eligible for PDF generation
     * according to the current PDF scope setting.
     *
     * This is the central scope check used by the observer, the report page,
     * and the student download hook.
     *
     * @param \stdClass $attempt The quiz_attempts row (must have 'state' and 'sumgrades').
     * @param \stdClass $quiz    The quiz row (must have 'sumgrades', 'grade', 'id', 'course').
     * @param array     $config  Effective config from get_effective_config() (needs 'pdfscope').
     * @return bool True if the attempt is in scope for PDF generation.
     */
    public static function is_in_pdf_scope(\stdClass $attempt, \stdClass $quiz, array $config): bool {
        // Only finished attempts are eligible.
        if (($attempt->state ?? '') !== 'finished') {
            return false;
        }

        // If scope is 'all', every finished attempt qualifies.
        $scope = $config['pdfscope'] ?? 'passed';
        if ($scope === 'all') {
            return true;
        }

        // Scope is 'passed' — check the grade.
        return self::is_attempt_passed($attempt, $quiz);
    }

    /**
     * Determines whether a quiz attempt reached the passing grade.
     *
     * Extracted from the observer so it can be reused by is_in_pdf_scope()
     * and the report page.
     *
     * @param \stdClass $attempt The quiz_attempts row.
     * @param \stdClass $quiz    The quiz row.
     * @return bool True when the attempt reached or exceeded the pass grade.
     */
    public static function is_attempt_passed(\stdClass $attempt, \stdClass $quiz): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'courseid' => $quiz->course,
        ]);

        $gradepass = ($gradeitem && !empty($gradeitem->gradepass)) ? (float) $gradeitem->gradepass : 0.0;

        // If no passing grade is configured, every finished attempt counts as passed.
        if ($gradepass <= 0) {
            return true;
        }

        // Rescale attempt score to the quiz's grade scale.
        if ($quiz->sumgrades == 0) {
            return false;
        }

        $grade = $attempt->sumgrades / $quiz->sumgrades * $quiz->grade;
        return ($grade >= $gradepass);
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
     * Returns all PDF records belonging to a given quiz course module.
     *
     * The returned rows are augmented with the learner's full name and the
     * stored file (if any), so callers can render a table without further
     * lookups.
     *
     * @param int $cmid Course module ID of the quiz.
     * @return array List of objects with keys: record, user, file, downloadurl.
     */
    public static function get_quiz_pdfs(int $cmid): array {
        global $DB;

        $records = $DB->get_records(
            'local_eledia_exam2pdf',
            ['cmid' => $cmid],
            'timecreated DESC'
        );
        if (empty($records)) {
            return [];
        }

        // Bulk-load users to avoid N+1 queries.
        $userids = array_unique(array_map(static fn($r) => (int) $r->userid, $records));
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql(
            "SELECT u.id, {$userfields} FROM {user} u WHERE u.id {$insql}",
            $params
        );

        $result = [];
        foreach ($records as $record) {
            $file = self::get_stored_file($record);
            if (!$file) {
                continue;
            }
            $user = $users[$record->userid] ?? null;
            $result[] = (object) [
                'record'      => $record,
                'user'        => $user,
                'fullname'    => $user ? fullname($user) : '-',
                'file'        => $file,
                'downloadurl' => self::get_download_url($record, $file->get_filename()),
            ];
        }
        return $result;
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
