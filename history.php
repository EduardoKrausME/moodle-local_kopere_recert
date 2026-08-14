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
 * history.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_recert\form\history_filter_form;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$cycleid = optional_param('cycleid', 0, PARAM_INT);

require_login();

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

if ($userid === (int)$USER->id) {
    if ($courseid && !has_capability('local/kopere_recert:viewownhistory', $context)
            && !has_capability('local/kopere_recert:viewallhistory', $context)) {
        require_capability('local/kopere_recert:viewownhistory', $context);
    }
} else {
    if (!$courseid) {
        throw new moodle_exception('courseidrequired', 'local_kopere_recert');
    }
    require_capability('local/kopere_recert:viewallhistory', $context);
}

$canselectuser = $courseid > 0 && has_capability('local/kopere_recert:viewallhistory', $context);
$filterform = new history_filter_form(null, [
    'courseid' => $courseid,
    'userid' => $userid,
    'canselectuser' => $canselectuser,
]);
if ($filterdata = $filterform->get_data()) {
    $targetcourseid = (int)($filterdata->courseid ?? 0);
    $targetuserid = $canselectuser ? (int)($filterdata->userid ?? $userid) : $userid;
    if (!$targetcourseid && $targetuserid !== (int)$USER->id) {
        $targetuserid = (int)$USER->id;
    }
    redirect(new moodle_url('/local/kopere_recert/history.php', [
        'courseid' => $targetcourseid ?: null,
        'userid' => $targetuserid,
    ]));
}

$params = ['userid' => $userid];
$where = 'userid = :userid';
if ($courseid) {
    $where .= ' AND courseid = :courseid';
    $params['courseid'] = $courseid;
}
$cycles = $DB->get_records_select('local_kopere_recert_cycle', $where, $params, 'courseid ASC, number DESC');

// When no course filter is supplied, enforce the history capability independently in every course.
// A capability denied in one course must not be bypassed by the global history URL.
if (!$courseid) {
    foreach ($cycles as $id => $cycle) {
        $cyclecontext = context_course::instance((int)$cycle->courseid);
        if (!has_capability('local/kopere_recert:viewownhistory', $cyclecontext)
                && !has_capability('local/kopere_recert:viewallhistory', $cyclecontext)) {
            unset($cycles[$id]);
        }
    }
}

if ($cycleid && !isset($cycles[$cycleid])) {
    throw new moodle_exception('invalidcycle', 'local_kopere_recert');
}

if (!$cycleid && $cycles) {
    $first = reset($cycles);
    $cycleid = (int)$first->id;
}
$selected = null;
$history = [];
if ($cycleid) {
    $selected = $cycles[$cycleid];
    $history = $DB->get_records('local_kopere_recert_history', ['cycleid' => $cycleid], 'sortorder ASC, id ASC');
}

$PAGE->set_context($courseid ? $context : context_system::instance());
$PAGE->set_url(new moodle_url('/local/kopere_recert/history.php', [
    'courseid' => $courseid ?: null,
    'userid' => $userid,
    'cycleid' => $cycleid ?: null,
]));
$PAGE->set_title(get_string('history', 'local_kopere_recert'));
$PAGE->set_heading(get_string('history', 'local_kopere_recert'));

$cycleoptions = [];
foreach ($cycles as $cycle) {
    $course = get_course($cycle->courseid);
    $cycleoptions[] = [
        'url' => new moodle_url('/local/kopere_recert/history.php', [
            'courseid' => $courseid ?: $cycle->courseid,
            'userid' => $userid,
            'cycleid' => $cycle->id,
        ]),
        'label' => format_string($course->shortname) . ' - ' . $cycle->name . ' (#' . $cycle->number . ')',
        'selected' => (int)$cycle->id === $cycleid,
    ];
}
$items = [];
foreach ($history as $row) {
    $files = [];
    $filemetadata = $DB->get_records('local_kopere_recert_file', ['historyid' => $row->id], 'filepath ASC, filename ASC');
    $historycontext = context_course::instance((int)$row->courseid);
    foreach ($filemetadata as $filemeta) {
        $files[] = [
            'name' => $filemeta->filename,
            'url' => moodle_url::make_pluginfile_url(
                $historycontext->id,
                'local_kopere_recert',
                'historyfiles',
                (int)$row->id,
                $filemeta->filepath,
                $filemeta->filename,
                true
            ),
        ];
    }

    $items[] = [
        'name' => $row->activityname,
        'type' => $row->activitytype,
        'completedat' => $row->completedat ? userdate($row->completedat) : '',
        'hasgrade' => $row->grade !== null,
        'grade' => $row->grade !== null ? format_float($row->grade, 2) : '',
        'html' => $row->html ? format_text($row->html, FORMAT_HTML, ['trusted' => true]) : '',
        'hashtml' => trim((string)$row->html) !== '',
        'component' => $row->component,
        'files' => $files,
        'hasfiles' => !empty($files),
    ];
}

echo $OUTPUT->header();
if ($courseid) {
    echo $OUTPUT->render_from_template('local_kopere_recert/course_header', [
        'courseurl' => new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]),
        'noticesurl' => new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]),
        'bulkurl' => new moodle_url('/local/kopere_recert/bulk.php', ['courseid' => $courseid]),
        'historyurl' => new moodle_url('/local/kopere_recert/history.php', ['courseid' => $courseid]),
        'tasksurl' => new moodle_url('/local/kopere_recert/tasks.php', ['courseid' => $courseid]),
        'noticescount' => $DB->count_records('local_kopere_recert_notice', ['courseid' => $courseid]),
        'historyactive' => true,
    ]);
}
$filterform->display();
echo $OUTPUT->render_from_template('local_kopere_recert/history', [
    'cycles' => $cycleoptions,
    'hascycle' => (bool)$selected,
    'cyclename' => $selected->name ?? '',
    'status' => $selected->status ?? '',
    'startedat' => !empty($selected->startedat) ? userdate($selected->startedat) : '',
    'completedat' => !empty($selected->completedat) ? userdate($selected->completedat) : '',
    'items' => $items,
]);
echo $OUTPUT->footer();
