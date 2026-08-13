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
 * manager.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\status;

use local_kopere_recert\cycle\repository;
use stdClass;

/**
 * Provides logical kopere_recert status checks used by the user interface.
 */
class manager {
    /**
     * Returns the cycle associated with a historical file request.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?stdClass Result of the operation.
     */
    public function get_cycle(int $courseid, int $userid): ?stdClass {
        return (new repository())->get_active($courseid, $userid);
    }

    /**
     * Reports whether a user currently has a pending or active kopere_recert state.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return bool True when kopere_recert is logically required.
     */
    public function is_kopere_recert_required(int $courseid, int $userid): bool {
        return $this->get_cycle($courseid, $userid) !== null;
    }
}
