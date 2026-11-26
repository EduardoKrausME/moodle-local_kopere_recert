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
 * course.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_kopere_recert\course\reference_date_provider_interface;
use local_kopere_recert\form\course_form;
use local_kopere_recert\subplugin\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/kopere_recert:manage', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]));

\local_kopere_recert\mcp_banner::dismiss_if_requested($PAGE->url);
$PAGE->set_title(get_string('courseconfiguration', 'local_kopere_recert'));
$PAGE->set_heading(format_string($course->fullname));

$form = new course_form(null, ['courseid' => $courseid]);
$current = $DB->get_record('local_kopere_recert_course', ['courseid' => $courseid]);
if ($current) {
    $form->set_data($current);
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $record = course_form::build_record($courseid, $data, $current ?: null);
    if ($record->enabled && ($record->triggertype === 'certificate'
        || ($record->selfenabled && $record->selfreferencetype === 'certificate'))) {
        $cm = get_coursemodule_from_id('', $record->referencecmid, $courseid, false, MUST_EXIST);
        $provider = (new manager())->get_for_component('mod_' . $cm->modname);
        if (!$provider || !$provider instanceof reference_date_provider_interface) {
            throw new moodle_exception('certificatereferenceunavailable', 'local_kopere_recert');
        }
    }
    if ($current) {
        $record->id = $current->id;
        $DB->update_record('local_kopere_recert_course', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_kopere_recert_course', $record);
    }
    redirect($PAGE->url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

$notices = $DB->get_records('local_kopere_recert_notice', ['courseid' => $courseid], 'eventtype, offsetdays DESC');

echo $OUTPUT->header();
if (\local_kopere_recert\mcp_banner::should_show()) {
    echo \local_kopere_recert\mcp_banner::render($OUTPUT, $PAGE->url);
}

echo $OUTPUT->render_from_template('local_kopere_recert/course_header', [
    'courseurl' => new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]),
    'noticesurl' => new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]),
    'bulkurl' => new moodle_url('/local/kopere_recert/bulk.php', ['courseid' => $courseid]),
    'historyurl' => new moodle_url('/local/kopere_recert/history.php', ['courseid' => $courseid]),
    'tasksurl' => new moodle_url('/local/kopere_recert/tasks.php', ['courseid' => $courseid]),
    'noticescount' => count($notices),
    'courseactive' => true,
]);
$form->display();
echo $OUTPUT->footer();
