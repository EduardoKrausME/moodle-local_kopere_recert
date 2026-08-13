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
 * @package   recerttask_coursecompletion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_coursecompletion;

use completion_completion;
use completion_criteria_completion;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;
use moodle_exception;

/**
 * Specialized kopere_recert task provider for course completion.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component represented by this task provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string { return 'core_coursecompletion'; }
    /**
     * Returns the localized name of this provider.
     */
    public static function get_name(): string { return get_string('pluginname', 'recerttask_coursecompletion'); }
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
    public static function get_system_order(): int { return 30; }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $completion = $DB->get_record('course_completions', [
            'course' => $context->courseid,
            'userid' => $context->userid,
        ]);
        $criteria = $DB->get_records('course_completion_crit_compl', [
            'course' => $context->courseid,
            'userid' => $context->userid,
        ], 'criteriaid ASC');

        $grade = null;
        $courseitem = $DB->get_record('grade_items', [
            'courseid' => $context->courseid,
            'itemtype' => 'course',
        ], 'id, grademax', IGNORE_MISSING);
        if ($courseitem) {
            $value = $DB->get_field('grade_grades', 'finalgrade', [
                'itemid' => $courseitem->id,
                'userid' => $context->userid,
            ]);
            if ($value !== false && $value !== null) {
                $grade = (float)$value;
            }
        }

        $rows = [];
        foreach ($criteria as $row) {
            $rows[] = [
                'criteriaid' => $row->criteriaid,
                'timecompleted' => $row->timecompleted ? userdate($row->timecompleted) : '',
                'gradefinal' => $row->gradefinal === null ? '' : format_float($row->gradefinal, 2),
            ];
        }

        $html = $OUTPUT->render_from_template('recerttask_coursecompletion/history', [
            'hascompletion' => (bool)$completion,
            'timeenrolled' => $completion && $completion->timeenrolled ? userdate($completion->timeenrolled) : '',
            'timestarted' => $completion && $completion->timestarted ? userdate($completion->timestarted) : '',
            'timecompleted' => $completion && $completion->timecompleted ? userdate($completion->timecompleted) : '',
            'criteria' => $rows,
            'hasgrade' => $grade !== null,
            'grade' => $grade !== null ? format_float($grade, 2) : '',
        ]);

        return new history_result(
            completedat: $completion && $completion->timecompleted ? (int)$completion->timecompleted : null,
            grade: $grade,
            html: $html,
            data: [
                'timeenrolled' => $completion->timeenrolled ?? null,
                'timestarted' => $completion->timestarted ?? null,
                'timecompleted' => $completion->timecompleted ?? null,
                'criteria' => count($rows),
            ]
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
        $count = 0;

        $criteria = completion_criteria_completion::fetch_all([
            'course' => $context->courseid,
            'userid' => $context->userid,
        ]);
        if ($criteria) {
            foreach ($criteria as $criterion) {
                if (!$criterion->delete()) {
                    throw new moodle_exception('coursecompletioncleanupfailed', 'local_kopere_recert');
                }
                $count++;
            }
        }

        $completion = completion_completion::fetch([
            'course' => $context->courseid,
            'userid' => $context->userid,
        ]);
        if ($completion) {
            if (!$completion->delete()) {
                throw new moodle_exception('coursecompletioncleanupfailed', 'local_kopere_recert');
            }
            $count++;
        }

        return new cleanup_result($count, [get_string('resetcount', 'recerttask_coursecompletion', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        global $DB;
        return [
            'coursecompletion' => $DB->record_exists('course_completions', [
                'course' => $context->courseid,
                'userid' => $context->userid,
            ]) ? 1 : 0,
            'criteria' => $DB->count_records('course_completion_crit_compl', [
                'course' => $context->courseid,
                'userid' => $context->userid,
            ]),
        ];
    }
}
