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
 * Competency recertification task provider.
 *
 * @package   recerttask_competency
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_competency;

use core_competency\user_competency_course;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;
use moodle_exception;

/**
 * Specialized kopere_recert task provider for competencies.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component handled by this provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string {
        return 'core_competency';
    }

    /**
     * Returns the localized provider name.
     *
     * @return string Localized provider name.
     */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_competency');
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
        return 40;
    }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $OUTPUT;

        $records = user_competency_course::get_multiple($context->userid, $context->courseid);
        $rows = [];
        foreach ($records as $record) {
            $competency = $record->get_competency();
            $rows[] = [
                'competencyid' => $record->get('competencyid'),
                'name' => format_string($competency->get('shortname')),
                'grade' => $record->get('grade') === null ? '' : (string)$record->get('grade'),
                'proficiency' => $record->get('proficiency') ? get_string('yes') : get_string('no'),
                'timemodified' => $record->get('timemodified') ? userdate($record->get('timemodified')) : '',
            ];
        }
        return new history_result(
            html: $OUTPUT->render_from_template('recerttask_competency/history', [
                'rows' => $rows,
                'count' => count($rows),
            ]),
            data: ['competencies' => count($rows)]
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
        $records = user_competency_course::get_multiple($context->userid, $context->courseid);
        $count = 0;
        foreach ($records as $record) {
            if (!$record->delete()) {
                throw new moodle_exception('competencycleanupfailed', 'local_kopere_recert');
            }
            $count++;
        }
        return new cleanup_result($count, [get_string('resetcount', 'recerttask_competency', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        return ['competencies' => count(
            user_competency_course::get_multiple($context->userid, $context->courseid)
        )];
    }
}
