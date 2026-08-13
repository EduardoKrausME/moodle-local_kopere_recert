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
 * notice_form.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\form;

use moodleform;

/**
 * Moodle form for notice configuration.
 */
class notice_form extends moodleform {
    /**
     * Defines the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courseid = (int)$this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'eventtype', get_string('eventtype', 'local_kopere_recert'), [
            'kopere_recert_available' => get_string('notice_available', 'local_kopere_recert'),
            'kopere_recert_created' => get_string('notice_created', 'local_kopere_recert'),
            'kopere_recert_started' => get_string('notice_started', 'local_kopere_recert'),
            'expiration_warning' => get_string('notice_warning', 'local_kopere_recert'),
            'kopere_recert_due' => get_string('notice_due', 'local_kopere_recert'),
            'kopere_recert_expired' => get_string('notice_expired', 'local_kopere_recert'),
            'kopere_recert_completed' => get_string('notice_completed', 'local_kopere_recert'),
        ]);
        $mform->addElement('text', 'offsetdays', get_string('offsetdays', 'local_kopere_recert'));
        $mform->setType('offsetdays', PARAM_INT);
        $mform->setDefault('offsetdays', 0);
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_kopere_recert'));
        $mform->setDefault('enabled', 1);
        $mform->addElement('text', 'subject', get_string('subject', 'local_kopere_recert'), ['size' => 80]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addElement('editor', 'body_editor', get_string('body', 'local_kopere_recert'), null, ['maxfiles' => 0]);
        $this->add_action_buttons();
    }
}
