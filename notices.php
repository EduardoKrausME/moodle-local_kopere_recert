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
 * notices.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_recert\form\notice_form;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/kopere_recert:manage', $context);

if ($delete) {
    require_sesskey();
    $DB->delete_records('local_kopere_recert_notice', ['id' => $delete, 'courseid' => $courseid]);
    redirect(new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]));
}

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid, 'id' => $id ?: null]));
$PAGE->set_title(get_string('notices', 'local_kopere_recert'));
$PAGE->set_heading(format_string($course->fullname));

$record = $id ? $DB->get_record('local_kopere_recert_notice', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST) : null;
$form = new notice_form(null, ['courseid' => $courseid]);
if ($record) {
    $record->body_editor = ['text' => $record->body, 'format' => FORMAT_HTML];
    $form->set_data($record);
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $now = time();
    $save = (object)[
        'courseid' => $courseid,
        'eventtype' => $data->eventtype,
        'offsetdays' => max(0, (int)$data->offsetdays),
        'enabled' => (int)$data->enabled,
        'subject' => $data->subject,
        'body' => $data->body_editor['text'],
        'timemodified' => $now,
    ];
    if (!empty($data->id)) {
        $save->id = (int)$data->id;
        $DB->update_record('local_kopere_recert_notice', $save);
    } else {
        $save->timecreated = $now;
        $DB->insert_record('local_kopere_recert_notice', $save);
    }
    redirect(new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]), get_string('changessaved'));
}

$rows = [];
foreach ($DB->get_records('local_kopere_recert_notice', ['courseid' => $courseid], 'eventtype ASC, offsetdays DESC') as $notice) {
    $rows[] = [
        'eventtype' => get_string('notice_' . match ($notice->eventtype) {
            'kopere_recert_available' => 'available',
            'kopere_recert_created' => 'created',
            'kopere_recert_started' => 'started',
            'expiration_warning' => 'warning',
            'kopere_recert_due' => 'due',
            'kopere_recert_expired' => 'expired',
            'kopere_recert_completed' => 'completed',
            default => 'warning',
        }, 'local_kopere_recert'),
        'offsetdays' => $notice->offsetdays,
        'enabled' => $notice->enabled ? get_string('yes') : get_string('no'),
        'editurl' => new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid, 'id' => $notice->id]),
        'deleteurl' => new moodle_url('/local/kopere_recert/notices.php', [
            'courseid' => $courseid,
            'delete' => $notice->id,
            'sesskey' => sesskey(),
        ]),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_kopere_recert/course_header', [
    'courseurl' => new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]),
    'noticesurl' =>new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]),
    'bulkurl' => new moodle_url('/local/kopere_recert/bulk.php', ['courseid' => $courseid]),
    'historyurl' => new moodle_url('/local/kopere_recert/history.php', ['courseid' => $courseid]),
    'noticescount' => count($rows),
    'noticesactive' => true,
]);
echo $OUTPUT->render_from_template('local_kopere_recert/notices', ['rows' => $rows]);
$form->display();
echo $OUTPUT->footer();
