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
 * reference_date_provider_interface.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\course;

/**
 * Contract for providers that expose a component-specific kopere_recertification reference date.
 */
interface reference_date_provider_interface {
    /**
     * Returns the component-specific timestamp used as a kopere_recertification reference.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cmid Course module ID.
     * @param int $instanceid Activity instance ID.
     * @return ?int Reference timestamp, or null when no component data exists.
     */
    public function get_reference_date(int $userid, int $courseid, int $cmid, int $instanceid): ?int;
}
