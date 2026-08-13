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
 * lib.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_recert\status\manager;

/**
 * Handles the local kopere_recert pluginfile operation.
 *
 * @param stdClass $course Course record.
 * @param ?stdClass $cm Cm.
 * @param context $context Execution context.
 * @param string $filearea Filearea.
 * @param array $args Args.
 * @param bool $forcedownload Forcedownload.
 * @param array $options Options.
 * @return bool Boolean result.
 */
function local_kopere_recert_pluginfile(
    stdClass $course,
    ?stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    global $DB, $USER;

    if ($filearea !== 'historyfiles' || $context->contextlevel !== CONTEXT_COURSE) {
        return false;
    }

    require_login($course);

    if (count($args) < 2) {
        return false;
    }

    $historyid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');

    $history = $DB->get_record('local_kopere_recert_history', ['id' => $historyid], '*', IGNORE_MISSING);
    if (!$history) {
        return false;
    }

    $coursecontext = context_course::instance($history->courseid);
    if ($coursecontext->id !== $context->id) {
        return false;
    }
    $allowed = (int)$history->userid === (int)$USER->id
        ? has_capability('local/kopere_recert:viewownhistory', $coursecontext)
        : has_capability('local/kopere_recert:viewallhistory', $coursecontext);

    if (!$allowed) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_kopere_recert',
        'historyfiles',
        $historyid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Handles the local kopere_recert extend navigation course operation.
 *
 * @param navigation_node $navigation Navigation.
 * @param stdClass $course Course record.
 * @param context_course $context Execution context.
 */
function local_kopere_recert_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    global $USER;

    if (has_capability('local/kopere_recert:viewownhistory', $context)) {
        $navigation->add(
            get_string('history', 'local_kopere_recert'),
            new moodle_url('/local/kopere_recert/history.php', ['courseid' => $course->id, 'userid' => $USER->id]),
            navigation_node::TYPE_CUSTOM
        );
    }

    if (has_capability('local/kopere_recert:recertifyself', $context)) {
        $availability = (new \local_kopere_recert\recertification\manager())->get_self_availability((int)$course->id, (int)$USER->id);
        if (!empty($availability['allowed']) && !(new manager())->is_kopere_recert_required((int)$course->id, (int)$USER->id)) {
            $navigation->add(
                get_string('startkopere_recert', 'local_kopere_recert'),
                new moodle_url('/local/kopere_recert/recertify.php', ['courseid' => $course->id, 'userid' => $USER->id]),
                navigation_node::TYPE_CUSTOM
            );
        }
    }

    if (has_capability('local/kopere_recert:manage', $context)) {
        $navigation->add(
            get_string('courseconfiguration', 'local_kopere_recert'),
            new moodle_url('/local/kopere_recert/course.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM
        );
    }
}
