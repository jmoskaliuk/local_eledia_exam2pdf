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
 * Privacy API implementation for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Implements GDPR/Privacy API for local_eledia_exam2pdf.
 *
 * Personal data stored: one DB record per passed attempt (user ID, attempt ID,
 * quiz ID, timestamps) plus the PDF file containing the learner's name and
 * quiz answers.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns the metadata collection describing all personal data stored by
     * this plugin.
     *
     * @param collection $collection The collection to add items to.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_eledia_exam2pdf',
            [
                'userid'      => 'privacy:metadata:local_eledia_exam2pdf:userid',
                'quizid'      => 'privacy:metadata:local_eledia_exam2pdf:quizid',
                'attemptid'   => 'privacy:metadata:local_eledia_exam2pdf:attemptid',
                'timecreated' => 'privacy:metadata:local_eledia_exam2pdf:timecreated',
                'timeexpires' => 'privacy:metadata:local_eledia_exam2pdf:timeexpires',
            ],
            'privacy:metadata:local_eledia_exam2pdf'
        );

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Returns all contexts in which the given user has personal data.
     *
     * @param int $userid The user ID to look up.
     * @return contextlist The list of module contexts with data for this user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = '
            SELECT ctx.id
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
              JOIN {modules} m ON m.id = cm.module AND m.name = :quizmod
              JOIN {quiz} q ON q.id = cm.instance
              JOIN {local_eledia_exam2pdf} r ON r.quizid = q.id
             WHERE ctx.contextlevel = :ctxmodule
               AND r.userid = :userid
        ';

        $contextlist->add_from_sql($sql, [
            'userid'    => $userid,
            'ctxmodule' => CONTEXT_MODULE,
            'quizmod'   => 'quiz',
        ]);

        return $contextlist;
    }

    /**
     * Adds all users that have personal data in the given context to the
     * supplied userlist.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = '
            SELECT r.userid
              FROM {local_eledia_exam2pdf} r
              JOIN {quiz} q ON q.id = r.quizid
              JOIN {course_modules} cm ON cm.instance = q.id
              JOIN {modules} m ON m.id = cm.module AND m.name = :quizmod
             WHERE cm.id = :cmid
        ';

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid, 'quizmod' => 'quiz']);
    }

    /**
     * Exports all personal data of the given user from the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved list of contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $records = $DB->get_records_sql(
                'SELECT r.*
                   FROM {local_eledia_exam2pdf} r
                   JOIN {quiz} q ON q.id = r.quizid
                   JOIN {course_modules} cm ON cm.instance = q.id
                   JOIN {modules} m ON m.id = cm.module AND m.name = :quizmod
                  WHERE cm.id = :cmid AND r.userid = :userid',
                ['cmid' => $context->instanceid, 'userid' => $userid, 'quizmod' => 'quiz']
            );

            if (empty($records)) {
                continue;
            }

            foreach ($records as $record) {
                $data = [
                    'quizid'      => $record->quizid,
                    'attemptid'   => $record->attemptid,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timeexpires' => $record->timeexpires ? transform::datetime($record->timeexpires) : 'never',
                ];

                writer::with_context($context)
                    ->export_data(['PDF Certificate', $record->id], (object) $data);

                // Export the actual PDF file.
                writer::with_context($context)
                    ->export_area_files(
                        ['PDF Certificate', $record->id],
                        'local_eledia_exam2pdf',
                        'attempt_pdf',
                        $record->id
                    );
            }
        }
    }

    /**
     * Deletes all personal data for every user in the given context.
     *
     * @param context $context The context whose data should be wiped.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cmid = $context->instanceid;
        $cm   = get_coursemodule_from_id('quiz', $cmid);
        if (!$cm) {
            return;
        }

        $records = $DB->get_records('local_eledia_exam2pdf', ['quizid' => $cm->instance]);
        $fs      = get_file_storage();

        foreach ($records as $record) {
            $fs->delete_area_files($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $record->id);
        }

        $DB->delete_records('local_eledia_exam2pdf', ['quizid' => $cm->instance]);
    }

    /**
     * Deletes personal data for a single user, scoped to the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved list of contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $fs     = get_file_storage();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Scope deletion to the current quiz context only.
            $records = $DB->get_records('local_eledia_exam2pdf', [
                'userid' => $userid,
                'quizid' => $cm->instance,
            ]);
            foreach ($records as $record) {
                $fs->delete_area_files($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $record->id);
                $DB->delete_records('local_eledia_exam2pdf', ['id' => $record->id]);
            }
        }
    }

    /**
     * Deletes personal data for a batch of users inside a single context.
     *
     * @param approved_userlist $userlist The approved list of users, bound to a context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $fs = get_file_storage();

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['quizid'] = $cm->instance;

        $records = $DB->get_records_sql(
            "SELECT * FROM {local_eledia_exam2pdf} WHERE quizid = :quizid AND userid {$insql}",
            $params
        );

        foreach ($records as $record) {
            $fs->delete_area_files($context->id, 'local_eledia_exam2pdf', 'attempt_pdf', $record->id);
            $DB->delete_records('local_eledia_exam2pdf', ['id' => $record->id]);
        }
    }
}
