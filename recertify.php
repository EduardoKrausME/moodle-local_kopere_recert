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
 * recertify.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_kopere_recert\form\manual_form;
use local_kopere_recert\recertification\manager;
use local_kopere_recert\recertification\simulator;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

if ($userid === (int) $USER->id) {
    require_capability('local/kopere_recert:recertifyself', $context);
    $source = \local_kopere_recert\cycle\manager::SOURCE_MANUAL_USER;
} else {
    require_capability('local/kopere_recert:recertify', $context);
    $source = \local_kopere_recert\cycle\manager::SOURCE_MANUAL_ADMIN;
}

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recert/recertify.php', [
    'courseid' => $courseid,
    'userid' => $userid,
]));
$PAGE->set_title(get_string('startkopere_recert', 'local_kopere_recert'));
$PAGE->set_heading(format_string($course->fullname));

$form = new manual_form(null, ['courseid' => $courseid, 'userid' => $userid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $reason = trim($data->reason);
    if (!empty($data->simulate)) {
        require_capability('local/kopere_recert:simulate', $context);
        $report = (new simulator())->simulate(
            $courseid,
            $userid,
            $reason,
            $reason,
            $source,
            (int) $USER->id
        );
        $SESSION->local_kopere_recert_simulation = $report;
        redirect(new moodle_url('/local/kopere_recert/simulation.php', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]));
    }

    $cycle = (new manager())->create_and_queue(
        $courseid,
        $userid,
        $reason,
        $reason,
        $source,
        (int) $USER->id
    );
    redirect(
        new moodle_url('/local/kopere_recert/history.php', [
            'courseid' => $courseid,
            'userid' => $userid,
            'cycleid' => $cycle->id,
        ]),
        get_string('kopere_recertqueued', 'local_kopere_recert'),
        null,
        notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
