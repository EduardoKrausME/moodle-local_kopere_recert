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
 * Read ordered, configured recertification data handling actions.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\task\manager as task_manager;
use local_mcp\security\authenticated_identity;

/**
 * Describe the real configured providers without invoking cleanup or archives.
 */
final class get_execution_plan extends course_read {
    /**
     * Return this MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_get_execution_plan";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetexecutionplan", "local_kopere_recert");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetexecutionplandesc", "local_kopere_recert");
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
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 40],
        ], ["courseid"]);
    }

    /**
     * Execute the authorized MCP operation.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::require_course($courseid, $identity, $this->get_required_capability());
        $items = (new task_manager())->build_plan($courseid)->get_all_items();
        [$offset, $limit] = report_service::pagination($arguments);
        $result = [];
        foreach (array_slice($items, $offset, $limit) as $item) {
            $result[] = [
                "component" => $item->component,
                "taskid" => $item->taskid,
                "origin" => $item->origin,
                "cmid" => $item->cmid,
                "instanceid" => $item->instanceid,
                "activity_name" => $item->activityname,
                "history_enabled" => $item->historyenabled,
                "files_enabled" => $item->filesenabled,
                "cleanup_enabled" => $item->cleanupenabled,
                "sortorder" => $item->sortorder,
            ];
        }
        return [
            "courseid" => $courseid,
            "total" => count($items),
            "offset" => $offset,
            "limit" => $limit,
            "has_more" => $offset + count($result) < count($items),
            "plan" => $result,
        ];
    }
}
