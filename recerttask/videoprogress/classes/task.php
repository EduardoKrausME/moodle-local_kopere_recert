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
 * task.php
 *
 * @package   recerttask_videoprogress
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_videoprogress;

use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized recertification task provider for Video Progress.
 */
final class task implements task_plugin_interface {
    public static function get_component(): string {
        return 'mod_videoprogress';
    }

    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_videoprogress');
    }

    public static function supports_history(): bool {
        return true;
    }

    public static function supports_files(): bool {
        return false;
    }

    public static function supports_cleanup(): bool {
        return true;
    }

    public static function is_system_task(): bool {
        return false;
    }

    public static function get_system_order(): int {
        return 0;
    }

    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $activity = $DB->get_record('videoprogress', ['id' => $context->instanceid], '*', MUST_EXIST);
        $progress = $DB->get_record('videoprogress_progress', [
            'videoprogressid' => $context->instanceid,
            'userid' => $context->userid,
        ]);
        $sessioncount = $DB->count_records('videoprogress_sessions', [
            'videoprogressid' => $context->instanceid,
            'userid' => $context->userid,
        ]);
        $interactioncount = $this->count_interactions((int)$context->instanceid, $context->userid);

        $completedat = null;
        if ($progress && !empty($progress->completed)) {
            if (!empty($progress->confirmationtime)) {
                $completedat = (int)$progress->confirmationtime;
            } else if (!empty($progress->timemodified)) {
                $completedat = (int)$progress->timemodified;
            }
        }

        $data = [
            'sessioncount' => $sessioncount,
            'interactioncount' => $interactioncount,
        ];
        if ($progress) {
            $data['progress'] = [
                'duration' => (float)$progress->duration,
                'lastposition' => (float)$progress->lastposition,
                'uniquewatched' => (float)$progress->uniquewatched,
                'totalwatchtime' => (float)$progress->totalwatchtime,
                'percent' => (float)$progress->percent,
                'watchedsegments' => (string)($progress->watchedsegments ?? ''),
                'viewmap' => (string)($progress->viewmap ?? ''),
                'completed' => (bool)$progress->completed,
                'confirmation' => (bool)$progress->confirmation,
                'confirmationtime' => (int)$progress->confirmationtime,
                'timecreated' => (int)$progress->timecreated,
                'timemodified' => (int)$progress->timemodified,
            ];
        }

        $html = $OUTPUT->render_from_template('recerttask_videoprogress/history', [
            'activityname' => format_string($activity->name),
            'hasprogress' => (bool)$progress,
            'percent' => $progress ? format_float((float)$progress->percent, 2) : '',
            'lastposition' => $progress ? format_float((float)$progress->lastposition, 2) : '',
            'duration' => $progress ? format_float((float)$progress->duration, 2) : '',
            'totalwatchtime' => $progress ? format_float((float)$progress->totalwatchtime, 2) : '',
            'completed' => $progress && !empty($progress->completed),
            'sessioncount' => $sessioncount,
            'interactioncount' => $interactioncount,
        ]);

        return new history_result(
            completedat: $completedat,
            html: $html,
            data: $data,
            messages: [get_string('archivedcount', 'recerttask_videoprogress', $progress ? 1 : 0)]
        );
    }

    public function get_files(task_context $context, int $historyid): array {
        return [];
    }

    public function cleanup(task_context $context): cleanup_result {
        global $DB;

        $affected = 0;
        $activityparams = [
            'videoprogressid' => $context->instanceid,
            'userid' => $context->userid,
        ];

        $affected += $DB->count_records('videoprogress_sessions', $activityparams);
        $DB->delete_records('videoprogress_sessions', $activityparams);

        $itemids = $this->get_interaction_itemids((int)$context->instanceid);
        if ($itemids) {
            [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'vpi');
            $params = ['userid' => $context->userid] + $inparams;
            $select = "userid = :userid AND itemid {$insql}";
            $affected += $DB->count_records_select('videoprogress_interactions', $select, $params);
            $DB->delete_records_select('videoprogress_interactions', $select, $params);
        }

        $affected += $DB->count_records('videoprogress_progress', $activityparams);
        $DB->delete_records('videoprogress_progress', $activityparams);

        return new cleanup_result($affected, [get_string('removedcount', 'recerttask_videoprogress', $affected)]);
    }

    public function describe(task_context $context): array {
        global $DB;

        return [
            'progress' => $DB->count_records('videoprogress_progress', [
                'videoprogressid' => $context->instanceid,
                'userid' => $context->userid,
            ]),
            'sessions' => $DB->count_records('videoprogress_sessions', [
                'videoprogressid' => $context->instanceid,
                'userid' => $context->userid,
            ]),
            'interactions' => $this->count_interactions((int)$context->instanceid, $context->userid),
        ];
    }

    private function count_interactions(int $instanceid, int $userid): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {videoprogress_interactions} vi
                  JOIN {videoprogress_pointitems} pi ON pi.id = vi.itemid
                  JOIN {videoprogress_points} p ON p.id = pi.pointid
                 WHERE p.videoprogressid = :instanceid
                   AND vi.userid = :userid";
        return (int)$DB->count_records_sql($sql, ['instanceid' => $instanceid, 'userid' => $userid]);
    }

    private function get_interaction_itemids(int $instanceid): array {
        global $DB;

        $sql = "SELECT pi.id
                  FROM {videoprogress_pointitems} pi
                  JOIN {videoprogress_points} p ON p.id = pi.pointid
                 WHERE p.videoprogressid = :instanceid";
        return array_map('intval', array_keys($DB->get_records_sql($sql, ['instanceid' => $instanceid])));
    }
}
