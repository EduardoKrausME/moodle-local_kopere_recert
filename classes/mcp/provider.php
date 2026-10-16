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
 * Discover optional Moodle MCP recertification tools.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Auto-register optional recertification tools when local_mcp is present.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * @return array
     */
    public function get_read_tools(): array {
        return [
            new list_my_cycles(),
            new list_course_cycles(),
            new get_my_history(),
            new get_cycle_history(),
            new get_course_config(),
            new get_execution_plan(),
        ];
    }

    /**
     * @return array
     */
    public function get_write_tools(): array {
        return [new simulate_recertification(), new queue_recertification(), new queue_bulk()];
    }
}
