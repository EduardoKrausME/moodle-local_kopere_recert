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
 * executor.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\kopere_recertification;

use core\lock\lock_config;
use local_kopere_recertification\cycle\manager as cycle_manager;
use local_kopere_recertification\cycle\repository;
use local_kopere_recertification\event\kopere_recertification_failed;
use local_kopere_recertification\event\kopere_recertification_started;
use local_kopere_recertification\notification\manager as notification_manager;
use local_kopere_recertification\task\executor as task_executor;
use local_kopere_recertification\task\manager as task_manager;
use moodle_exception;
use Throwable;

/**
 * Coordinates the transactional execution of one kopere_recertification cycle.
 */
class executor {
    /**
     * Creates a new executor instance.
     *
     * @param cycle_manager $cycles Cycles.
     * @param task_manager $tasks Tasks.
     * @param task_executor $executor Executor.
     * @param notification_manager $notifications Notifications.
     */
    public function __construct(
        private readonly cycle_manager $cycles = new cycle_manager(),
        private readonly task_manager $tasks = new task_manager(),
        private readonly task_executor $executor = new task_executor(),
        private readonly notification_manager $notifications = new notification_manager(),
    ) {
    }

    /**
     * Executes this kopere_recertification operation.
     *
     * @param int $cycleid Recertification cycle ID.
     * @return array Structured result data.
     */
    public function execute(int $cycleid): array {
        global $DB;

        $cycle = (new repository())->get($cycleid);
        $lockfactory = lock_config::get_lock_factory('local_kopere_recertification');
        $lockkey = "local_kopere_recertification:{$cycle->courseid}:{$cycle->userid}";
        $lock = $lockfactory->get_lock($lockkey, 0);
        if (!$lock) {
            throw new moodle_exception('kopere_recertificationlocked', 'local_kopere_recertification');
        }

        try {
            // Re-read after acquiring the lock. A stale/duplicate Ad-hoc Task must never reset a cycle
            // which has already become active, completed or cancelled. Failed cycles may be retried
            // because their destructive transaction was rolled back before the failure state was stored.
            $cycle = (new repository())->get($cycleid);
            if (!in_array($cycle->status, [cycle_manager::STATUS_PENDING, cycle_manager::STATUS_FAILED], true)) {
                (new \local_kopere_recertification\log\manager())->add(
                    $cycleid,
                    null,
                    'completion',
                    null,
                    null,
                    'warning',
                    get_string('execution_skipped_cycle_status', 'local_kopere_recertification', $cycle->status)
                );
                return ['skipped' => true, 'status' => $cycle->status];
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $this->cycles->mark_processing($cycleid);
                $plan = $this->tasks->build_plan((int)$cycle->courseid);

                $historyids = $this->executor->create_all_histories(
                    $plan,
                    (int)$cycle->userid,
                    (int)$cycle->courseid,
                    $cycleid,
                    false
                );

                $files = $this->executor->copy_all_files(
                    $plan,
                    (int)$cycle->userid,
                    (int)$cycle->courseid,
                    $cycleid,
                    $historyids,
                    false
                );

                // Validation boundary: reaching this point means all history and file operations succeeded.
                $activitycleanup = $this->executor->cleanup_all_activities(
                    $plan,
                    (int)$cycle->userid,
                    (int)$cycle->courseid,
                    $cycleid,
                    false
                );

                $systemcleanup = $this->executor->cleanup_all_system(
                    $plan,
                    (int)$cycle->userid,
                    (int)$cycle->courseid,
                    $cycleid,
                    false
                );

                $this->cycles->mark_active($cycleid);
                $transaction->allow_commit();

                kopere_recertification_started::create_from_cycle($cycleid)->trigger();
                try {
                    $this->notifications->send_configured_event($cycleid, 'kopere_recertification_started');
                } catch (Throwable $e) {
                    (new \local_kopere_recertification\log\manager())->add($cycleid, null, 'notification', null, null, 'failed', $e->getMessage());
                }

                return [
                    'historyids' => $historyids,
                    'files' => $files,
                    'activitycleanup' => $activitycleanup,
                    'systemcleanup' => $systemcleanup,
                ];
            } catch (Throwable $e) {
                $transaction->rollback($e);
            }
        } catch (Throwable $e) {
            // This runs after the rollback, therefore the failure marker and log survive.
            $this->cycles->mark_failed($cycleid, $e);
            (new \local_kopere_recertification\log\manager())->add(
                $cycleid,
                null,
                'completion',
                null,
                null,
                'failed',
                $e->getMessage()
            );
            kopere_recertification_failed::create_from_cycle($cycleid, $e)->trigger();
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
