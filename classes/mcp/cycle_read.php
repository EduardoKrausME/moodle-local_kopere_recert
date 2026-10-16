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
 * Resolve cycle-scoped recertification MCP history READ tools.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use context;
use local_kopere_recert\cycle\repository;
use local_mcp\read\tool\base_tool;

/**
 * Resolve the course from a real cycle, not from caller-supplied context.
 */
abstract class cycle_read extends base_tool {
    /**
     * Resolve the course context for this tool.
     *
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        $cycle = (new repository())->get((int)$arguments["cycleid"]);
        return access::context((int)$cycle->courseid);
    }
}
