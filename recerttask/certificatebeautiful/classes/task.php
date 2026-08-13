<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace recerttask_certificatebeautiful;

use local_kopere_recert\course\reference_date_provider_interface;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized recertification task provider for Beautiful Certificate.
 */
final class task implements task_plugin_interface, reference_date_provider_interface {
    public static function get_component(): string {
        return 'mod_certificatebeautiful';
    }

    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_certificatebeautiful');
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

        $activity = $DB->get_record('certificatebeautiful', ['id' => $context->instanceid], '*', MUST_EXIST);
        $issue = $this->get_issue($context->userid, (int)$context->cmid, (int)$context->instanceid);

        $data = [];
        if ($issue) {
            $data = [
                'issueid' => (int)$issue->id,
                'code' => (string)($issue->code ?? ''),
                'version' => $issue->version === null ? null : (int)$issue->version,
                'timecreated' => (int)$issue->timecreated,
            ];
        }

        $html = $OUTPUT->render_from_template('recerttask_certificatebeautiful/history', [
            'activityname' => format_string($activity->name),
            'hasissue' => (bool)$issue,
            'code' => $issue ? (string)($issue->code ?? '') : '',
            'version' => $issue && $issue->version !== null ? (int)$issue->version : '',
            'issuedat' => $issue && !empty($issue->timecreated) ? userdate((int)$issue->timecreated) : '',
        ]);

        return new history_result(
            completedat: $issue ? (int)$issue->timecreated : null,
            html: $html,
            data: $data,
            messages: [get_string('archivedcount', 'recerttask_certificatebeautiful', $issue ? 1 : 0)]
        );
    }

    public function get_files(task_context $context, int $historyid): array {
        return [];
    }

    public function cleanup(task_context $context): cleanup_result {
        global $DB;

        $params = [
            'userid' => $context->userid,
            'cmid' => $context->cmid,
            'certificatebeautifulid' => $context->instanceid,
        ];
        $count = $DB->count_records('certificatebeautiful_issue', $params);
        $DB->delete_records('certificatebeautiful_issue', $params);

        return new cleanup_result($count, [get_string('removedcount', 'recerttask_certificatebeautiful', $count)]);
    }

    public function describe(task_context $context): array {
        global $DB;

        return [
            'issues' => $DB->count_records('certificatebeautiful_issue', [
                'userid' => $context->userid,
                'cmid' => $context->cmid,
                'certificatebeautifulid' => $context->instanceid,
            ]),
        ];
    }

    public function get_reference_date(int $userid, int $courseid, int $cmid, int $instanceid): ?int {
        $issue = $this->get_issue($userid, $cmid, $instanceid);
        return $issue && !empty($issue->timecreated) ? (int)$issue->timecreated : null;
    }

    private function get_issue(int $userid, int $cmid, int $instanceid): ?object {
        global $DB;

        $issue = $DB->get_record('certificatebeautiful_issue', [
            'userid' => $userid,
            'cmid' => $cmid,
            'certificatebeautifulid' => $instanceid,
        ]);
        return $issue ?: null;
    }
}
