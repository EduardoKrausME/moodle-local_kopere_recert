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
 * Read course recertification triggers and self-service settings.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_mcp\security\authenticated_identity;

/**
 * Administrative READ of the existing course configuration.
 */
final class get_course_config extends course_read {
    /**
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_get_course_config";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetcourseconfig", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetcourseconfigdesc", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_required_capability(): string {
        return "local/kopere_recert:manage";
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
        ], ["courseid"]);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::require_course($courseid, $identity, $this->get_required_capability());
        return report_service::config($courseid);
    }
}
