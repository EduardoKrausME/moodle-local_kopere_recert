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
 * course_form.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\form;

use local_kopere_recertification\course\reference_date_provider_interface;
use local_kopere_recertification\subplugin\manager;
use moodleform;

/**
 * Moodle form for course configuration.
 */
class course_form extends moodleform {
    /**
     * Defines the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courseid = (int)$this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_kopere_recertification'));

        $mform->addElement('select', 'triggertype', get_string('triggertype', 'local_kopere_recertification'), [
            'enrolment' => get_string('trigger_enrolment', 'local_kopere_recertification'),
            'fixeddate' => get_string('trigger_fixeddate', 'local_kopere_recertification'),
            'completion' => get_string('trigger_completion', 'local_kopere_recertification'),
            'certificate' => get_string('trigger_certificate', 'local_kopere_recertification'),
            'lastkopere_recertification' => get_string('trigger_lastkopere_recertification', 'local_kopere_recertification'),
        ]);

        $mform->addElement('text', 'intervaldays', get_string('intervaldays', 'local_kopere_recertification'));
        $mform->setType('intervaldays', PARAM_INT);

        $months = array_combine(range(1, 12), array_map(fn($m) => userdate(make_timestamp(2024, $m, 1), '%B'), range(1, 12)));
        $mform->addElement('select', 'fixedmonth', get_string('fixedmonth', 'local_kopere_recertification'), $months);
        $mform->addElement('select', 'fixedday', get_string('fixedday', 'local_kopere_recertification'), array_combine(range(1, 31), range(1, 31)));

        $modinfo = get_fast_modinfo($courseid);
        $options = [0 => get_string('none')];
        $subplugins = new manager();
        foreach ($modinfo->get_cms() as $cm) {
            $provider = $subplugins->get_for_component('mod_' . $cm->modname);
            if (!$provider || !$provider instanceof reference_date_provider_interface) {
                continue;
            }
            $options[$cm->id] = format_string($cm->name) . ' (' . $cm->modname . ')';
        }
        $mform->addElement('select', 'referencecmid', get_string('referencecmid', 'local_kopere_recertification'), $options);

        $mform->addElement('advcheckbox', 'resetcompetencies', get_string('resetcompetencies', 'local_kopere_recertification'));
        $mform->addElement('static', 'resetcompetencieshelp', '', get_string('resetcompetencies_desc', 'local_kopere_recertification'));

        $mform->addElement('advcheckbox', 'selfenabled', get_string('selfenabled', 'local_kopere_recertification'));
        $mform->addElement('select', 'selfreferencetype', get_string('selfreferencetype', 'local_kopere_recertification'), [
            'enrolment' => get_string('selfreference_enrolment', 'local_kopere_recertification'),
            'completion' => get_string('selfreference_completion', 'local_kopere_recertification'),
            'lastkopere_recertification' => get_string('selfreference_lastkopere_recertification', 'local_kopere_recertification'),
            'certificate' => get_string('selfreference_certificate', 'local_kopere_recertification'),
        ]);
        $mform->addElement('text', 'selfafterdays', get_string('selfafterdays', 'local_kopere_recertification'));
        $mform->setType('selfafterdays', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validates submitted Moodle form data.
     *
     * @param mixed $data Structured data.
     * @param mixed $files Files.
     * @return array Structured result data.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $trigger = (string)($data['triggertype'] ?? '');
        if ($trigger !== 'fixeddate' && (int)($data['intervaldays'] ?? 0) <= 0) {
            $errors['intervaldays'] = get_string('intervalmustbepositive', 'local_kopere_recertification');
        }
        if (($trigger === 'certificate'
                || (!empty($data['selfenabled']) && ($data['selfreferencetype'] ?? '') === 'certificate'))
                && empty($data['referencecmid'])) {
            $errors['referencecmid'] = get_string('missingcertificatereference', 'local_kopere_recertification');
        }
        if (!empty($data['selfenabled']) && (int)($data['selfafterdays'] ?? 0) < 0) {
            $errors['selfafterdays'] = get_string('selfafterdaysinvalid', 'local_kopere_recertification');
        }
        return $errors;
    }
}
