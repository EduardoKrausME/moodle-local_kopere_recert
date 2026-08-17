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
 * Child Course recertification task provider.
 *
 * @package   recerttask_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_childcourse;

use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized recertification task provider for Child Course.
 */
final class task implements task_plugin_interface {
    /** @return string Moodle component name. */
    public static function get_component(): string {
        return 'mod_childcourse';
    }

    /** @return string Localized provider name. */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_childcourse');
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
        return false;
    }

    /** @return int System execution order. */
    public static function get_system_order(): int {
        return 0;
    }

    /**
     * Builds the historical snapshot for the current recertification context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $activity = $DB->get_record('childcourse', ['id' => $context->instanceid], '*', MUST_EXIST);
        $childcourse = $DB->get_record('course', ['id' => $activity->childcourseid], 'id,fullname');
        $map = $DB->get_record('childcourse_map', [
            'childcourseinstanceid' => $context->instanceid,
            'userid' => $context->userid,
        ]);
        $state = $DB->get_record('childcourse_state', [
            'childcourseinstanceid' => $context->instanceid,
            'userid' => $context->userid,
        ]);

        $completedat = null;
        if ($state && !empty($state->coursecompleted) && !empty($state->coursecompletiontimemodified)) {
            $completedat = (int)$state->coursecompletiontimemodified;
        }

        $grade = null;
        if ($state && $state->finalgrade !== null) {
            $grade = (float)$state->finalgrade;
        }

        $data = [
            'childcourseid' => (int)$activity->childcourseid,
            'mapped' => (bool)$map,
            'timeenrolled' => $map ? (int)$map->timeenrolled : 0,
            'manualenrolid' => $map ? (int)$map->manualenrolid : 0,
            'roleid' => $map ? (int)$map->roleid : 0,
            'groupidsjson' => $map ? (string)($map->groupidsjson ?? '') : '',
            'coursecompleted' => $state ? (bool)$state->coursecompleted : false,
            'finalgrade' => $grade,
            'gradeitemtimemodified' => $state ? (int)$state->gradeitemtimemodified : 0,
            'coursecompletiontimemodified' => $state ? (int)$state->coursecompletiontimemodified : 0,
        ];

        $html = $OUTPUT->render_from_template('recerttask_childcourse/history', [
            'activityname' => format_string($activity->name),
            'childcoursename' => $childcourse ? format_string($childcourse->fullname) : (string)$activity->childcourseid,
            'mapped' => (bool)$map,
            'timeenrolled' => $map && !empty($map->timeenrolled) ? userdate((int)$map->timeenrolled) : '',
            'hasstate' => (bool)$state,
            'coursecompleted' => $state && !empty($state->coursecompleted),
            'hasgrade' => $grade !== null,
            'finalgrade' => $grade !== null ? format_float($grade, 2) : '',
        ]);

        return new history_result(
            completedat: $completedat,
            grade: $grade,
            html: $html,
            data: $data,
            messages: [get_string('archivedcount', 'recerttask_childcourse', ($map ? 1 : 0) + ($state ? 1 : 0))]
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
     * Removes user-specific Child Course synchronization state.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result {
        global $DB;

        // The enrolment map is intentionally preserved. It represents the external child-course enrolment
        // created by the activity and deleting it would orphan that enrolment. Only the per-user sync cache
        // is reset here; course/module completion and grades are handled by the core recertification providers.
        $params = [
            'childcourseinstanceid' => $context->instanceid,
            'userid' => $context->userid,
        ];
        $count = $DB->count_records('childcourse_state', $params);
        $DB->delete_records('childcourse_state', $params);

        return new cleanup_result($count, [get_string('removedcount', 'recerttask_childcourse', $count)]);
    }

    /**
     * Returns a non-destructive description of affected Child Course data.
     *
     * @param task_context $context Execution context.
     * @return array Structured impact description.
     */
    public function describe(task_context $context): array {
        global $DB;

        $params = [
            'childcourseinstanceid' => $context->instanceid,
            'userid' => $context->userid,
        ];
        return [
            'maps' => $DB->count_records('childcourse_map', $params),
            'states' => $DB->count_records('childcourse_state', $params),
        ];
    }
}
