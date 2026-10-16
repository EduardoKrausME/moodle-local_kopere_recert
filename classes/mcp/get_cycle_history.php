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
 * Read an authorized learner recertification archive.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_kopere_recert\cycle\repository;
use local_mcp\security\authenticated_identity;

/**
 * Provide bounded historical snapshots only to viewallhistory holders.
 */
final class get_cycle_history extends cycle_read {
    /**
     * @return string
     */
    public function get_name(): string {
        return "kopere_recert_get_cycle_history";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetcyclehistory", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetcyclehistorydesc", "local_kopere_recert");
    }

    /**
     * @return string
     */
    public function get_required_capability(): string {
        return "local/kopere_recert:viewallhistory";
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cycleid" => ["type" => "integer", "minimum" => 1],
        ] + report_service::pagination_schema(), ["cycleid"]);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $cycle = (new repository())->get((int)$arguments["cycleid"]);
        access::require_course((int)$cycle->courseid, $identity, $this->get_required_capability());
        [$offset, $limit] = report_service::pagination($arguments);
        return report_service::history((int)$cycle->id, (int)$cycle->userid,
            (int)$cycle->courseid, $offset, $limit);
    }
}
