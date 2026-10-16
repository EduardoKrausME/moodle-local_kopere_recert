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
 * Queue a bounded batch of confirmed native learner recertifications.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\cycle\repository;
use local_kopere_recert\recertification\manager;
use local_mcp\security\authenticated_identity;
use moodle_exception;
use Throwable;

/**
 * Maximum ten learners per confirmed MCP call; each uses its native lock and task.
 */
final class queue_bulk extends course_write {
    /** Maximum learners per request. */
    public const MAX_USERS = 10;

    /**
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_queue_bulk";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpqueuebulk", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpqueuebulkdesc", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_required_capability(): string {
        return "local/kopere_recert:bulkrecertify";
    }

    /**
     * @return bool
     */
    public function is_destructive(): bool {
        return true;
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "reason" => ["type" => "string", "minLength" => 1, "maxLength" => 255],
            "targets" => [
                "type" => "array", "minItems" => 1, "maxItems" => self::MAX_USERS,
                "items" => [
                    "type" => "object",
                    "properties" => [
                        "userid" => ["type" => "integer", "minimum" => 1],
                        "expected_state_hash" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"],
                    ],
                    "required" => ["userid", "expected_state_hash"],
                    "additionalProperties" => false,
                ],
            ],
        ], ["courseid", "reason", "targets"]);
    }

    /**
     * Check all users before the first real task is enqueued.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Caller.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        $reason = $this->validated_reason((string)$arguments["reason"]);
        access::require_course($courseid, $identity, $this->get_required_capability());
        access::require_current_identity($identity);
        $targets = (array)$arguments["targets"];
        if (!$targets || count($targets) > self::MAX_USERS) {
            throw new moodle_exception("mcpinvalidtargets", "local_kopere_recert");
        }
        $users = [];
        $seen = [];
        foreach ($targets as $target) {
            if (!is_array($target) || !isset($target["userid"], $target["expected_state_hash"])) {
                throw new moodle_exception("mcpinvalidtargets", "local_kopere_recert");
            }
            $userid = (int)$target["userid"];
            if ($userid <= 0 || isset($seen[$userid])) {
                throw new moodle_exception("mcpinvalidtargets", "local_kopere_recert");
            }
            access::require_enrolled($courseid, $userid, $identity, $this->get_required_capability());
            report_service::require_state($courseid, $userid, (string)$target["expected_state_hash"]);
            $open = (new repository())->get_open($courseid, $userid);
            if ($open && $open->status !== cycle_manager::STATUS_SCHEDULED) {
                throw new moodle_exception("activecycleexists", "local_kopere_recert");
            }
            $users[] = [
                "userid" => $userid,
                "will_supersede_scheduled_cycle" => $open !== null,
            ];
            $seen[$userid] = true;
        }
        return [
            "operation" => $this->get_name(),
            "courseid" => $courseid,
            "reason" => $reason,
            "targets" => $users,
            "will_queue_independent_native_tasks" => true,
            "may_archive_and_reset_learner_records" => true,
            "partial_success_possible" => true,
        ];
    }

    /**
     * Queue each cycle through the existing recertification manager.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Caller.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->preview($arguments, $identity);
        $courseid = (int)$arguments["courseid"];
        $reason = $this->validated_reason((string)$arguments["reason"]);
        $results = [];
        foreach ($arguments["targets"] as $target) {
            $userid = (int)$target["userid"];
            try {
                report_service::require_state($courseid, $userid, (string)$target["expected_state_hash"]);
                $cycle = (new manager())->create_and_queue($courseid, $userid,
                    $reason, $reason, cycle_manager::SOURCE_BULK, $identity->userid);
                $results[] = ["userid" => $userid, "queued" => true,
                    "cycleid" => (int)$cycle->id, "status" => (string)$cycle->status];
            } catch (Throwable $error) {
                $results[] = ["userid" => $userid, "queued" => false,
                    "error_code" => $error instanceof moodle_exception
                        ? $error->errorcode : "queue_failed"];
            }
        }
        return [
            "courseid" => $courseid,
            "results" => $results,
            "queued_count" => count(array_filter($results,
                static fn(array $result): bool => $result["queued"])),
            "execution_completed" => false,
        ];
    }
}
