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
 * repository.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\cycle;

use dml_exception;
use stdClass;

/**
 * Persistence repository for kopere_recertification cycle records.
 */
class repository {
    /**
     * Loads a kopere_recertification cycle by ID.
     *
     * @param int $cycleid Recertification cycle ID.
     * @return stdClass Result of the operation.
     * @throws dml_exception
     */
    public function get(int $cycleid): stdClass {
        global $DB;
        return $DB->get_record('local_recert_cycle', ['id' => $cycleid], '*', MUST_EXIST);
    }

    /**
     * Returns the open kopere_recertification cycle for a course and user, when one exists.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?stdClass Open cycle record, or null when none exists.
     * @throws dml_exception
     */
    public function get_open(int $courseid, int $userid): ?stdClass {
        global $DB;
        return $DB->get_record_select(
            'local_recert_cycle',
            "courseid = :courseid AND userid = :userid AND status IN ('scheduled','pending','processing','active')",
            ['courseid' => $courseid, 'userid' => $userid],
            '*',
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Returns active.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?stdClass Result of the operation.
     * @throws dml_exception
     */
    public function get_active(int $courseid, int $userid): ?stdClass {
        global $DB;
        $sql = "SELECT *
                  FROM {local_recert_cycle}
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND status IN ('pending', 'processing', 'active')
              ORDER BY number DESC";
        return $DB->get_record_sql($sql, ['courseid' => $courseid, 'userid' => $userid], IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Returns the most recent completed cycle for a course and user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?stdClass Most recently completed cycle record, or null when none exists.
     * @throws dml_exception
     */
    public function get_last_completed(int $courseid, int $userid): ?stdClass {
        global $DB;
        $sql = "SELECT *
                  FROM {local_recert_cycle}
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND status = 'completed'
              ORDER BY number DESC";
        return $DB->get_record_sql($sql, ['courseid' => $courseid, 'userid' => $userid], IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Calculates the next sequential cycle number for a course and user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return int Next sequential cycle number.
     * @throws dml_exception
     */
    public function get_next_number(int $courseid, int $userid): int {
        global $DB;
        $max = $DB->get_field_sql(
            "SELECT MAX(number) FROM {local_recert_cycle} WHERE courseid = :courseid AND userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]
        );
        return ((int)$max) + 1;
    }

    /**
     * Inserts a kopere_recertification cycle record.
     *
     * @param stdClass $record Database record.
     * @return int Resulting integer value.
     * @throws dml_exception
     */
    public function insert(stdClass $record): int {
        global $DB;
        return (int)$DB->insert_record('local_recert_cycle', $record);
    }

    /**
     * Updates a kopere_recertification cycle record.
     *
     * @param stdClass $record Database record.
     * @throws dml_exception
     */
    public function update(stdClass $record): void {
        global $DB;
        $record->timemodified = time();
        $DB->update_record('local_recert_cycle', $record);
    }
}
