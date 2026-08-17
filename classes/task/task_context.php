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
 * task execution context.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

/**
 * Immutable execution context passed to generic tasks and subplugin providers.
 */
class task_context {
    /** @var int User ID. */
    public readonly int $userid;

    /** @var int Course ID. */
    public readonly int $courseid;

    /** @var int|null Course module ID. */
    public readonly ?int $cmid;

    /** @var int|null Activity instance ID. */
    public readonly ?int $instanceid;

    /** @var int Moodle context ID. */
    public readonly int $contextid;

    /** @var int Recertification cycle ID. */
    public readonly int $cycleid;

    /** @var int Recertification ID alias kept for SQL placeholder compatibility. */
    public readonly int $recertificationid;

    /** @var bool Whether the operation is running in simulation mode. */
    public readonly bool $simulation;

    /**
     * Creates a new task context instance.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int|null $cmid Course module ID.
     * @param int|null $instanceid Activity instance ID.
     * @param int $contextid Context ID.
     * @param int $cycleid Recertification cycle ID.
     * @param int $recertificationid Recertification ID alias.
     * @param bool $simulation Whether the operation is running in simulation mode.
     */
    public function __construct(
        int $userid,
        int $courseid,
        ?int $cmid,
        ?int $instanceid,
        int $contextid,
        int $cycleid,
        int $recertificationid,
        bool $simulation = false
    ) {
        $this->userid = $userid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->instanceid = $instanceid;
        $this->contextid = $contextid;
        $this->cycleid = $cycleid;
        $this->recertificationid = $recertificationid;
        $this->simulation = $simulation;
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
            'kopere_recertid' => $this->recertificationid,
        ];
    }
}
