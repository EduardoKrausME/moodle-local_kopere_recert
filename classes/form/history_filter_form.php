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
 * History filter form.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\form;

use moodleform;

/**
 * Moodle form for history filter configuration.
 */
class history_filter_form extends moodleform {
    /**
     * Defines the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        global $DB, $USER;

        $mform = $this->_form;
        $courseid = (int)($this->_customdata['courseid'] ?? 0);
        $userid = (int)($this->_customdata['userid'] ?? $USER->id);
        $canselectuser = !empty($this->_customdata['canselectuser']);

        $courseoptions = [];
        if ($courseid) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname,shortname');
            if ($course) {
                $courseoptions[$course->id] = format_string($course->fullname);
            }
        }
        $mform->addElement('autocomplete', 'courseid', get_string('course'), $courseoptions, [
            'ajax' => 'core/form-course-selector',
            'multiple' => false,
            'noselectionstring' => get_string('allcourses', 'local_kopere_recert'),
        ]);
        $mform->setType('courseid', PARAM_INT);
        if ($courseid) {
            $mform->setDefault('courseid', $courseid);
        }

        if ($canselectuser) {
            $useroptions = [];
            $userfields = 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename';
            $user = $DB->get_record('user', ['id' => $userid], $userfields);
            if ($user) {
                $useroptions[$user->id] = fullname($user);
            }
            $mform->addElement('autocomplete', 'userid', get_string('user'), $useroptions, [
                'ajax' => 'core_user/form_user_selector',
                'multiple' => false,
            ]);
            $mform->setType('userid', PARAM_INT);
            $mform->setDefault('userid', $userid);
        } else {
            $mform->addElement('hidden', 'userid', $userid);
            $mform->setType('userid', PARAM_INT);
        }

        $mform->addElement('submit', 'filterbutton', get_string('filter', 'local_kopere_recert'));
    }
}
