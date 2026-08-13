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
 * @package   recerttask_enrolment
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_enrolment;

use local_kopere_recertification\task\cleanup_result;
use local_kopere_recertification\task\history_result;
use local_kopere_recertification\task\task_context;
use local_kopere_recertification\task\task_plugin_interface;
use moodle_exception;

/**
 * Specialized kopere_recertification task provider for enrolments.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component represented by this task provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string { return 'core_enrolment'; }
    /**
     * Returns the localized name of this provider.
     */
    public static function get_name(): string { return get_string('pluginname', 'recerttask_enrolment'); }
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
    public static function get_system_order(): int { return 50; }

    /**
     * Builds the historical snapshot for the current kopere_recertification context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $sql = "SELECT ue.id, ue.enrolid, ue.status, ue.timestart, ue.timeend, ue.timecreated, ue.timemodified,
                       e.enrol
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid
              ORDER BY ue.id";
        $records = $DB->get_records_sql($sql, ['courseid' => $context->courseid, 'userid' => $context->userid]);
        $rows = [];
        foreach ($records as $row) {
            $rows[] = [
                'enrol' => $row->enrol,
                'status' => $row->status,
                'timestart' => $row->timestart ? userdate($row->timestart) : '',
                'timeend' => $row->timeend ? userdate($row->timeend) : '',
                'timecreated' => $row->timecreated ? userdate($row->timecreated) : '',
            ];
        }
        return new history_result(
            html: $OUTPUT->render_from_template('recerttask_enrolment/history', ['rows' => $rows, 'count' => count($rows)]),
            data: ['enrolments' => count($rows)]
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
        global $DB;

        $sql = "SELECT ue.*, e.enrol, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid";
        $records = $DB->get_records_sql($sql, ['courseid' => $context->courseid, 'userid' => $context->userid]);

        $now = time();
        $count = 0;
        foreach ($records as $ue) {
            $instance = $DB->get_record('enrol', ['id' => $ue->enrolid, 'courseid' => $context->courseid], '*', MUST_EXIST);
            if ($context->simulation) {
                // Prevent third-party enrolment plugins from performing external side effects during a simulation.
                $DB->set_field('user_enrolments', 'timestart', $now, ['id' => $ue->id, 'userid' => $context->userid]);
            } else {
                $plugin = enrol_get_plugin($instance->enrol);
                if (!$plugin) {
                    throw new moodle_exception('enrolpluginmissing', 'local_kopere_recertification', '', $instance->enrol);
                }
                $plugin->update_user_enrol($instance, $context->userid, null, $now, null);
            }
            $count++;
        }

        return new cleanup_result($count, [get_string('resetcount', 'recerttask_enrolment', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        global $DB;
        return ['enrolments' => (int)$DB->get_field_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $context->courseid, 'userid' => $context->userid]
        )];
    }
}
