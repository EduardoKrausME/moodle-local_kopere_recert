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
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_kopere_recertification\course\reference_date_provider_interface;
use local_kopere_recertification\form\course_form;
use local_kopere_recertification\subplugin\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/kopere_recertification:manage', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/kopere_recertification/course.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('courseconfiguration', 'local_kopere_recertification'));
$PAGE->set_heading(format_string($course->fullname));

$form = new course_form(null, ['courseid' => $courseid]);
$current = $DB->get_record('local_recert_course', ['courseid' => $courseid]);
if ($current) {
    $form->set_data($current);
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    if ($data->triggertype !== 'fixeddate' && (int)$data->intervaldays <= 0) {
        throw new invalid_parameter_exception(get_string('intervalmustbepositive', 'local_kopere_recertification'));
    }
    if ($data->triggertype === 'certificate'
            || (!empty($data->selfenabled) && $data->selfreferencetype === 'certificate')) {
        if (empty($data->referencecmid)) {
            throw new invalid_parameter_exception(get_string('missingcertificatereference', 'local_kopere_recertification'));
        }
        $cm = get_coursemodule_from_id('', (int)$data->referencecmid, $courseid, false, MUST_EXIST);
        $provider = (new manager())->get_for_component('mod_' . $cm->modname);
        if (!$provider || !$provider instanceof reference_date_provider_interface) {
            throw new moodle_exception('certificatereferenceunavailable', 'local_kopere_recertification');
        }
    }
    $record = (object)[
        'courseid' => $courseid,
        'enabled' => (int)$data->enabled,
        'triggertype' => $data->triggertype,
        'intervaldays' => (int)$data->intervaldays,
        'fixedmonth' => (int)$data->fixedmonth,
        'fixedday' => (int)$data->fixedday,
        'referencecmid' => (int)$data->referencecmid ?: null,
        'resetcompetencies' => (int)$data->resetcompetencies,
        'selfenabled' => (int)$data->selfenabled,
        'selfreferencetype' => $data->selfreferencetype,
        'selfafterdays' => (int)$data->selfafterdays,
        'configjson' => null,
        'timemodified' => time(),
    ];
    if ($current) {
        $record->id = $current->id;
        $DB->update_record('local_recert_course', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_recert_course', $record);
    }
    redirect($PAGE->url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

$notices = $DB->get_records('local_recert_notice', ['courseid' => $courseid], 'eventtype, offsetdays DESC');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_kopere_recertification/course_header', [
    'noticesurl' => (new moodle_url('/local/kopere_recertification/notices.php', ['courseid' => $courseid]))->out(false),
    'bulkurl' => (new moodle_url('/local/kopere_recertification/bulk.php', ['courseid' => $courseid]))->out(false),
    'historyurl' => (new moodle_url('/local/kopere_recertification/history.php', ['courseid' => $courseid]))->out(false),
    'noticescount' => count($notices),
]);
$form->display();
echo $OUTPUT->footer();
