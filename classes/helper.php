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
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf;

/**
 * Provides shared utility methods for the plugin.
 */
class helper {
    /**
     * Boolean settings exposed in the per-quiz form as advcheckbox fields.
     *
     * The `save_quiz_config_with_inheritance()` method compares each of these
     * against the current global default and only persists an override when
     * they differ. This prevents a plain "Save" click from silently freezing
     * the current global default as a permanent per-quiz override.
     *
     * @var string[]
     */
    public const BOOL_KEYS = [
        'showcorrectanswers',
        'show_score',
        'show_passgrade',
        'show_percentage',
        'show_timestamp',
        'show_duration',
        'show_attemptnumber',
    ];

    /**
     * Reads a boolean plugin setting and applies a default when unset.
     *
     * `get_config()` returns boolean false for unset settings, which must be
     * distinguished from an explicit "0" value.
     *
     * @param string $name Setting name.
     * @param bool $default Default value when setting is unset.
     * @return bool
     */
    private static function get_bool_setting(string $name, bool $default): bool {
        $raw = get_config('local_eledia_exam2pdf', $name);
        return ($raw === false) ? $default : (bool) $raw;
    }

    /**
     * Returns installed Moodle language packs as code => display name.
     *
     * @return array
     */
    private static function get_installed_languages(): array {
        return get_string_manager()->get_list_of_translations();
    }

    /**
     * Normalizes configured PDF language value.
     *
     * Allowed values:
     * - 'site': use the site default language
     * - installed language code (e.g. 'de', 'en')
     *
     * @param string $value Raw configured value.
     * @return string Normalized value.
     */
    private static function normalize_pdf_language(string $value): string {
        $value = trim($value);
        if ($value === '' || $value === 'site') {
            return 'site';
        }

        $languages = self::get_installed_languages();
        return array_key_exists($value, $languages) ? $value : 'site';
    }

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

        $pdfgeneration = get_config('local_eledia_exam2pdf', 'pdfgeneration');
        if (!in_array($pdfgeneration, ['auto', 'ondemand'], true)) {
            $pdfgeneration = 'auto';
        }

        $pdfscope = get_config('local_eledia_exam2pdf', 'pdfscope');
        if (!in_array($pdfscope, ['passed', 'all'], true)) {
            $pdfscope = 'passed';
        }

        $bulkformat = get_config('local_eledia_exam2pdf', 'bulkformat');
        if (!in_array($bulkformat, ['zip', 'merged'], true)) {
            $bulkformat = 'zip';
        }

        $outputmode = get_config('local_eledia_exam2pdf', 'outputmode');
        if (!in_array($outputmode, ['download', 'email', 'both'], true)) {
            $outputmode = 'download';
        }

        $pdflanguage = self::normalize_pdf_language(
            (string) (get_config('local_eledia_exam2pdf', 'pdflanguage') ?: 'site')
        );

        // Start with global defaults.
        // NOTE: retentiondays intentionally does NOT use the short-ternary `?:` fallback
        // because '0' is a valid, meaningful value (never expire). `?: 365` would silently
        // rewrite zero to the default.
        $retentionraw = get_config('local_eledia_exam2pdf', 'retentiondays');

        // The get_config() function returns boolean false (not null) when a setting has never been saved.
        // The null-coalescing operator `??` only fires for null, so `false ?? true` stays
        // false. We must explicitly detect false and substitute the intended default (true).
        $sdraw = get_config('local_eledia_exam2pdf', 'studentdownload');
        $seraw = get_config('local_eledia_exam2pdf', 'studentemail');
        $studentemaildefault = ($seraw === false)
            ? in_array($outputmode, ['email', 'both'], true)
            : (bool) $seraw;

        $config = [
            'pdfgeneration'       => $pdfgeneration,
            'pdfscope'            => $pdfscope,
            'studentdownload'     => ($sdraw === false) ? true : (bool) $sdraw,
            'studentemail'        => $studentemaildefault,
            'bulkformat'          => $bulkformat,
            'outputmode'          => $outputmode,
            'pdflanguage'         => $pdflanguage,
            'pdffootertext'       => get_config('local_eledia_exam2pdf', 'pdffootertext') ?: '',
            'emailrecipients'     => get_config('local_eledia_exam2pdf', 'emailrecipients') ?: '',
            'emailsubject'        => get_config('local_eledia_exam2pdf', 'emailsubject')
                                        ?: get_string('email_subject_default', 'local_eledia_exam2pdf'),
            'retentiondays'       => ($retentionraw === false || $retentionraw === '' || $retentionraw === null)
                                        ? 365
                                        : (int) $retentionraw,
            'showcorrectanswers'  => self::get_bool_setting('showcorrectanswers', true),
            'showquestioncomments' => self::get_bool_setting('showquestioncomments', false),
            'show_score'          => self::get_bool_setting('show_score', true),
            'show_passgrade'      => self::get_bool_setting('show_passgrade', true),
            'show_percentage'     => self::get_bool_setting('show_percentage', true),
            'show_timestamp'      => self::get_bool_setting('show_timestamp', true),
            'show_duration'       => self::get_bool_setting('show_duration', true),
            'show_attemptnumber'  => self::get_bool_setting('show_attemptnumber', true),
        ];

        // Apply per-quiz overrides.
        $overrides = $DB->get_records_menu(
            'local_eledia_exam2pdf_cfg',
            ['quizid' => $quizid],
            '',
            'name, value'
        );
        $hasstudentemailoverride = array_key_exists('studentemail', $overrides)
            && $overrides['studentemail'] !== null
            && $overrides['studentemail'] !== '';

        foreach ($overrides as $name => $value) {
            if ($value !== null && $value !== '') {
                // Cast booleans stored as '0'/'1'.
                $boolfields = [
                    'studentdownload',
                    'studentemail',
                    'showcorrectanswers',
                    'showquestioncomments',
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
                } else if ($name === 'pdflanguage') {
                    $config[$name] = self::normalize_pdf_language((string) $value);
                } else {
                    $config[$name] = $value;
                }
            }
        }

        // Keep backward compatibility with older outputmode overrides when no
        // explicit student-email override exists yet.
        if (!$hasstudentemailoverride) {
            $config['studentemail'] = in_array((string) ($config['outputmode'] ?? 'download'), ['email', 'both'], true);
        }

        return $config;
    }

    /**
     * Returns true when the user may download all quiz PDFs in this context.
     *
     * Keeps the legacy `manage` capability as backward-compatible fallback.
     *
     * @param \context_module $context Quiz module context.
     * @return bool
     */
    public static function has_downloadall_capability(\context_module $context): bool {
        return has_capability('local/eledia_exam2pdf:downloadall', $context)
            || has_capability('local/eledia_exam2pdf:manage', $context);
    }

    /**
     * Returns true when the user may generate/regenerate PDFs in this context.
     *
     * Keeps the legacy `manage` capability as backward-compatible fallback.
     *
     * @param \context_module $context Quiz module context.
     * @return bool
     */
    public static function has_generatepdf_capability(\context_module $context): bool {
        return has_capability('local/eledia_exam2pdf:generatepdf', $context)
            || has_capability('local/eledia_exam2pdf:manage', $context);
    }

    /**
     * Returns true when the user may download their own quiz PDF.
     *
     * Users with "download all" are implicitly allowed.
     *
     * @param \context_module $context Quiz module context.
     * @return bool
     */
    public static function has_downloadown_capability(\context_module $context): bool {
        return has_capability('local/eledia_exam2pdf:downloadown', $context)
            || self::has_downloadall_capability($context);
    }

    /**
     * Returns the current effective global default for a boolean advcheckbox key.
     *
     * All keys in {@see self::BOOL_KEYS} default to true in the plugin
     * install; admins can flip them via the global settings page. This helper
     * asks get_config() for the current persisted value and falls back to the
     * documented default when the setting has never been saved.
     *
     * @param string $name Setting name (must be one of BOOL_KEYS).
     * @return bool Current global default.
     */
    private static function get_bool_default(string $name): bool {
        // All current advcheckbox keys default to `true` in the plugin defaults.
        return self::get_bool_setting($name, true);
    }

    /**
     * Saves per-quiz configuration overrides with inheritance semantics for
     * advcheckbox fields.
     *
     * For each key listed in {@see self::BOOL_KEYS}, compares the submitted
     * value against the current global default. When both match, the override
     * is removed (null) so the per-quiz form inherits future changes to the
     * global default. When they differ, the explicit '1'/'0' override is
     * persisted. All other keys are forwarded unchanged to save_quiz_config().
     *
     * This prevents the "freeze at current default" pitfall where a plain
     * "Save" click on the per-quiz form would capture the currently-visible
     * global default as a permanent override and silently break the
     * inheritance chain when the admin later flips the global setting.
     *
     * @param int   $quizid The quiz ID whose overrides should be updated.
     * @param array $values Associative array of name => value.
     * @return void
     */
    public static function save_quiz_config_with_inheritance(int $quizid, array $values): void {
        foreach ($values as $name => $value) {
            if (!in_array($name, self::BOOL_KEYS, true)) {
                continue;
            }
            // Submitted value as bool — advcheckbox sends '0'/'1'.
            $submitted = (bool) (int) $value;
            $globaldefault = self::get_bool_default($name);
            if ($submitted === $globaldefault) {
                // Match the global default → remove any existing override.
                $values[$name] = null;
            } else {
                // Differs → persist explicit override.
                $values[$name] = $submitted ? '1' : '0';
            }
        }
        self::save_quiz_config($quizid, $values);
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
     * The returned rows are augmented with user/attempt/quiz data and ready-made
     * URLs so callers can render report tables without extra lookups.
     *
     * @param int $cmid Course module ID of the quiz.
     * @return array List of objects with keys:
     *   - record (\stdClass local_eledia_exam2pdf row)
     *   - user (\stdClass|null user row)
     *   - attempt (\stdClass|null quiz_attempts row)
     *   - quiz (\stdClass|null quiz row)
     *   - fullname (string)
     *   - profileurl (\moodle_url|null)
     *   - gradedisplay (string)
     *   - reviewurl (\moodle_url|null)
     *   - file (\stored_file)
     *   - downloadurl (\moodle_url)
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

        // Bulk-load attempts.
        $attemptids = array_unique(array_map(static fn($r) => (int) $r->attemptid, $records));
        [$attemptinsql, $attemptparams] = $DB->get_in_or_equal($attemptids);
        $attempts = $DB->get_records_sql(
            "SELECT qa.id, qa.quiz, qa.sumgrades FROM {quiz_attempts} qa WHERE qa.id {$attemptinsql}",
            $attemptparams
        );

        // Bulk-load quizzes.
        $quizids = array_unique(array_map(static fn($r) => (int) $r->quizid, $records));
        $quizids = array_values(array_filter($quizids));
        if (empty($quizids)) {
            $quizids = array_values(array_unique(array_map(static fn($a) => (int) $a->quiz, $attempts)));
        }
        $quizzes = [];
        if (!empty($quizids)) {
            [$quizinsql, $quizparams] = $DB->get_in_or_equal($quizids);
            $quizzes = $DB->get_records_sql(
                "SELECT q.id, q.course, q.grade, q.sumgrades FROM {quiz} q WHERE q.id {$quizinsql}",
                $quizparams
            );
        }

        $result = [];
        foreach ($records as $record) {
            $file = self::get_stored_file($record);
            if (!$file) {
                continue;
            }
            $user = $users[$record->userid] ?? null;
            $attempt = $attempts[$record->attemptid] ?? null;
            $quiz = $quizzes[$record->quizid] ?? null;
            if (!$quiz && $attempt) {
                $quiz = $quizzes[$attempt->quiz] ?? null;
            }

            $profileurl = null;
            if ($user && $quiz) {
                $profileurl = new \moodle_url('/user/profile.php', [
                    'id' => (int) $user->id,
                    'course' => (int) $quiz->course,
                ]);
            }

            $gradedisplay = '-';
            if ($attempt && $quiz && (float) $quiz->sumgrades > 0 && $attempt->sumgrades !== null) {
                $grade = $attempt->sumgrades / $quiz->sumgrades * $quiz->grade;
                $gradedisplay = round($grade, 2) . ' / ' . round((float) $quiz->grade, 2);
            }

            $reviewurl = null;
            if ($attempt) {
                $reviewurl = new \moodle_url('/mod/quiz/review.php', [
                    'attempt' => (int) $attempt->id,
                    'cmid' => (int) $record->cmid,
                ]);
            }

            $result[] = (object) [
                'record'      => $record,
                'user'        => $user,
                'attempt'     => $attempt,
                'quiz'        => $quiz,
                'fullname'    => $user ? fullname($user) : '-',
                'profileurl'  => $profileurl,
                'gradedisplay' => $gradedisplay,
                'reviewurl'   => $reviewurl,
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
