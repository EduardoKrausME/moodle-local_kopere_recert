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
 * Create and enqueue one confirmed recertification cycle via the native manager.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\cycle\repository;
use local_kopere_recert\recertification\manager;
use local_mcp\security\authenticated_identity;

/**
 * Preserve the native learner self-service and administrative permission split.
 */
final class queue_recertification extends course_write {
    /**
     * Return this MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_queue";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpqueue", "local_kopere_recert");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpqueuedesc", "local_kopere_recert");
    }

    /**
     * Declare the Moodle capability required by the tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        // Baseline access covers both learners and managers; native specific checks run below.
        return "moodle/course:view";
    }

    /**
     * Mark the write operation as destructive.
     *
     * @return bool
     */
    public function is_destructive(): bool {
        return true;
    }

    /**
     * Declare validated MCP input parameters.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "userid" => ["type" => "integer", "minimum" => 1],
            "reason" => ["type" => "string", "minLength" => 1, "maxLength" => 255],
            "expected_state_hash" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"],
        ], ["courseid", "userid", "reason", "expected_state_hash"]);
    }

    /**
     * Explicitly preview which learner's records will be archived and reset later.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Caller.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        $userid = (int)$arguments["userid"];
        [$source] = access::request_context($courseid, $userid, $identity);
        report_service::require_state($courseid, $userid, (string)$arguments["expected_state_hash"]);
        $reason = $this->validated_reason((string)$arguments["reason"]);
        $open = (new repository())->get_open($courseid, $userid);
        if ($open && $open->status !== "scheduled") {
            throw new \moodle_exception("activecycleexists", "local_kopere_recert");
        }
        return [
            "operation" => $this->get_name(),
            "courseid" => $courseid,
            "userid" => $userid,
            "reason" => $reason,
            "source" => $source,
            "scheduled_cycle_will_be_superseded" => $open !== null,
            "will_queue_native_adhoc_task" => true,
            "execution_later_may_reset_grades_and_completions" => true,
            "archives_run_before_cleanup" => true,
            "will_emit_events_and_notifications" => true,
        ];
    }

    /**
     * Native manager owns locking, status changes, task queue, events and notices.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Caller.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->preview($arguments, $identity);
        $courseid = (int)$arguments["courseid"];
        $userid = (int)$arguments["userid"];
        [$source] = access::request_context($courseid, $userid, $identity);
        $reason = $this->validated_reason((string)$arguments["reason"]);
        $cycle = (new manager())->create_and_queue($courseid, $userid,
            $reason, $reason, $source, $identity->userid);
        return [
            "courseid" => $courseid,
            "userid" => $userid,
            "cycleid" => (int)$cycle->id,
            "status" => (string)$cycle->status,
            "number" => (int)$cycle->number,
            "queued" => true,
            "execution_completed" => false,
            "history_tool" => $userid === $identity->userid
                ? "kopere_recert_list_my_cycles" : "kopere_recert_list_course_cycles",
        ];
    }
}
