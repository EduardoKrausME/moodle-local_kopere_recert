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
 * bulk.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\recertification\manager as recertification_manager;

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/kopere_recert:bulkrecertify', $context);

if (data_submitted() && confirm_sesskey()) {
    $userids = optional_param_array('userids', [], PARAM_INT);
    $reason = trim(required_param('reason', PARAM_TEXT));
    if ($reason === '') {
        throw new invalid_parameter_exception(get_string('reasonrequired', 'local_kopere_recert'));
    }

    $success = 0;
    $failed = 0;
    foreach ($userids as $userid) {
        if (!is_enrolled($context, $userid, '', true)) {
            $failed++;
            continue;
        }
        try {
            (new recertification_manager())->create_and_queue(
                $courseid,
                (int) $userid,
                $reason,
                $reason,
                cycle_manager::SOURCE_BULK,
                (int) $USER->id
            );
            $success++;
        } catch (Throwable $e) {
            // Keep every user isolated so one failure does not affect another queued recertification.
            $failed++;
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    redirect(
        new moodle_url('/local/kopere_recert/bulk.php', ['courseid' => $courseid]),
        get_string('bulkqueuedsummary', 'local_kopere_recert', (object) [
            'success' => $success,
            'failed' => $failed,
        ]),
        null,
        $failed ? notification::NOTIFY_WARNING : notification::NOTIFY_SUCCESS
    );
}

[$enrolledsql, $params] = get_enrolled_sql($context, '', 0, true);
$where = '';
if ($search !== '') {
    $where = ' AND (' . $DB->sql_like('u.firstname', ':search1', false)
        . ' OR ' . $DB->sql_like('u.lastname', ':search2', false)
        . ' OR ' . $DB->sql_like('u.email', ':search3', false) . ')';
    $like = '%' . $DB->sql_like_escape($search) . '%';
    $params['search1'] = $like;
    $params['search2'] = $like;
    $params['search3'] = $like;
}
$sql = "SELECT u.id, u.firstname, u.lastname, u.email
          FROM {user} u
          JOIN ({$enrolledsql}) eu ON eu.id = u.id
         WHERE u.deleted = 0 {$where}
      ORDER BY u.lastname, u.firstname";
$perpage = 100;
$users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$countsql = "SELECT COUNT(1)
               FROM {user} u
               JOIN ({$enrolledsql}) eu ON eu.id = u.id
              WHERE u.deleted = 0 {$where}";
$totalusers = (int) $DB->get_field_sql($countsql, $params);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recert/bulk.php', [
    'courseid' => $courseid,
    'page' => $page,
    'search' => $search,
]));
$PAGE->set_title(get_string('bulkkopere_recert', 'local_kopere_recert'));
$PAGE->set_heading(format_string($course->fullname));

$rows = [];
foreach ($users as $user) {
    $rows[] = [
        'id' => $user->id,
        'fullname' => fullname($user),
        'email' => $user->email,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_kopere_recert/bulk', [
    'users' => $rows,
    'courseid' => $courseid,
    'sesskey' => sesskey(),
    'search' => s($search),
]);
echo $OUTPUT->paging_bar(
    $totalusers,
    $page,
    $perpage,
    new moodle_url('/local/kopere_recert/bulk.php', [
        'courseid' => $courseid,
        'search' => $search,
    ])
);
echo $OUTPUT->footer();
