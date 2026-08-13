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
 * provider.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy API provider for kopere_recert cycles, history, files, logs, and notices.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Describes personal data stored by the plugin for the Privacy API.
     *
     * @param collection $collection Privacy metadata collection.
     * @return collection Updated privacy metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_kopere_recert_cycle', [
            'userid' => 'privacy:metadata:local_kopere_recert_cycle:userid',
            'courseid' => 'privacy:metadata:local_kopere_recert_cycle:courseid',
            'createdby' => 'privacy:metadata:local_kopere_recert_cycle:createdby',
            'reason' => 'privacy:metadata:local_kopere_recert_cycle:reason',
        ], 'privacy:metadata:local_kopere_recert_cycle');

        $collection->add_database_table('local_kopere_recert_history', [
            'userid' => 'privacy:metadata:local_kopere_recert_history:userid',
            'html' => 'privacy:metadata:local_kopere_recert_history:html',
            'datajson' => 'privacy:metadata:local_kopere_recert_history:datajson',
        ], 'privacy:metadata:local_kopere_recert_history');

        $collection->add_database_table('local_kopere_recert_file', [
            'userid' => 'privacy:metadata:local_kopere_recert_file:userid',
        ], 'privacy:metadata:local_kopere_recert_file');

        $collection->add_database_table('local_kopere_recert_log', [
            'message' => 'privacy:metadata:local_kopere_recert_log:message',
        ], 'privacy:metadata:local_kopere_recert_log');

        $collection->add_database_table('local_kopere_recert_notice_log', [
            'userid' => 'privacy:metadata:local_kopere_recert_notice_log:userid',
        ], 'privacy:metadata:local_kopere_recert_notice_log');

        return $collection;
    }

    /**
     * Returns contexts that contain personal data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist Contexts containing personal data for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {local_kopere_recert_cycle} c ON c.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND (c.userid = :ownerid OR c.createdby = :creatorid)";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'ownerid' => $userid,
            'creatorid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Adds users whose personal data exists in the supplied context.
     *
     * @param userlist $userlist Privacy user list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {local_kopere_recert_cycle} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT createdby AS userid
               FROM {local_kopere_recert_cycle}
              WHERE courseid = :courseid
                AND createdby IS NOT NULL",
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Exports personal data for the approved user and contexts.
     *
     * @param approved_contextlist $contextlist Approved privacy context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $cycles = $DB->get_records('local_kopere_recert_cycle', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ], 'number ASC');

            foreach ($cycles as $cycle) {
                self::export_owned_cycle($context, $cycle);
            }

            // createdby is also personal data. Export administrative actions without exposing
            // another user's archived history in the creator's privacy export.
            $created = $DB->get_records('local_kopere_recert_cycle', [
                'courseid' => $context->instanceid,
                'createdby' => $userid,
            ], 'timecreated ASC');
            foreach ($created as $cycle) {
                if ((int)$cycle->userid === $userid) {
                    continue;
                }
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'local_kopere_recert'),
                    'created-cycles',
                    'cycle-' . $cycle->id,
                ], (object)[
                    'cycleid' => $cycle->id,
                    'number' => $cycle->number,
                    'name' => $cycle->name,
                    'reason' => $cycle->reason,
                    'source' => $cycle->source,
                    'status' => $cycle->status,
                    'timecreated' => self::datetime($cycle->timecreated),
                ]);
            }
        }
    }

    /**
     * Exports one user-owned kopere_recert cycle and its related data.
     *
     * @param context_course $context Execution context.
     * @param stdClass $cycle Cycle.
     */
    private static function export_owned_cycle(context_course $context, stdClass $cycle): void {
        global $DB;

        $cyclepath = [
            get_string('pluginname', 'local_kopere_recert'),
            'cycle-' . $cycle->number,
        ];

        writer::with_context($context)->export_data($cyclepath, (object)[
            'number' => $cycle->number,
            'name' => $cycle->name,
            'reason' => $cycle->reason,
            'source' => $cycle->source,
            'status' => $cycle->status,
            'createdby' => $cycle->createdby,
            'previouscompletedat' => self::datetime($cycle->previouscompletedat),
            'dueat' => self::datetime($cycle->dueat),
            'availableat' => self::datetime($cycle->availableat),
            'startedat' => self::datetime($cycle->startedat),
            'completedat' => self::datetime($cycle->completedat),
            'timecreated' => self::datetime($cycle->timecreated),
        ]);

        $histories = $DB->get_records('local_kopere_recert_history', [
            'cycleid' => $cycle->id,
            'userid' => $cycle->userid,
        ], 'sortorder ASC, id ASC');

        foreach ($histories as $history) {
            $historypath = array_merge($cyclepath, ['history-' . $history->id]);
            writer::with_context($context)->export_data($historypath, (object)[
                'component' => $history->component,
                'cmid' => $history->cmid,
                'instanceid' => $history->instanceid,
                'activityname' => $history->activityname,
                'activitytype' => $history->activitytype,
                'completedat' => self::datetime($history->completedat),
                'grade' => $history->grade,
                'html' => $history->html,
                'datajson' => $history->datajson,
            ]);

            $filemetadata = $DB->get_records('local_kopere_recert_file', ['historyid' => $history->id], 'id ASC');
            foreach ($filemetadata as $filemeta) {
                writer::with_context($context)->export_data(array_merge($historypath, [
                    'files',
                    'file-' . $filemeta->id,
                ]), (object)[
                    'filename' => $filemeta->filename,
                    'filepath' => $filemeta->filepath,
                    'mimetype' => $filemeta->mimetype,
                    'filesize' => $filemeta->filesize,
                    'contenthash' => $filemeta->contenthash,
                    'sourcecomponent' => $filemeta->sourcecomponent,
                    'sourcefilearea' => $filemeta->sourcefilearea,
                    'sourceitemid' => $filemeta->sourceitemid,
                    'sourcecontextid' => $filemeta->sourcecontextid,
                ]);
            }

            writer::with_context($context)->export_area_files(
                $historypath,
                'local_kopere_recert',
                'historyfiles',
                $history->id
            );
        }

        $logs = $DB->get_records('local_kopere_recert_log', ['cycleid' => $cycle->id], 'id ASC');
        foreach ($logs as $log) {
            writer::with_context($context)->export_data(array_merge($cyclepath, ['logs', 'log-' . $log->id]), (object)[
                'action' => $log->action,
                'component' => $log->component,
                'cmid' => $log->cmid,
                'status' => $log->status,
                'message' => $log->message,
                'duration' => $log->duration,
                'timecreated' => self::datetime($log->timecreated),
            ]);
        }

        $noticelogs = $DB->get_records('local_kopere_recert_notice_log', ['cycleid' => $cycle->id], 'id ASC');
        foreach ($noticelogs as $noticelog) {
            writer::with_context($context)->export_data(array_merge($cyclepath, [
                'notifications',
                'notice-' . $noticelog->id,
            ]), (object)[
                'noticeid' => $noticelog->noticeid,
                'sentat' => self::datetime($noticelog->sentat),
            ]);
        }
    }

    /**
     * Deletes plugin personal data for every user in a context.
     *
     * @param context $context Execution context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $cycleids = $DB->get_fieldset_select('local_kopere_recert_cycle', 'id', 'courseid = :courseid', [
            'courseid' => $context->instanceid,
        ]);
        self::delete_cycles($cycleids, $context);
    }

    /**
     * Deletes plugin personal data for an approved user.
     *
     * @param approved_contextlist $contextlist Approved privacy context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $cycleids = $DB->get_fieldset_select(
                'local_kopere_recert_cycle',
                'id',
                'courseid = :courseid AND userid = :userid',
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );
            self::delete_cycles($cycleids, $context);

            // Do not delete another user's cycle merely because this user created it.
            $DB->set_field_select(
                'local_kopere_recert_cycle',
                'createdby',
                null,
                'courseid = :courseid AND createdby = :createdby',
                ['courseid' => $context->instanceid, 'createdby' => $userid]
            );
        }
    }

    /**
     * Deletes plugin personal data for an approved list of users.
     *
     * @param approved_userlist $userlist Privacy user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'owner');
        $params['courseid'] = $context->instanceid;
        $cycleids = $DB->get_fieldset_select(
            'local_kopere_recert_cycle',
            'id',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        self::delete_cycles($cycleids, $context);

        [$creatorinsql, $creatorparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'creator');
        $creatorparams['courseid'] = $context->instanceid;
        $DB->set_field_select(
            'local_kopere_recert_cycle',
            'createdby',
            null,
            "courseid = :courseid AND createdby {$creatorinsql}",
            $creatorparams
        );
    }

    /**
     * Deletes cycles and their dependent personal data through the appropriate APIs.
     *
     * @param array $cycleids Recertification cycle IDs.
     * @param context_course $context Execution context.
     */
    private static function delete_cycles(array $cycleids, context_course $context): void {
        global $DB;

        if (!$cycleids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($cycleids, SQL_PARAMS_NAMED, 'cycle');
        $historyids = $DB->get_fieldset_select('local_kopere_recert_history', 'id', "cycleid {$insql}", $params);

        $fs = get_file_storage();
        foreach ($historyids as $historyid) {
            $fs->delete_area_files($context->id, 'local_kopere_recert', 'historyfiles', $historyid);
        }

        $DB->delete_records_select('local_kopere_recert_file', "cycleid {$insql}", $params);
        $DB->delete_records_select('local_kopere_recert_log', "cycleid {$insql}", $params);
        $DB->delete_records_select('local_kopere_recert_notice_log', "cycleid {$insql}", $params);
        $DB->delete_records_select('local_kopere_recert_history', "cycleid {$insql}", $params);
        $DB->delete_records_select('local_kopere_recert_cycle', "id {$insql}", $params);
    }

    /**
     * Converts an optional timestamp to the Privacy API export representation.
     *
     * @param mixed $value Value to validate or transform.
     * @return mixed Result of the operation.
     */
    private static function datetime(mixed $value): mixed {
        return empty($value) ? null : transform::datetime((int)$value);
    }
}
