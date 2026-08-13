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
 * simulator.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\recertification;

use core\lock\lock_config;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\task\executor as task_executor;
use local_kopere_recert\task\manager as task_manager;
use moodle_exception;

/**
 * Runs the real recertification pipeline in rollback-only simulation mode.
 */
class simulator {
    /** Task plan manager. */
    private readonly task_manager $tasks;

    /** Task execution service. */
    private readonly task_executor $executor;

    /**
     * Creates a new simulator instance.
     *
     * @param task_manager|null $tasks Task plan manager.
     * @param task_executor|null $executor Task execution service.
     */
    public function __construct(?task_manager $tasks = null, ?task_executor $executor = null) {
        $this->tasks = $tasks ?? new task_manager();
        $this->executor = $executor ?? new task_executor();
    }

    /**
     * Executes the real recertification flow inside a transaction that is always rolled back.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param string $name Human-readable name.
     * @param string $reason Human-readable recertification reason.
     * @param string $source Recertification source.
     * @param int|null $createdby User ID that created the cycle.
     * @return array Simulation report.
     */
    public function simulate(
        int $courseid,
        int $userid,
        string $name,
        string $reason,
        string $source,
        ?int $createdby
    ): array {
        global $DB;

        $lockfactory = lock_config::get_lock_factory('local_kopere_recert');
        $lock = $lockfactory->get_lock("local_kopere_recert:{$courseid}:{$userid}", 0);
        if (!$lock) {
            throw new moodle_exception('kopere_recertlocked', 'local_kopere_recert');
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $cycles = new cycle_manager();
                $cycle = $cycles->create($courseid, $userid, $name, $reason, $source, $createdby);
                $cycles->mark_processing((int) $cycle->id);

                $plan = $this->tasks->build_plan($courseid);
                $inspection = $this->executor->describe_plan(
                    $plan,
                    $userid,
                    $courseid,
                    (int) $cycle->id,
                    true
                );
                $historyids = $this->executor->create_all_histories(
                    $plan,
                    $userid,
                    $courseid,
                    (int) $cycle->id,
                    true
                );
                $files = $this->executor->copy_all_files(
                    $plan,
                    $userid,
                    $courseid,
                    (int) $cycle->id,
                    $historyids,
                    true
                );
                $activitycleanup = $this->executor->cleanup_all_activities(
                    $plan,
                    $userid,
                    $courseid,
                    (int) $cycle->id,
                    true
                );
                $systemcleanup = $this->executor->cleanup_all_system(
                    $plan,
                    $userid,
                    $courseid,
                    (int) $cycle->id,
                    true
                );

                $historypreview = [];
                foreach ($historyids as $sortorder => $historyid) {
                    $row = $DB->get_record('local_kopere_recert_history', ['id' => $historyid], '*', MUST_EXIST);
                    $historypreview[$sortorder] = [
                        'component' => $row->component,
                        'name' => $row->activityname,
                        'completedat' => $row->completedat,
                        'grade' => $row->grade,
                        'datajson' => $row->datajson,
                    ];
                }

                $report = [
                    'cycle' => clone $cycle,
                    'plan' => $plan,
                    'inspection' => $inspection,
                    'historyids' => $historyids,
                    'historypreview' => $historypreview,
                    'files' => $files,
                    'activitycleanup' => $activitycleanup,
                    'systemcleanup' => $systemcleanup,
                    'rolledback' => true,
                ];

                $transaction->rollback(new simulation_rollback($report));
            } catch (simulation_rollback $e) {
                return $e->get_report();
            }
        } finally {
            $lock->release();
        }
    }
}
