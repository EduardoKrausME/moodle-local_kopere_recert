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
 * Simulate native recertification under rollback after MCP confirmation.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\recertification\simulator;
use local_kopere_recert\task\manager as task_manager;
use local_mcp\security\authenticated_identity;

/**
 * Simulation performs realistic operations in a rollback-only transaction.
 */
final class simulate_recertification extends course_write {
    /**
     * Return this MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_simulate";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpsimulate", "local_kopere_recert");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpsimulatedesc", "local_kopere_recert");
    }

    /**
     * Declare the Moodle capability required by the tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "local/kopere_recert:simulate";
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
     * Validate all inputs without initiating even a rollback transaction.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity User.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        $userid = (int)$arguments["userid"];
        access::require_enrolled($courseid, $userid, $identity, $this->get_required_capability());
        access::require_current_identity($identity);
        report_service::require_state($courseid, $userid, (string)$arguments["expected_state_hash"]);
        $reason = $this->validated_reason((string)$arguments["reason"]);
        $items = (new task_manager())->build_plan($courseid)->get_all_items();
        return [
            "operation" => $this->get_name(),
            "courseid" => $courseid,
            "userid" => $userid,
            "reason" => $reason,
            "configured_steps" => count($items),
            "steps_with_cleanup" => count(array_filter($items,
                static fn($item): bool => $item->cleanupenabled)),
            "will_run_native_pipeline" => true,
            "always_rolls_back" => true,
            "no_notifications_or_queued_tasks" => true,
            "requires_confirmation" => true,
        ];
    }

    /**
     * Run the rollback-only simulator, not the real queue executor.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity User.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->preview($arguments, $identity);
        $courseid = (int)$arguments["courseid"];
        $userid = (int)$arguments["userid"];
        $reason = $this->validated_reason((string)$arguments["reason"]);
        $report = (new simulator())->simulate($courseid, $userid,
            $reason, $reason, cycle_manager::SOURCE_MANUAL_ADMIN, $identity->userid);
        $plan = [];
        foreach ($report["plan"]->get_all_items() as $item) {
            $plan[] = [
                "component" => $item->component,
                "cmid" => $item->cmid,
                "history" => $item->historyenabled,
                "files" => $item->filesenabled,
                "cleanup" => $item->cleanupenabled,
            ];
        }
        return [
            "courseid" => $courseid,
            "userid" => $userid,
            "rolled_back" => !empty($report["rolledback"]),
            "plan" => array_slice($plan, 0, 40),
            "total_plan_items" => count($plan),
            "history_count" => count($report["historyids"]),
            "files" => array_slice((array)$report["files"], 0, 40),
            "activity_cleanup" => array_slice((array)$report["activitycleanup"], 0, 40),
            "system_cleanup" => array_slice((array)$report["systemcleanup"], 0, 40),
            "persisted_cycle" => false,
            "notifications_sent" => false,
        ];
    }
}
