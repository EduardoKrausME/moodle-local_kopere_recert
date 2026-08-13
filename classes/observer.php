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
 * observer.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert;

use coding_exception;
use core\event\course_completed;
use dml_exception;
use local_kopere_recert\cycle\manager;
use local_kopere_recert\event\kopere_recert_completed;
use Throwable;

/**
 * Handles Moodle events that change kopere_recert cycle state.
 */
class observer {
    /**
     * Handles a new Moodle course completion for an active kopere_recert cycle.
     *
     * @param course_completed $event Moodle event instance.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function course_completed(course_completed $event): void {
        global $DB;

        $courseid = $event->courseid;
        $userid = $event->relateduserid;
        if (!$courseid || !$userid) {
            return;
        }

        $cycle = $DB->get_record_select(
            'local_kopere_recert_cycle',
            "courseid = :courseid AND userid = :userid AND status = 'active'",
            ['courseid' => $courseid, 'userid' => $userid],
            '*',
            IGNORE_MULTIPLE
        );
        if (!$cycle) {
            return;
        }

        // A valid completion must occur after the cycle became active/started.
        $completiontime = $event->timecreated ?: time();
        if (!empty($cycle->startedat) && $completiontime < (int)$cycle->startedat) {
            return;
        }

        (new manager())->mark_completed((int)$cycle->id, $completiontime);
        kopere_recert_completed::create_from_cycle((int)$cycle->id)->trigger();

        try {
            (new notification\manager())->send_configured_event((int)$cycle->id, 'kopere_recert_completed');
        } catch (Throwable $e) {
            (new log\manager())->add(
                (int)$cycle->id,
                null,
                'notification',
                null,
                null,
                'failed',
                $e->getMessage()
            );
        }
    }
}
