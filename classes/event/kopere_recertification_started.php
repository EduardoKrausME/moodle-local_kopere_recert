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
 * recertification_started.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\event;

use coding_exception;
use context_course;
use core\event\base;
use dml_exception;

/**
 * Moodle event emitted when a kopere_recertification cycle is started.
 */
class kopere_recertification_started extends base {
    /**
     * Initializes the event properties.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_recert_cycle';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localized name of this provider.
     *
     * @return string Localized provider name.
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string('event_kopere_recertification_started', 'local_kopere_recertification');
    }

    /**
     * Returns the human-readable event description.
     *
     * @return string Resulting string value.
     * @throws coding_exception
     */
    public function get_description(): string {
        return get_string('event_kopere_recertification_started_description', 'local_kopere_recertification', (object)[
            'userid' => $this->relateduserid,
            'courseid' => $this->courseid,
            'cycleid' => $this->objectid,
        ]);
    }

    /**
     * Creates an event instance populated from a kopere_recertification cycle.
     *
     * @param int $cycleid Recertification cycle ID.
     * @return base Result of the operation.
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function create_from_cycle(int $cycleid): base {
        global $DB;
        $cycle = $DB->get_record('local_recert_cycle', ['id' => $cycleid], '*', MUST_EXIST);
        return self::create([
            'context' => context_course::instance($cycle->courseid),
            'objectid' => $cycleid,
            'relateduserid' => $cycle->userid,
            'courseid' => $cycle->courseid,
            'other' => [],
        ]);
    }
}
