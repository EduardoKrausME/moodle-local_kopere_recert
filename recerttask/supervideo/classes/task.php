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
 * Super Video recertification task provider.
 *
 * @package   recerttask_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_supervideo;

use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized recertification task provider for Super Video.
 */
final class task implements task_plugin_interface {
    /** @return string Moodle component name. */
    public static function get_component(): string {
        return 'mod_supervideo';
    }

    /** @return string Localized provider name. */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_supervideo');
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

        $activity = $DB->get_record('supervideo', ['id' => $context->instanceid], '*', MUST_EXIST);
        $view = $DB->get_record('supervideo_view', [
            'cm_id' => $context->cmid,
            'user_id' => $context->userid,
        ]);

        $completedat = null;
        if ($view && !empty($activity->completionpercent)
                && (int)$view->percent >= (int)$activity->completionpercent && !empty($view->timemodified)) {
            $completedat = (int)$view->timemodified;
        }

        $data = [];
        if ($view) {
            $data = [
                'currenttime' => (int)$view->currenttime,
                'duration' => (int)$view->duration,
                'percent' => (int)$view->percent,
                'map' => (string)($view->map ?? ''),
                'timecreated' => (int)($view->timecreated ?? 0),
                'timemodified' => (int)($view->timemodified ?? 0),
            ];
        }

        $html = $OUTPUT->render_from_template('recerttask_supervideo/history', [
            'activityname' => format_string($activity->name),
            'hasview' => (bool)$view,
            'percent' => $view ? (int)$view->percent : 0,
            'currenttime' => $view ? (int)$view->currenttime : 0,
            'duration' => $view ? (int)$view->duration : 0,
            'lastview' => $view && !empty($view->timemodified) ? userdate((int)$view->timemodified) : '',
        ]);

        return new history_result(
            completedat: $completedat,
            html: $html,
            data: $data,
            messages: [get_string('archivedcount', 'recerttask_supervideo', $view ? 1 : 0)]
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
     * Removes the user's Super Video viewing state.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result {
        global $DB;

        $params = [
            'cm_id' => $context->cmid,
            'user_id' => $context->userid,
        ];
        $count = $DB->count_records('supervideo_view', $params);
        $DB->delete_records('supervideo_view', $params);

        return new cleanup_result($count, [get_string('removedcount', 'recerttask_supervideo', $count)]);
    }

    /**
     * Returns a non-destructive description of affected Super Video data.
     *
     * @param task_context $context Execution context.
     * @return array Structured impact description.
     */
    public function describe(task_context $context): array {
        global $DB;

        return [
            'views' => $DB->count_records('supervideo_view', [
                'cm_id' => $context->cmid,
                'user_id' => $context->userid,
            ]),
        ];
    }
}
