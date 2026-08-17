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
 * Gradebook recertification task provider.
 *
 * @package   recerttask_grades
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_grades;

use grade_item;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;
use moodle_exception;

/**
 * Specialized kopere_recert task provider for gradebook.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component handled by this provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string {
        return 'core_grades';
    }

    /**
     * Returns the localized provider name.
     *
     * @return string Localized provider name.
     */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_grades');
    }

    /**
     * Checks whether history creation is supported.
     *
     * @return bool True when history creation is supported.
     */
    public static function supports_history(): bool {
        return true;
    }

    /**
     * Checks whether file preservation is supported.
     *
     * @return bool True when file preservation is supported.
     */
    public static function supports_files(): bool {
        return false;
    }

    /**
     * Checks whether cleanup is supported.
     *
     * @return bool True when cleanup is supported.
     */
    public static function supports_cleanup(): bool {
        return true;
    }

    /**
     * Checks whether this is a system-level task.
     *
     * @return bool True for a system-level task.
     */
    public static function is_system_task(): bool {
        return true;
    }

    /**
     * Returns the system execution order.
     *
     * @return int System execution order.
     */
    public static function get_system_order(): int {
        return 10;
    }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $sql = "SELECT gi.id AS itemid, gi.itemname, gi.itemtype, gi.itemmodule, gi.iteminstance,
                       gi.grademax, gg.finalgrade, gg.rawgrade, gg.timemodified
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id
                 WHERE gi.courseid = :courseid
                   AND gg.userid = :userid
              ORDER BY gi.sortorder, gi.id";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $context->courseid,
            'userid' => $context->userid,
        ]);

        $rows = [];
        foreach ($records as $row) {
            $rows[] = [
                'name' => $row->itemname ?: ($row->itemmodule ?: $row->itemtype),
                'type' => $row->itemmodule ?: $row->itemtype,
                'finalgrade' => $row->finalgrade === null ? '' : format_float($row->finalgrade, 2),
                'hasgrade' => $row->finalgrade !== null,
                'maxgrade' => format_float($row->grademax, 2),
                'timemodified' => $row->timemodified ? userdate($row->timemodified) : '',
            ];
        }

        return new history_result(
            html: $OUTPUT->render_from_template('recerttask_grades/history', [
                'grades' => $rows,
                'count' => count($rows),
            ]),
            data: ['gradeitems' => count($rows)]
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
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $sql = "SELECT gi.id AS itemid
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id
                 WHERE gi.courseid = :courseid
                   AND gg.userid = :userid";
        $rows = $DB->get_records_sql($sql, [
            'courseid' => $context->courseid,
            'userid' => $context->userid,
        ]);

        $count = 0;
        foreach ($rows as $row) {
            $item = grade_item::fetch(['id' => $row->itemid]);
            if (!$item) {
                continue;
            }
            if (!$item->update_raw_grade($context->userid, null, 'local_kopere_recert')) {
                throw new moodle_exception('gradecleanupfailed', 'local_kopere_recert');
            }
            if (!$item->update_final_grade($context->userid, null, 'local_kopere_recert')) {
                throw new moodle_exception('gradecleanupfailed', 'local_kopere_recert');
            }
            $count++;
        }

        return new cleanup_result($count, [get_string('resetcount', 'recerttask_grades', $count)]);
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
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gg.userid = :userid";
        return ['grades' => (int)$DB->get_field_sql($sql, [
            'courseid' => $context->courseid,
            'userid' => $context->userid,
        ])];
    }
}
