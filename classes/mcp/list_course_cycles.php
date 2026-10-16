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
 * Read authorized learner cycle history for a course administrator.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_mcp\security\authenticated_identity;

/**
 * Expose learner cycle metadata only with the existing viewallhistory permission.
 */
final class list_course_cycles extends course_read {
    /**
     * Return this MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_list_course_cycles";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcplistcoursecycles", "local_kopere_recert");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcplistcoursecyclesdesc", "local_kopere_recert");
    }

    /**
     * Declare the Moodle capability required by the tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "local/kopere_recert:viewallhistory";
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
        ] + report_service::pagination_schema(), ["courseid", "userid"]);
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
        $userid = (int)$arguments["userid"];
        access::require_course($courseid, $identity, $this->get_required_capability());
        [$offset, $limit] = report_service::pagination($arguments);
        return report_service::cycles($courseid, $userid, $offset, $limit) + [
            "state_hash" => report_service::state_hash($courseid, $userid),
        ];
    }
}
