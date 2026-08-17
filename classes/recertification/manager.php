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
 * Recertification manager.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\recertification;

use context_course;
use core\lock\lock_config;
use invalid_parameter_exception;
use local_kopere_recert\course\reference_date_provider_interface;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\cycle\repository as cycle_repository;
use local_kopere_recert\event\kopere_recert_created;
use local_kopere_recert\task\execute_kopere_recert;
use moodle_exception;
use required_capability_exception;
use stdClass;
use Throwable;

/**
 * Coordinates manual and self-service recertification requests.
 */
class manager {
    /** @var cycle_manager Cycle lifecycle manager. */
    private readonly cycle_manager $cycles;

    /** @var cycle_repository Cycle persistence repository. */
    private readonly cycle_repository $repository;

    /**
     * Creates a new manager instance.
     *
     * @param cycle_manager|null $cycles Cycle lifecycle manager.
     * @param cycle_repository|null $repository Cycle persistence repository.
     */
    public function __construct(?cycle_manager $cycles = null, ?cycle_repository $repository = null) {
        $this->cycles = $cycles ?? new cycle_manager();
        $this->repository = $repository ?? new cycle_repository();
    }

    /**
     * Creates a cycle and queues its ad-hoc execution task.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param string $name Human-readable name.
     * @param string $reason Human-readable recertification reason.
     * @param string $source Recertification source.
     * @param int|null $createdby User ID that created the cycle.
     * @return stdClass Created cycle.
     */
    public function create_and_queue(
        int $courseid,
        int $userid,
        string $name,
        string $reason,
        string $source,
        ?int $createdby
    ): stdClass {
        global $DB, $USER;

        if (trim($reason) === '') {
            throw new invalid_parameter_exception(get_string('reasonrequired', 'local_kopere_recert'));
        }

        $context = context_course::instance($courseid);
        if (!is_enrolled($context, $userid, '', true)) {
            throw new moodle_exception('usernotenrolled', 'local_kopere_recert');
        }

        if ($source === cycle_manager::SOURCE_MANUAL_USER) {
            if ($userid !== (int) $USER->id) {
                throw new required_capability_exception(
                    $context,
                    'local/kopere_recert:recertifyself',
                    'nopermissions',
                    ''
                );
            }
            require_capability('local/kopere_recert:recertifyself', $context);
            $availability = $this->get_self_availability($courseid, $userid);
            if (!$availability['allowed']) {
                throw new moodle_exception(
                    'selfkopere_recertnotavailable',
                    'local_kopere_recert',
                    '',
                    userdate((int) $availability['availableat'])
                );
            }
        } else if ($source === cycle_manager::SOURCE_BULK) {
            require_capability('local/kopere_recert:bulkrecertify', $context);
        } else {
            require_capability('local/kopere_recert:recertify', $context);
        }

        $lockfactory = lock_config::get_lock_factory('local_kopere_recert');
        $lock = $lockfactory->get_lock("local_kopere_recert:{$courseid}:{$userid}", 0);
        if (!$lock) {
            throw new moodle_exception('kopere_recertlocked', 'local_kopere_recert');
        }

        try {
            $open = $this->repository->get_open($courseid, $userid);
            if ($open && $open->status !== cycle_manager::STATUS_SCHEDULED) {
                throw new moodle_exception('activecycleexists', 'local_kopere_recert');
            }

            // A future automatic cycle is superseded by an explicit manual request.
            if ($open && $open->status === cycle_manager::STATUS_SCHEDULED) {
                $open->status = cycle_manager::STATUS_CANCELLED;
                $open->timemodified = time();
                $DB->update_record('local_kopere_recert_cycle', $open);
            }

            $cycle = $this->cycles->create(
                $courseid,
                $userid,
                $name,
                $reason,
                $source,
                $createdby
            );

            kopere_recert_created::create_from_cycle((int) $cycle->id)->trigger();
            try {
                (new \local_kopere_recert\notification\manager())->send_configured_event(
                    (int) $cycle->id,
                    'kopere_recert_created',
                    false
                );
            } catch (Throwable $e) {
                (new \local_kopere_recert\log\manager())->add(
                    (int) $cycle->id,
                    null,
                    'notification',
                    null,
                    null,
                    'failed',
                    $e->getMessage()
                );
            }

            $task = new execute_kopere_recert();
            $task->set_custom_data([
                'userid' => $userid,
                'courseid' => $courseid,
                'cycleid' => (int) $cycle->id,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);

            return $cycle;
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns the current self-recertification availability for a user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param int $now Reference timestamp; zero uses the current time.
     * @return array Availability data.
     */
    public function get_self_availability(int $courseid, int $userid, int $now = 0): array {
        global $DB;

        $now = $now ?: time();
        $config = $DB->get_record('local_kopere_recert_course', ['courseid' => $courseid, 'enabled' => 1]);
        if (!$config || empty($config->selfenabled)) {
            return ['allowed' => false, 'availableat' => 0];
        }

        $reference = null;
        $referencetype = (string) ($config->selfreferencetype ?? 'enrolment');
        switch ($referencetype) {
            case 'enrolment':
                $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE e.courseid = :courseid
                           AND ue.userid = :userid
                           AND ue.status = 0";
                $reference = (int) $DB->get_field_sql($sql, [
                    'courseid' => $courseid,
                    'userid' => $userid,
                ]);
                break;

            case 'completion':
                $reference = (int) $DB->get_field('course_completions', 'timecompleted', [
                    'course' => $courseid,
                    'userid' => $userid,
                ]);
                break;

            case 'lastkopere_recert':
                $last = $this->repository->get_last_completed($courseid, $userid);
                $reference = $last && !empty($last->completedat) ? (int) $last->completedat : 0;
                break;

            case 'certificate':
                $cmid = (int) ($config->referencecmid ?? 0);
                if (!$cmid) {
                    throw new moodle_exception('missingcertificatereference', 'local_kopere_recert');
                }
                $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
                $provider = (new \local_kopere_recert\subplugin\manager())->get_for_component(
                    'mod_' . $cm->modname
                );
                if (!$provider || !$provider instanceof reference_date_provider_interface) {
                    throw new moodle_exception('certificatereferenceunavailable', 'local_kopere_recert');
                }
                $reference = (int) ($provider->get_reference_date(
                    $userid,
                    $courseid,
                    $cmid,
                    (int) $cm->instance
                ) ?? 0);
                break;

            default:
                throw new invalid_parameter_exception(
                    get_string('invalidselfreference', 'local_kopere_recert')
                );
        }

        if (!$reference) {
            return ['allowed' => false, 'availableat' => 0];
        }

        $availableat = $reference + max(0, (int) $config->selfafterdays) * DAYSECS;
        return ['allowed' => $availableat <= $now, 'availableat' => $availableat];
    }
}
