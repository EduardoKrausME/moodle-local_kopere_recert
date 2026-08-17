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
 * Task executor.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

use coding_exception;
use invalid_parameter_exception;
use local_kopere_recert\cleanup\manager as cleanup_manager;
use local_kopere_recert\files\manager as files_manager;
use local_kopere_recert\history\manager as history_manager;
use local_kopere_recert\history\renderer_service;
use local_kopere_recert\log\manager as log_manager;
use moodle_exception;

/**
 * Executes history, file-copy, and cleanup phases for an ordered plan.
 */
class executor {
    /** @var history_manager History manager. */
    private readonly history_manager $history;

    /** @var renderer_service History renderer. */
    private readonly renderer_service $renderer;

    /** @var files_manager File manager. */
    private readonly files_manager $files;

    /** @var cleanup_manager Cleanup manager. */
    private readonly cleanup_manager $cleanup;

    /** @var log_manager Log manager. */
    private readonly log_manager $log;

    /**
     * Creates a new executor instance.
     *
     * @param history_manager $history History.
     * @param renderer_service $renderer Renderer.
     * @param files_manager $files Files.
     * @param cleanup_manager $cleanup Cleanup.
     * @param log_manager $log Log.
     */
    public function __construct(
        history_manager $history = new history_manager(),
        renderer_service $renderer = new renderer_service(),
        files_manager $files = new files_manager(),
        cleanup_manager $cleanup = new cleanup_manager(),
        log_manager $log = new log_manager(),
    ) {
        $this->history = $history;
        $this->renderer = $renderer;
        $this->files = $files;
        $this->cleanup = $cleanup;
        $this->log = $log;
    }

    /**
     * Build a non-destructive description of the same plan which will be executed.
     * This is supplemental to simulation; simulation still runs the real methods in a rollback transaction.
     *
     * @param execution_plan $plan Execution plan.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array Structured plan description.
     */
    public function describe_plan(
        execution_plan $plan,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): array {
        $report = [];
        foreach ($plan->get_all_items() as $item) {
            $context = $this->context_for_item($item, $userid, $courseid, $cycleid, $simulation);
            $details = [];

            if ($item->plugin) {
                $details = $item->plugin->describe($context);
            } else if ($item->cleanupenabled) {
                $config = json_decode((string)($item->genericconfig->cleanupconfigjson ?? ''), true);
                if (is_array($config)) {
                    $configs = isset($config[0]) ? $config : [$config];
                    foreach ($configs as $cleanupconfig) {
                        $table = (string)($cleanupconfig['table'] ?? '');
                        if ($table !== '') {
                            $details[$table] = $this->cleanup->count($item->component, $cleanupconfig, $context);
                        }
                    }
                }
            }

            $report[$item->sortorder] = [
                'component' => $item->component,
                'cmid' => $item->cmid,
                'name' => $item->activityname,
                'details' => $details,
            ];
        }
        return $report;
    }

    /**
     * Creates all enabled historical snapshots for an execution plan.
     *
     * @param execution_plan $plan Execution plan.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array<int,int> Map plan sortorder => history id.
     */
    public function create_all_histories(
        execution_plan $plan,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): array {
        $historyids = [];
        foreach ($plan->get_all_items() as $item) {
            if (!$item->historyenabled) {
                continue;
            }

            $started = microtime(true);
            $context = $this->context_for_item($item, $userid, $courseid, $cycleid, $simulation);
            $basic = $this->history->create_basic_result($item, $context);

            if ($item->plugin) {
                $specific = $item->plugin->create_history($context);
                $result = new history_result(
                    $specific->completedat ?? $basic->completedat,
                    $specific->grade ?? $basic->grade,
                    $specific->html,
                    $specific->data,
                    $specific->files,
                    $specific->messages
                );
            } else {
                $result = $basic;
                $template = (string)($item->genericconfig->historytemplate ?? '');
                if (trim($template) !== '') {
                    $result->html = $this->renderer->render($template, $context, [
                        'activityname' => $item->activityname,
                        'activitytype' => $item->activitytype,
                        'completedat' => $result->completedat,
                        'grade' => $result->grade,
                    ]);
                }
            }

            $historyid = $this->history->persist($item, $context, $result);
            $historyids[$item->sortorder] = $historyid;
            $this->log->add(
                $cycleid,
                $item->taskid,
                'history',
                $item->component,
                $item->cmid,
                $simulation ? 'simulated' : 'success',
                implode("\n", $result->messages),
                microtime(true) - $started
            );
        }
        return $historyids;
    }

    /**
     * Copies all files required by the execution plan.
     *
     * @param execution_plan $plan Execution plan.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param array $historyids History IDs keyed by plan item.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array Structured result data.
     */
    public function copy_all_files(
        execution_plan $plan,
        int $userid,
        int $courseid,
        int $cycleid,
        array $historyids,
        bool $simulation
    ): array {
        $report = [];
        foreach ($plan->get_all_items() as $item) {
            if (!$item->filesenabled) {
                continue;
            }
            if (!isset($historyids[$item->sortorder])) {
                throw new coding_exception("File phase requires a history row for {$item->component}");
            }

            $started = microtime(true);
            $context = $this->context_for_item($item, $userid, $courseid, $cycleid, $simulation);
            $descriptors = [];
            if ($item->plugin) {
                $descriptors = $item->plugin->get_files($context, $historyids[$item->sortorder]);
            } else {
                $descriptors = $this->generic_file_descriptors($item, $context);
            }

            $count = $this->files->copy_descriptors($descriptors, $context, $historyids[$item->sortorder]);
            $report[] = ['component' => $item->component, 'cmid' => $item->cmid, 'count' => $count];
            $this->log->add(
                $cycleid,
                $item->taskid,
                'files',
                $item->component,
                $item->cmid,
                $simulation ? 'simulated' : 'success',
                get_string('filescopiedcount', 'local_kopere_recert', $count),
                microtime(true) - $started
            );
        }
        return $report;
    }

    /**
     * Runs cleanup for activity-specific plan items in course order.
     *
     * @param execution_plan $plan Execution plan.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array Structured result data.
     */
    public function cleanup_all_activities(
        execution_plan $plan,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): array {
        $report = [];
        foreach ($plan->get_activity_items() as $item) {
            if (!$item->cleanupenabled) {
                continue;
            }
            $report[] = $this->cleanup_item($item, $userid, $courseid, $cycleid, $simulation);
        }
        return $report;
    }

    /**
     * Runs cleanup for system-level plan items after activity cleanup.
     *
     * @param execution_plan $plan Execution plan.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array Structured result data.
     */
    public function cleanup_all_system(
        execution_plan $plan,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): array {
        $report = [];
        foreach ($plan->get_system_items() as $item) {
            if (!$item->cleanupenabled) {
                continue;
            }
            $report[] = $this->cleanup_item($item, $userid, $courseid, $cycleid, $simulation);
        }
        return $report;
    }

    /**
     * Runs cleanup for one execution plan item.
     *
     * @param plan_item $item Execution plan item.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return array Structured result data.
     */
    private function cleanup_item(
        plan_item $item,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): array {
        $started = microtime(true);
        $context = $this->context_for_item($item, $userid, $courseid, $cycleid, $simulation);

        if ($item->plugin) {
            $result = $item->plugin->cleanup($context);
        } else {
            $config = json_decode((string)($item->genericconfig->cleanupconfigjson ?? ''), true);
            if (!is_array($config)) {
                throw new moodle_exception('invalidcleanupconfig', 'local_kopere_recert');
            }
            $configs = isset($config[0]) ? $config : [$config];
            $affected = 0;
            $messages = [];
            foreach ($configs as $cleanupconfig) {
                $count = $this->cleanup->cleanup($item->component, $cleanupconfig, $context);
                $affected += $count;
                $messages[] = (string)($cleanupconfig['table'] ?? '') . ': ' . $count;
            }
            $result = new cleanup_result($affected, $messages);
        }

        $this->log->add(
            $cycleid,
            $item->taskid,
            'cleanup',
            $item->component,
            $item->cmid,
            $simulation ? 'simulated' : 'success',
            get_string('recordsaffectedcount', 'local_kopere_recert', $result->affected),
            microtime(true) - $started
        );

        return [
            'component' => $item->component,
            'cmid' => $item->cmid,
            'affected' => $result->affected,
            'messages' => $result->messages,
        ];
    }

    /**
     * Creates the task context used to execute a plan item.
     *
     * @param plan_item $item Execution plan item.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $cycleid Recertification cycle ID.
     * @param bool $simulation Whether the operation is running in simulation mode.
     * @return task_context Result of the operation.
     */
    private function context_for_item(
        plan_item $item,
        int $userid,
        int $courseid,
        int $cycleid,
        bool $simulation
    ): task_context {
        return new task_context(
            $userid,
            $courseid,
            $item->cmid,
            $item->instanceid,
            $item->contextid,
            $cycleid,
            $cycleid,
            $simulation,
        );
    }

    /**
     * Builds file descriptors for a generic task configuration.
     *
     * @param plan_item $item Execution plan item.
     * @param task_context $context Execution context.
     * @return array Structured result data.
     */
    private function generic_file_descriptors(plan_item $item, task_context $context): array {
        $config = json_decode((string)($item->genericconfig->fileconfigjson ?? ''), true);
        if (!$config) {
            return [];
        }

        $rows = isset($config[0]) ? $config : [$config];
        $result = [];
        foreach ($rows as $row) {
            $itemid = $this->resolve_value($row['itemid'] ?? ':instanceid', $context);
            $sourcecontext = $this->resolve_value($row['contextid'] ?? ':contextid', $context);
            $result[] = new file_descriptor(
                (int)$sourcecontext,
                (string)($row['component'] ?? $item->component),
                (string)($row['filearea'] ?? ''),
                (int)$itemid,
                (string)($row['filepath'] ?? '/'),
                isset($row['filename']) && $row['filename'] !== '' ? (string)$row['filename'] : null,
            );
        }
        return $result;
    }

    /**
     * Resolves a configured literal or execution placeholder.
     *
     * @param mixed $value Value to validate or transform.
     * @param task_context $context Execution context.
     * @return mixed Result of the operation.
     */
    private function resolve_value(mixed $value, task_context $context): mixed {
        if (!is_string($value) || !str_starts_with($value, ':')) {
            return $value;
        }
        $name = substr($value, 1);
        $params = $context->get_sql_params();
        if (!array_key_exists($name, $params)) {
            throw new invalid_parameter_exception("Unsupported file placeholder: {$value}");
        }
        return $params[$name];
    }
}
