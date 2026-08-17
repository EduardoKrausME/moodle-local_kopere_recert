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
 * Date calculator.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\course;

use coding_exception;
use dml_exception;
use invalid_parameter_exception;
use local_kopere_recert\cycle\repository as cycle_repository;
use local_kopere_recert\subplugin\manager as subplugin_manager;
use moodle_exception;
use stdClass;

/**
 * Calculates kopere_recert reference, availability, and due dates.
 */
class date_calculator {
    /** @var cycle_repository Cycle repository. */
    private readonly cycle_repository $cycles;

    /** @var subplugin_manager Subplugin manager. */
    private readonly subplugin_manager $subplugins;

    /**
     * Creates a new date calculator instance.
     *
     * @param cycle_repository $cycles Cycles.
     * @param subplugin_manager $subplugins Subplugins.
     */
    public function __construct(
        cycle_repository $cycles = new cycle_repository(),
        subplugin_manager $subplugins = new subplugin_manager(),
    ) {
        $this->cycles = $cycles;
        $this->subplugins = $subplugins;
    }

    /**
     * Calculates the reference, availability, and due dates for a user.
     *
     * @param stdClass $config Configuration record.
     * @param int $userid User ID.
     * @param int $now Reference timestamp; zero uses the current time.
     * @return ?array Calculated reference, availability, and due timestamps, or null when no reference exists.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function calculate(stdClass $config, int $userid, int $now = 0): ?array {
        global $DB;
        $now = $now ?: time();
        $courseid = (int)$config->courseid;
        $interval = max(0, (int)($config->intervaldays ?? 0)) * DAYSECS;
        if ($config->triggertype !== 'fixeddate' && $interval <= 0) {
            throw new moodle_exception('intervalmustbepositive', 'local_kopere_recert');
        }

        switch ($config->triggertype) {
            case 'enrolment':
                $reference = $this->get_enrolment_date($courseid, $userid);
                if (!$reference) {
                    return null;
                }
                $dueat = $reference + $interval;
                $last = $this->cycles->get_last_completed($courseid, $userid);
                if ($last && !empty($last->completedat) && (int)$last->completedat >= $dueat) {
                    $elapsed = (int)$last->completedat - $reference;
                    $periods = intdiv(max(0, $elapsed), $interval) + 1;
                    $dueat = $reference + ($periods * $interval);
                }
                break;

            case 'completion':
                $reference = (int)$DB->get_field('course_completions', 'timecompleted', [
                    'course' => $courseid,
                    'userid' => $userid,
                ]);
                if (!$reference) {
                    return null;
                }
                $dueat = $reference + $interval;
                break;

            case 'lastkopere_recert':
                $last = $this->cycles->get_last_completed($courseid, $userid);
                if (!$last || empty($last->completedat)) {
                    return null;
                }
                $reference = (int)$last->completedat;
                $dueat = $reference + $interval;
                break;

            case 'fixeddate':
                $month = max(1, min(12, (int)$config->fixedmonth));
                $day = max(1, min(31, (int)$config->fixedday));
                $year = (int)userdate($now, '%Y', 99, false);
                $dueat = $this->safe_date($year, $month, $day);
                if ($dueat < $now) {
                    $dueat = $this->safe_date($year + 1, $month, $day);
                }
                $reference = $dueat;
                break;

            case 'certificate':
                $cmid = (int)($config->referencecmid ?? 0);
                if (!$cmid) {
                    throw new moodle_exception('missingcertificatereference', 'local_kopere_recert');
                }
                $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
                $provider = $this->subplugins->get_for_component('mod_' . $cm->modname);
                if (!$provider || !$provider instanceof reference_date_provider_interface) {
                    throw new moodle_exception('certificatereferenceunavailable', 'local_kopere_recert');
                }
                $reference = $provider->get_reference_date($userid, $courseid, $cmid, (int)$cm->instance);
                if (!$reference) {
                    return null;
                }
                $dueat = $reference + $interval;
                break;

            default:
                throw new invalid_parameter_exception('Unknown kopere_recert trigger type.');
        }

        return [
            'referenceat' => $reference,
            'dueat' => $dueat,
            'availableat' => $this->calculate_available_at($config, $dueat),
        ];
    }

    /**
     * Returns the earliest effective enrolment start date for the user in the course.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?int Effective enrolment timestamp, or null when the user is not actively enrolled.
     * @throws dml_exception
     */
    private function get_enrolment_date(int $courseid, int $userid): ?int {
        global $DB;
        $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid
                   AND ue.status = 0";
        $value = $DB->get_field_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        return $value ? (int)$value : null;
    }

    /**
     * Calculates when advance kopere_recert notices become available.
     *
     * @param stdClass $config Configuration record.
     * @param int $dueat Recertification due timestamp.
     * @return int Resulting integer value.
     * @throws dml_exception
     */
    private function calculate_available_at(stdClass $config, int $dueat): int {
        global $DB;
        $maxoffset = $DB->get_field_sql(
            "SELECT MAX(offsetdays)
               FROM {local_kopere_recert_notice}
              WHERE courseid = :courseid
                AND enabled = 1",
            ['courseid' => $config->courseid]
        );
        return $dueat - max(0, (int)$maxoffset) * DAYSECS;
    }

    /**
     * Builds a valid timestamp while clamping invalid month-end days.
     *
     * @param int $year Calendar year.
     * @param int $month Calendar month.
     * @param int $day Calendar day.
     * @return int Resulting integer value.
     * @throws coding_exception
     */
    private function safe_date(int $year, int $month, int $day): int {
        $maxday = (int)date('t', make_timestamp($year, $month, 1, 0, 0, 0, 99, false));
        return make_timestamp($year, $month, min($day, $maxday), 0, 0, 0, 99, false);
    }
}
