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
 * simulation.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/kopere_recert:simulate', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recert/simulation.php', ['courseid' => $courseid, 'userid' => $userid]));
$PAGE->set_title(get_string('simulation', 'local_kopere_recert'));
$PAGE->set_heading(format_string($course->fullname));

$report = $SESSION->local_kopere_recert_simulation ?? null;
unset($SESSION->local_kopere_recert_simulation);
if (!$report) {
    throw new moodle_exception('nosimulationreport', 'local_kopere_recert');
}

$planrows = [];
foreach ($report['plan']->get_all_items() as $item) {
    $detailrows = [];
    foreach (($report['inspection'][$item->sortorder]['details'] ?? []) as $key => $value) {
        $detailrows[] = ['key' => (string)$key, 'value' => is_scalar($value) ? (string)$value : json_encode($value)];
    }
    $planrows[] = [
        'component' => $item->component,
        'name' => $item->activityname,
        'cmid' => $item->cmid ?: '',
        'history' => $item->historyenabled,
        'files' => $item->filesenabled,
        'cleanup' => $item->cleanupenabled,
        'details' => $detailrows,
        'hasdetails' => !empty($detailrows),
    ];
}

$historypreview = [];
foreach ($report['historypreview'] ?? [] as $row) {
    $historypreview[] = [
        'component' => $row['component'],
        'name' => $row['name'],
        'completedat' => !empty($row['completedat']) ? userdate((int)$row['completedat']) : '',
        'hasgrade' => $row['grade'] !== null,
        'grade' => $row['grade'] !== null ? format_float((float)$row['grade'], 2) : '',
        'datajson' => !empty($row['datajson']) ? $row['datajson'] : '',
    ];
}

$formatcleanup = static function(array $rows): array {
    foreach ($rows as &$row) {
        $row['messagerows'] = [];
        foreach ($row['messages'] ?? [] as $message) {
            $row['messagerows'][] = ['message' => $message];
        }
    }
    unset($row);
    return $rows;
};

$user = core_user::get_user($userid, '*', MUST_EXIST);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_kopere_recert/simulation', [
    'course' => format_string($course->fullname),
    'user' => fullname($user),
    'cyclename' => $report['cycle']->name ?? '',
    'plan' => $planrows,
    'historycount' => count($report['historyids']),
    'historypreview' => $historypreview,
    'files' => $report['files'],
    'activitycleanup' => $formatcleanup($report['activitycleanup']),
    'systemcleanup' => $formatcleanup($report['systemcleanup']),
    'rolledback' => !empty($report['rolledback']),
]);
echo $OUTPUT->footer();
