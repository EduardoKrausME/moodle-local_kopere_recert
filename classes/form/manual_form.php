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
 * manual_form.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\form;

use moodleform;

/**
 * Moodle form for manual configuration.
 */
class manual_form extends moodleform {
    /**
     * Defines the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'userid', $this->_customdata['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('text', 'reason', get_string('reason', 'local_kopere_recert'), ['size' => 80]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required');
        $mform->addElement('advcheckbox', 'simulate', get_string('simulate', 'local_kopere_recert'));
        $this->add_action_buttons(true, get_string('startkopere_recert', 'local_kopere_recert'));
    }
}
