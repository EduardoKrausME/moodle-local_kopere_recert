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
 * Activity completion recertification task provider.
 *
 * @package   recerttask_activitycompletion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_activitycompletion;

use completion_info;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized kopere_recert task provider for activity completion.
 */
final class task implements task_plugin_interface {
    /** @return string Moodle component name. */
    public static function get_component(): string {
        return 'core_activitycompletion';
    }

    /** @return string Localized provider name. */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_activitycompletion');
    }

    /** @return bool True when history creation is supported. */
    public static function supports_history(): bool {
        return true;
    }

    /** @return bool True when file preservation is supported. */
    public static function supports_files(): bool {
        return false;
    }

    /** @return bool True when cleanup is supported. */
    public static function supports_cleanup(): bool {
        return true;
    }

    /** @return bool True for a system-level task. */
    public static function is_system_task(): bool {
        return true;
    }

    /** @return int System execution order. */
    public static function get_system_order(): int {
        return 20;
    }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;
        $sql = "SELECT cmc.coursemoduleid,
                       cmc.completionstate,
                       CASE WHEN cmv.id IS NULL THEN 0 ELSE 1 END AS viewed,
                       cmc.overrideby,
                       cmc.timemodified
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             LEFT JOIN {course_modules_viewed} cmv
                    ON cmv.coursemoduleid = cmc.coursemoduleid
                   AND cmv.userid = cmc.userid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
              ORDER BY cm.section, cm.id";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $context->courseid,
            'userid' => $context->userid,
        ]);
        $rows = [];
        $modinfo = get_fast_modinfo($context->courseid);
        foreach ($records as $row) {
            $name = isset($modinfo->cms[$row->coursemoduleid])
                ? $modinfo->cms[$row->coursemoduleid]->name
                : 'cmid ' . $row->coursemoduleid;
            $rows[] = [
                'cmid' => $row->coursemoduleid,
                'name' => format_string($name),
                'state' => $row->completionstate,
                'viewed' => $row->viewed,
                'timemodified' => $row->timemodified ? userdate($row->timemodified) : '',
            ];
        }
        return new history_result(
            html: $OUTPUT->render_from_template('recerttask_activitycompletion/history', [
                'rows' => $rows,
                'count' => count($rows),
            ]),
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
    public function get_files(task_context $context, int $historyid): array {
        return [];
    }

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
        $sql = "SELECT COUNT(1)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid";
        return ['records' => (int)$DB->get_field_sql($sql, [
            'courseid' => $context->courseid,
            'userid' => $context->userid,
        ])];
    }
}
