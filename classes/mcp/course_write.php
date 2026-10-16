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
 * Resolve course-scoped confirmed MCP recertification writes.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use context;
use local_mcp\write\tool\base_tool;
use moodle_exception;

/**
 * Base for explicitly confirmed operations with real Moodle side effects.
 */
abstract class course_write extends base_tool {
    /**
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return access::context((int)$arguments["courseid"]);
    }

    /**
     * @param string $reason Text entered by the connected user.
     * @return string
     */
    protected function validated_reason(string $reason): string {
        if (trim($reason) === "" || \core_text::strlen($reason) > 255) {
            throw new moodle_exception("mcpreasoninvalid", "local_kopere_recert");
        }
        $reason = clean_param(trim($reason), PARAM_TEXT);
        if ($reason === "") {
            throw new moodle_exception("mcpreasoninvalid", "local_kopere_recert");
        }
        return $reason;
    }
}
