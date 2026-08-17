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
 * Scheduler manager.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\scheduler;

use context_course;
use core\lock\lock_config;
use local_kopere_recert\course\date_calculator;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\cycle\repository as cycle_repository;
use local_kopere_recert\event\kopere_recert_created;
use local_kopere_recert\notification\manager as notification_manager;
use local_kopere_recert\task\execute_kopere_recert;
use stdClass;
use Throwable;

/**
 * Discovers scheduled kopere_recert candidates and queues isolated work.
 */
class manager {
    /** @var date_calculator Date calculator. */
    private readonly date_calculator $calculator;

    /** @var cycle_manager Cycle manager. */
    private readonly cycle_manager $cycles;

    /** @var cycle_repository Cycle repository. */
    private readonly cycle_repository $repository;

    /** @var notification_manager Notification manager. */
    private readonly notification_manager $notifications;

    /**
     * Creates a new manager instance.
     *
     * @param date_calculator $calculator Calculator.
     * @param cycle_manager $cycles Cycles.
     * @param cycle_repository $repository Repository.
     * @param notification_manager $notifications Notifications.
     */
    public function __construct(
        date_calculator $calculator = new date_calculator(),
        cycle_manager $cycles = new cycle_manager(),
        cycle_repository $repository = new cycle_repository(),
        notification_manager $notifications = new notification_manager(),
    ) {
        $this->calculator = $calculator;
        $this->cycles = $cycles;
        $this->repository = $repository;
        $this->notifications = $notifications;
    }

    /**
     * Scans configured courses for users who may require kopere_recert.
     *
     * @param int $now Reference timestamp; zero uses the current time.
     */
    public function scan(int $now = 0): void {
        global $DB;
        $now = $now ?: time();

        $configs = $DB->get_recordset('local_kopere_recert_course', ['enabled' => 1], 'courseid ASC');
        foreach ($configs as $config) {
            $this->scan_course($config, $now);
        }
        $configs->close();
    }

    /**
     * Scans one course and queues eligible kopere_recert work.
     *
     * @param stdClass $config Configuration record.
     * @param int $now Reference timestamp; zero uses the current time.
     */
    private function scan_course(stdClass $config, int $now): void {
        global $DB;

        [$enrolledsql, $enrolledparams] = get_enrolled_sql(
            context_course::instance($config->courseid),
            '',
            0,
            true
        );
        $sql = "SELECT u.id
                  FROM {user} u
                  JOIN ({$enrolledsql}) eu ON eu.id = u.id
                 WHERE u.deleted = 0";
        $recordset = $DB->get_recordset_sql($sql, $enrolledparams);
        foreach ($recordset as $user) {
            try {
                $this->scan_user($config, (int)$user->id, $now);
            } catch (Throwable $e) {
                mtrace("local_kopere_recert: course {$config->courseid}, user {$user->id}: {$e->getMessage()}");
            }
        }
        $recordset->close();
    }

    /**
     * Evaluates one user for scheduled kopere_recert.
     *
     * @param stdClass $config Configuration record.
     * @param int $userid User ID.
     * @param int $now Reference timestamp; zero uses the current time.
     */
    private function scan_user(stdClass $config, int $userid, int $now): void {
        $lockfactory = lock_config::get_lock_factory('local_kopere_recert');
        $lock = $lockfactory->get_lock("local_kopere_recert:{$config->courseid}:{$userid}", 0);
        if (!$lock) {
            return;
        }
        try {
            $this->scan_user_locked($config, $userid, $now);
        } finally {
            $lock->release();
        }
    }

    /**
     * Evaluates one user while holding the course/user concurrency lock.
     *
     * @param stdClass $config Configuration record.
     * @param int $userid User ID.
     * @param int $now Reference timestamp; zero uses the current time.
     */
    private function scan_user_locked(stdClass $config, int $userid, int $now): void {
        global $DB;

        $active = $this->repository->get_active((int)$config->courseid, $userid);
        if ($active) {
            return;
        }

        $scheduled = $DB->get_record_select(
            'local_kopere_recert_cycle',
            "courseid = :courseid AND userid = :userid AND status = 'scheduled'",
            ['courseid' => $config->courseid, 'userid' => $userid],
            '*',
            IGNORE_MULTIPLE
        );

        if (!$scheduled) {
            $dates = $this->calculator->calculate($config, $userid, $now);
            if (!$dates) {
                return;
            }

            $scheduled = $this->cycles->create(
                (int)$config->courseid,
                $userid,
                get_string('automaticcyclename', 'local_kopere_recert', userdate($dates['dueat'], '%Y')),
                get_string('automaticcyclereason', 'local_kopere_recert'),
                cycle_manager::SOURCE_AUTOMATIC,
                null,
                null,
                $dates['availableat'],
                $dates['dueat'],
                cycle_manager::STATUS_SCHEDULED
            );
            kopere_recert_created::create_from_cycle((int)$scheduled->id)->trigger();
            try {
                $this->notifications->send_configured_event((int)$scheduled->id, 'kopere_recert_created', false);
            } catch (Throwable $e) {
                (new \local_kopere_recert\log\manager())->add(
                    (int)$scheduled->id,
                    null,
                    'notification',
                    null,
                    null,
                    'failed',
                    $e->getMessage()
                );
            }
        }

        if (!empty($scheduled->dueat)) {
            $this->notifications->send_due_notices($scheduled, $now);
        }

        if (!empty($scheduled->dueat) && (int)$scheduled->dueat <= $now) {
            $this->cycles->mark_pending((int)$scheduled->id);
            $task = new execute_kopere_recert();
            $task->set_custom_data([
                'userid' => $userid,
                'courseid' => (int)$config->courseid,
                'cycleid' => (int)$scheduled->id,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }
}
