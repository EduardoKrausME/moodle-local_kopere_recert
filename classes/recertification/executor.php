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
 * Recertification executor.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\recertification;

use core\lock\lock_config;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\cycle\repository;
use local_kopere_recert\event\kopere_recert_failed;
use local_kopere_recert\event\kopere_recert_started;
use local_kopere_recert\notification\manager as notification_manager;
use local_kopere_recert\task\executor as task_executor;
use local_kopere_recert\task\manager as task_manager;
use moodle_exception;
use Throwable;

/**
 * Coordinates the transactional execution of one recertification cycle.
 */
class executor {
    /** @var cycle_manager Cycle lifecycle manager. */
    private readonly cycle_manager $cycles;

    /** @var task_manager Task plan manager. */
    private readonly task_manager $tasks;

    /** @var task_executor Task execution service. */
    private readonly task_executor $executor;

    /** @var notification_manager Notification service. */
    private readonly notification_manager $notifications;

    /**
     * Creates a new executor instance.
     *
     * @param cycle_manager|null $cycles Cycle lifecycle manager.
     * @param task_manager|null $tasks Task plan manager.
     * @param task_executor|null $executor Task execution service.
     * @param notification_manager|null $notifications Notification service.
     */
    public function __construct(
        ?cycle_manager $cycles = null,
        ?task_manager $tasks = null,
        ?task_executor $executor = null,
        ?notification_manager $notifications = null
    ) {
        $this->cycles = $cycles ?? new cycle_manager();
        $this->tasks = $tasks ?? new task_manager();
        $this->executor = $executor ?? new task_executor();
        $this->notifications = $notifications ?? new notification_manager();
    }

    /**
     * Executes this recertification operation.
     *
     * @param int $cycleid Recertification cycle ID.
     * @return array Execution result.
     */
    public function execute(int $cycleid): array {
        global $DB;

        $cycle = (new repository())->get($cycleid);
        $lockfactory = lock_config::get_lock_factory('local_kopere_recert');
        $lockkey = "local_kopere_recert:{$cycle->courseid}:{$cycle->userid}";
        $lock = $lockfactory->get_lock($lockkey, 0);
        if (!$lock) {
            throw new moodle_exception('kopere_recertlocked', 'local_kopere_recert');
        }

        try {
            // Re-read after acquiring the lock so stale Ad-hoc Tasks cannot reset an already processed cycle.
            $cycle = (new repository())->get($cycleid);
            if (!in_array($cycle->status, [cycle_manager::STATUS_PENDING, cycle_manager::STATUS_FAILED], true)) {
                (new \local_kopere_recert\log\manager())->add(
                    $cycleid,
                    null,
                    'completion',
                    null,
                    null,
                    'warning',
                    get_string('execution_skipped_cycle_status', 'local_kopere_recert', $cycle->status)
                );
                return ['skipped' => true, 'status' => $cycle->status];
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $this->cycles->mark_processing($cycleid);
                $plan = $this->tasks->build_plan((int) $cycle->courseid);

                $historyids = $this->executor->create_all_histories(
                    $plan,
                    (int) $cycle->userid,
                    (int) $cycle->courseid,
                    $cycleid,
                    false
                );
                $files = $this->executor->copy_all_files(
                    $plan,
                    (int) $cycle->userid,
                    (int) $cycle->courseid,
                    $cycleid,
                    $historyids,
                    false
                );
                $activitycleanup = $this->executor->cleanup_all_activities(
                    $plan,
                    (int) $cycle->userid,
                    (int) $cycle->courseid,
                    $cycleid,
                    false
                );
                $systemcleanup = $this->executor->cleanup_all_system(
                    $plan,
                    (int) $cycle->userid,
                    (int) $cycle->courseid,
                    $cycleid,
                    false
                );

                $this->cycles->mark_active($cycleid);
                $transaction->allow_commit();

                kopere_recert_started::create_from_cycle($cycleid)->trigger();
                try {
                    $this->notifications->send_configured_event($cycleid, 'kopere_recert_started');
                } catch (Throwable $e) {
                    (new \local_kopere_recert\log\manager())->add(
                        $cycleid,
                        null,
                        'notification',
                        null,
                        null,
                        'failed',
                        $e->getMessage()
                    );
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
            // This runs after rollback, therefore the failure marker and execution log survive.
            $this->cycles->mark_failed($cycleid, $e);
            (new \local_kopere_recert\log\manager())->add(
                $cycleid,
                null,
                'completion',
                null,
                null,
                'failed',
                $e->getMessage()
            );
            kopere_recert_failed::create_from_cycle($cycleid, $e)->trigger();
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
