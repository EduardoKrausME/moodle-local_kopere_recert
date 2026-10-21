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
 * Project bounded course recertification reports and state hashes.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\cycle\repository;
use moodle_exception;
use stdClass;

/**
 * Read-only projections of cycle, history and course configuration records.
 */
final class report_service {
    /** Maximum page length. */
    public const MAX_PAGE = 40;

    /**
     * Fetch a cycle and ensure its identifiers match.
     *
     * @param stdClass $cycle Native recertification cycle.
     * @return array
     */
    public static function cycle(stdClass $cycle): array {
        return [
            "cycleid" => (int)$cycle->id,
            "courseid" => (int)$cycle->courseid,
            "userid" => (int)$cycle->userid,
            "number" => (int)$cycle->number,
            "name" => (string)$cycle->name,
            "reason" => (string)$cycle->reason,
            "source" => (string)$cycle->source,
            "status" => (string)$cycle->status,
            "previouscompletedat" => $cycle->previouscompletedat === null
                ? null : (int)$cycle->previouscompletedat,
            "availableat" => $cycle->availableat === null ? null : (int)$cycle->availableat,
            "dueat" => $cycle->dueat === null ? null : (int)$cycle->dueat,
            "startedat" => $cycle->startedat === null ? null : (int)$cycle->startedat,
            "completedat" => $cycle->completedat === null ? null : (int)$cycle->completedat,
            "createdat" => (int)$cycle->timecreated,
        ];
    }

    /**
     * List a learner's recertification cycles.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @param int $offset Page.
     * @param int $limit Page size.
     * @return array
     */
    public static function cycles(int $courseid, int $userid, int $offset, int $limit): array {
        global $DB;
        $where = ["courseid" => $courseid, "userid" => $userid];
        $total = $DB->count_records("local_kopere_recert_cycle", $where);
        $rows = $DB->get_records("local_kopere_recert_cycle", $where,
            "number DESC, id DESC", "*", $offset, $limit);
        return [
            "courseid" => $courseid,
            "userid" => $userid,
            "total" => $total,
            "offset" => $offset,
            "limit" => $limit,
            "has_more" => $offset + count($rows) < $total,
            "cycles" => array_values(array_map([self::class, "cycle"], $rows)),
        ];
    }

    /**
     * Read the archived history of a verified cycle.
     *
     * @param int $cycleid ID of an existing cycle.
     * @param int $userid Authorized learner ID.
     * @param int $courseid Authorized course.
     * @param int $offset Page.
     * @param int $limit Page length.
     * @return array
     */
    public static function history(
        int $cycleid, int $userid, int $courseid, int $offset, int $limit
    ): array {
        global $DB;
        $cycle = (new repository())->get($cycleid);
        if ((int)$cycle->userid !== $userid || (int)$cycle->courseid !== $courseid) {
            throw new moodle_exception("invalidcycle", "local_kopere_recert");
        }
        $where = ["cycleid" => $cycleid, "userid" => $userid, "courseid" => $courseid];
        $total = $DB->count_records("local_kopere_recert_history", $where);
        $rows = $DB->get_records("local_kopere_recert_history", $where,
            "sortorder ASC, id ASC", "*", $offset, $limit);
        $history = [];
        foreach ($rows as $row) {
            $files = $DB->count_records("local_kopere_recert_file", ["historyid" => $row->id]);
            $history[] = [
                "historyid" => (int)$row->id,
                "component" => (string)$row->component,
                "cmid" => $row->cmid === null ? null : (int)$row->cmid,
                "instanceid" => $row->instanceid === null ? null : (int)$row->instanceid,
                "activityname" => (string)$row->activityname,
                "activitytype" => (string)$row->activitytype,
                "completedat" => $row->completedat === null ? null : (int)$row->completedat,
                "grade" => $row->grade === null ? null : (float)$row->grade,
                "html_preview" => substr((string)$row->html, 0, 3000),
                "html_truncated" => strlen((string)$row->html) > 3000,
                "structured_json_preview" => substr((string)$row->datajson, 0, 3000),
                "structured_json_truncated" => strlen((string)$row->datajson) > 3000,
                "file_count" => $files,
            ];
        }
        return [
            "cycle" => self::cycle($cycle),
            "total" => $total,
            "offset" => $offset,
            "limit" => $limit,
            "has_more" => $offset + count($history) < $total,
            "history" => $history,
        ];
    }

    /**
     * Describe the configured recertification rules.
     *
     * @param int $courseid Course ID.
     * @return array Course settings without modifying them.
     */
    public static function config(int $courseid): array {
        global $DB;
        $settings = $DB->get_record("local_kopere_recert_course", ["courseid" => $courseid]);
        if (!$settings) {
            return ["courseid" => $courseid, "configured" => false];
        }
        return [
            "courseid" => $courseid,
            "configured" => true,
            "enabled" => (bool)$settings->enabled,
            "trigger_type" => (string)$settings->triggertype,
            "interval_days" => (int)$settings->intervaldays,
            "fixed_month" => (int)$settings->fixedmonth,
            "fixed_day" => (int)$settings->fixedday,
            "reference_cmid" => $settings->referencecmid === null ? null : (int)$settings->referencecmid,
            "reset_competencies" => (bool)$settings->resetcompetencies,
            "self_enabled" => (bool)$settings->selfenabled,
            "self_reference_type" => (string)$settings->selfreferencetype,
            "self_after_days" => (int)$settings->selfafterdays,
        ];
    }

    /**
     * Fingerprint a learner's recertification cycle state.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @return string
     */
    public static function state_hash(int $courseid, int $userid): string {
        global $DB;
        $rows = $DB->get_records("local_kopere_recert_cycle", [
            "courseid" => $courseid, "userid" => $userid,
        ], "number ASC, id ASC");
        $state = [];
        foreach ($rows as $row) {
            $state[] = [(int)$row->id, (int)$row->number, (string)$row->status,
                (int)$row->timemodified, (int)$row->timecreated];
        }
        return hash("sha256", json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * Reject a stale recertification cycle snapshot.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @param string $expected Expected state.
     * @return void
     */
    public static function require_state(int $courseid, int $userid, string $expected): void {
        if (!preg_match("/^[a-f0-9]{64}$/", $expected) ||
                !hash_equals(self::state_hash($courseid, $userid), $expected)) {
            throw new moodle_exception("mcpstalecycle", "local_kopere_recert");
        }
    }

    /**
     * Describe the bounded pagination parameters.
     *
     * @return array
     */
    public static function pagination_schema(): array {
        return [
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => self::MAX_PAGE],
        ];
    }

    /**
     * Validate requested pagination limits.
     *
     * @param array $arguments Tool input.
     * @return array
     */
    public static function pagination(array $arguments): array {
        return [max(0, (int)($arguments["offset"] ?? 0)),
            max(1, min(self::MAX_PAGE, (int)($arguments["limit"] ?? 20)))];
    }
}
