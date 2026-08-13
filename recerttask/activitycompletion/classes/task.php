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
 * @package   recerttask_activitycompletion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_activitycompletion;

use completion_info;
use local_kopere_recertification\task\cleanup_result;
use local_kopere_recertification\task\history_result;
use local_kopere_recertification\task\task_context;
use local_kopere_recertification\task\task_plugin_interface;

/**
 * Specialized kopere_recertification task provider for activity completion.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component represented by this task provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string { return 'core_activitycompletion'; }
    /**
     * Returns the localized name of this provider.
     */
    public static function get_name(): string { return get_string('pluginname', 'recerttask_activitycompletion'); }
    /**
     * Reports whether the provider can create historical snapshots.
     *
     * @return bool True when history creation is supported.
     */
    public static function supports_history(): bool { return true; }
    /**
     * Reports whether the provider can preserve files.
     *
     * @return bool True when file preservation is supported.
     */
    public static function supports_files(): bool { return false; }
    /**
     * Reports whether the provider can clean user data.
     *
     * @return bool True when cleanup is supported.
     */
    public static function supports_cleanup(): bool { return true; }
    /**
     * Reports whether this provider represents a system-level task.
     *
     * @return bool True for a system-level task.
     */
    public static function is_system_task(): bool { return true; }
    /**
     * Returns the ordering value used for system-level execution.
     *
     * @return int System execution order.
     */
    public static function get_system_order(): int { return 20; }

    /**
     * Builds the historical snapshot for the current kopere_recertification context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;
        $sql = "SELECT cmc.coursemoduleid, cmc.completionstate, cmc.viewed, cmc.overrideby, cmc.timemodified
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
              ORDER BY cm.section, cm.id";
        $records = $DB->get_records_sql($sql, ['courseid' => $context->courseid, 'userid' => $context->userid]);
        $rows = [];
        $modinfo = get_fast_modinfo($context->courseid);
        foreach ($records as $row) {
            $name = isset($modinfo->cms[$row->coursemoduleid]) ? $modinfo->cms[$row->coursemoduleid]->name : 'cmid ' . $row->coursemoduleid;
            $rows[] = [
                'cmid' => $row->coursemoduleid,
                'name' => format_string($name),
                'state' => $row->completionstate,
                'viewed' => $row->viewed,
                'timemodified' => $row->timemodified ? userdate($row->timemodified) : '',
            ];
        }
        return new history_result(
            html: $OUTPUT->render_from_template('recerttask_activitycompletion/history', ['rows' => $rows, 'count' => count($rows)]),
            data: ['records' => count($rows)]
        );
    }

    /**
     * Returns file descriptors that must be copied into historical storage.
     *
     * @param task_context $context Execution context.
     * @param int $historyid History record ID.
     * @return array File descriptors to preserve.
     */
    public function get_files(task_context $context, int $historyid): array { return []; }

    /**
     * Cleans the current user data after history and files have been safely preserved.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result {
        $course = get_course($context->courseid);
        $completion = new completion_info($course);
        $modinfo = get_fast_modinfo($course);

        $count = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$completion->is_enabled($cm)) {
                continue;
            }
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $context->userid, true);
            $count++;
        }
        return new cleanup_result($count, [get_string('resetcount', 'recerttask_activitycompletion', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        global $DB;
        return ['records' => (int)$DB->get_field_sql(
            "SELECT COUNT(1) FROM {course_modules_completion} cmc JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid WHERE cm.course = :courseid AND cmc.userid = :userid",
            ['courseid' => $context->courseid, 'userid' => $context->userid]
        )];
    }
}
