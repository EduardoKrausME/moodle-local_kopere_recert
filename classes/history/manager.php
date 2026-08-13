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
 * manager.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\history;

use local_kopere_recert\task\plan_item;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\history_result;

/**
 * Creates and persists immutable historical snapshots for kopere_recert cycles.
 */
class manager {
    /**
     * Creates the default historical result for an activity.
     *
     * @param plan_item $item Execution plan item.
     * @param task_context $context Execution context.
     * @return history_result Result of the operation.
     */
    public function create_basic_result(plan_item $item, task_context $context): history_result {
        global $DB, $CFG;

        $completedat = null;
        $grade = null;

        if ($context->cmid) {
            $completion = $DB->get_record('course_modules_completion', [
                'coursemoduleid' => $context->cmid,
                'userid' => $context->userid,
            ], 'id, completionstate, timemodified', IGNORE_MISSING);
            if ($completion && (int)$completion->completionstate !== 0) {
                $completedat = (int)$completion->timemodified;
            }

            if ($context->instanceid && str_starts_with($item->component, 'mod_')) {
                require_once($CFG->libdir . '/gradelib.php');
                $modname = substr($item->component, 4);
                $gradeitem = $DB->get_record('grade_items', [
                    'courseid' => $context->courseid,
                    'itemtype' => 'mod',
                    'itemmodule' => $modname,
                    'iteminstance' => $context->instanceid,
                    'itemnumber' => 0,
                ], 'id', IGNORE_MISSING);
                if ($gradeitem) {
                    $finalgrade = $DB->get_field('grade_grades', 'finalgrade', [
                        'itemid' => $gradeitem->id,
                        'userid' => $context->userid,
                    ]);
                    if ($finalgrade !== false && $finalgrade !== null) {
                        $grade = (float)$finalgrade;
                    }
                }
            }
        }

        return new history_result($completedat, $grade);
    }

    /**
     * Persists a structured history result for the current cycle.
     *
     * @param plan_item $item Execution plan item.
     * @param task_context $context Execution context.
     * @param history_result $result Structured history result.
     * @return int Resulting integer value.
     */
    public function persist(plan_item $item, task_context $context, history_result $result): int {
        global $DB;

        $record = (object)[
            'cycleid' => $context->cycleid,
            'courseid' => $context->courseid,
            'userid' => $context->userid,
            'taskid' => $item->taskid,
            'component' => $item->component,
            'cmid' => $context->cmid,
            'instanceid' => $context->instanceid,
            'activityname' => $item->activityname,
            'activitytype' => $item->activitytype,
            'completedat' => $result->completedat,
            'grade' => $result->grade,
            'html' => $result->html,
            'datajson' => $result->data ? json_encode($result->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'sortorder' => $item->sortorder,
            'timecreated' => time(),
        ];

        return (int)$DB->insert_record('local_kopere_recert_history', $record);
    }
}
