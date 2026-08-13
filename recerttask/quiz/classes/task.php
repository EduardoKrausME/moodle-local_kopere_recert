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
 * task.php
 *
 * @package   recerttask_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_quiz;

use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Specialized kopere_recert task provider for quizzes.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component represented by this task provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string { return 'mod_quiz'; }
    /**
     * Returns the localized name of this provider.
     */
    public static function get_name(): string { return get_string('pluginname', 'recerttask_quiz'); }
    /**
     * Reports whether the provider can create historical snapshots.
     *
     * @return bool True when history creation is supported.
     */
    public static function supports_history(): bool { return true; }
    /**
     * Reports whether the provider can preserve files.
     *
     * @return bool True when file preservation is supported.
     */
    public static function supports_files(): bool { return false; }
    /**
     * Reports whether the provider can clean user data.
     *
     * @return bool True when cleanup is supported.
     */
    public static function supports_cleanup(): bool { return true; }
    /**
     * Reports whether this provider represents a system-level task.
     *
     * @return bool True for a system-level task.
     */
    public static function is_system_task(): bool { return false; }
    /**
     * Returns the ordering value used for system-level execution.
     *
     * @return int System execution order.
     */
    public static function get_system_order(): int { return 0; }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $quiz = $DB->get_record('quiz', ['id' => $context->instanceid], '*', MUST_EXIST);
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $context->userid,
            'preview' => 0,
        ], 'attempt ASC');

        $rows = [];
        $best = null;
        foreach ($attempts as $attempt) {
            $grade = $attempt->sumgrades === null || (float)$quiz->sumgrades == 0.0
                ? null
                : ((float)$attempt->sumgrades / (float)$quiz->sumgrades) * (float)$quiz->grade;
            if ($grade !== null && ($best === null || $grade > $best)) {
                $best = $grade;
            }
            $rows[] = [
                'attempt' => $attempt->attempt,
                'state' => $attempt->state,
                'timestart' => $attempt->timestart ? userdate($attempt->timestart) : '',
                'timefinish' => $attempt->timefinish ? userdate($attempt->timefinish) : '',
                'grade' => $grade === null ? '' : format_float($grade, 2),
                'hasgrade' => $grade !== null,
            ];
        }

        $finalgrade = $DB->get_field('quiz_grades', 'grade', [
            'quiz' => $quiz->id,
            'userid' => $context->userid,
        ]);
        if ($finalgrade !== false && $finalgrade !== null) {
            $best = (float)$finalgrade;
        }

        $html = $OUTPUT->render_from_template('recerttask_quiz/history', [
            'quizname' => format_string($quiz->name),
            'attempts' => $rows,
            'attemptcount' => count($rows),
            'hasfinalgrade' => $best !== null,
            'finalgrade' => $best !== null ? format_float($best, 2) : '',
            'maxgrade' => format_float((float)$quiz->grade, 2),
        ]);

        return new history_result(
            grade: $best,
            html: $html,
            data: [
                'attempts' => count($rows),
                'bestgrade' => $best,
                'maxgrade' => (float)$quiz->grade,
            ],
            messages: [get_string('archivedcount', 'recerttask_quiz', count($rows))]
        );
    }

    /**
     * Returns file descriptors that must be copied into historical storage.
     *
     * @param task_context $context Execution context.
     * @param int $historyid History record ID.
     * @return array File descriptors to preserve.
     */
    public function get_files(task_context $context, int $historyid): array {
        return [];
    }

    /**
     * Cleans the current user data after history and files have been safely preserved.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $context->instanceid], '*', MUST_EXIST);
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $context->userid,
        ], 'id ASC');

        $count = 0;
        foreach ($attempts as $attempt) {
            quiz_delete_attempt($attempt, $quiz);
            $count++;
        }
        return new cleanup_result($count, [get_string('removedcount', 'recerttask_quiz', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        global $DB;
        return [
            'attempts' => $DB->count_records('quiz_attempts', [
                'quiz' => $context->instanceid,
                'userid' => $context->userid,
            ]),
        ];
    }
}
