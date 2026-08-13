<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace recerttask_supervideo;

use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized recertification task provider for Super Video.
 */
final class task implements task_plugin_interface {
    public static function get_component(): string {
        return 'mod_supervideo';
    }

    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_supervideo');
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

    public function get_files(task_context $context, int $historyid): array {
        return [];
    }

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
