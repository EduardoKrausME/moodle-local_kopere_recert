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
 * task_context.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

/**
 * Immutable execution context passed to generic tasks and subplugin providers.
 */
class task_context {
    /**
     * Creates a new task context instance.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param ?int $cmid Course module ID.
     * @param ?int $instanceid Activity instance ID.
     * @param int $contextid Context ID.
     * @param int $cycleid Recertification cycle ID.
     * @param int $kopere_recertificationid Recertification ID alias.
     * @param bool $simulation Whether the operation is running in simulation mode.
     */
    public function __construct(
        public readonly int $userid,
        public readonly int $courseid,
        public readonly ?int $cmid,
        public readonly ?int $instanceid,
        public readonly int $contextid,
        public readonly int $cycleid,
        public readonly int $kopere_recertificationid,
        public readonly bool $simulation = false,
    ) {
    }

    /**
     * Returns the bound parameters available to task SQL operations.
     *
     * @return array Bound execution parameters keyed by placeholder name.
     */
    public function get_sql_params(): array {
        return [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'cmid' => $this->cmid ?? 0,
            'instanceid' => $this->instanceid ?? 0,
            'contextid' => $this->contextid,
            'cycleid' => $this->cycleid,
            'kopere_recertificationid' => $this->kopere_recertificationid,
        ];
    }
}
