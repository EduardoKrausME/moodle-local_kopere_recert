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
 * Course configuration form.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use coding_exception;
use local_kopere_recert\course\reference_date_provider_interface;
use local_kopere_recert\subplugin\manager;
use moodleform;
use stdClass;

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

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_kopere_recert'));

        $mform->addElement('select', 'triggertype', get_string('triggertype', 'local_kopere_recert'), [
            'enrolment' => get_string('trigger_enrolment', 'local_kopere_recert'),
            'fixeddate' => get_string('trigger_fixeddate', 'local_kopere_recert'),
            'completion' => get_string('trigger_completion', 'local_kopere_recert'),
            'certificate' => get_string('trigger_certificate', 'local_kopere_recert'),
            'lastkopere_recert' => get_string('trigger_lastkopere_recert', 'local_kopere_recert'),
        ]);

        $mform->addElement('text', 'intervaldays', get_string('intervaldays', 'local_kopere_recert'));
        $mform->setType('intervaldays', PARAM_INT);

        $monthnumbers = range(1, 12);
        $monthnames = array_map(
            fn($month) => userdate(make_timestamp(2024, $month, 1), '%B'),
            $monthnumbers
        );
        $months = array_combine($monthnumbers, $monthnames);
        $mform->addElement('select', 'fixedmonth', get_string('fixedmonth', 'local_kopere_recert'), $months);
        $mform->addElement(
            'select',
            'fixedday',
            get_string('fixedday', 'local_kopere_recert'),
            array_combine(range(1, 31), range(1, 31))
        );

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
        $mform->addElement('select', 'referencecmid', get_string('referencecmid', 'local_kopere_recert'), $options);

        $mform->addElement('selectyesno', 'resetcompetencies', get_string('resetcompetencies', 'local_kopere_recert'));
        $mform->addElement('static', 'resetcompetencieshelp', '', get_string('resetcompetencies_desc', 'local_kopere_recert'));

        $mform->addElement('selectyesno', 'selfenabled', get_string('selfenabled', 'local_kopere_recert'));
        $mform->addElement('select', 'selfreferencetype', get_string('selfreferencetype', 'local_kopere_recert'), [
            'enrolment' => get_string('selfreference_enrolment', 'local_kopere_recert'),
            'completion' => get_string('selfreference_completion', 'local_kopere_recert'),
            'lastkopere_recert' => get_string('selfreference_lastkopere_recert', 'local_kopere_recert'),
            'certificate' => get_string('selfreference_certificate', 'local_kopere_recert'),
        ]);
        $mform->addElement('text', 'selfafterdays', get_string('selfafterdays', 'local_kopere_recert'));
        $mform->setType('selfafterdays', PARAM_INT);

        $mform->hideIf('triggertype', 'enabled', 'eq', 0);
        $mform->hideIf('intervaldays', 'enabled', 'eq', 0);
        $mform->hideIf('fixedmonth', 'enabled', 'eq', 0);
        $mform->hideIf('fixedday', 'enabled', 'eq', 0);
        $mform->hideIf('referencecmid', 'enabled', 'eq', 0);
        $mform->hideIf('selfenabled', 'enabled', 'eq', 0);
        $mform->hideIf('selfreferencetype', 'enabled', 'eq', 0);
        $mform->hideIf('selfafterdays', 'enabled', 'eq', 0);
        $mform->hideIf('resetcompetencies', 'enabled', 'eq', 0);
        $mform->hideIf('resetcompetencieshelp', 'enabled', 'eq', 0);

        // Interval only applies to non-fixed-date triggers.
        $mform->hideIf('intervaldays', 'triggertype', 'eq', 'fixeddate');

        // Fixed date fields.
        $mform->hideIf('fixedmonth', 'triggertype', 'neq', 'fixeddate');
        $mform->hideIf('fixedday', 'triggertype', 'neq', 'fixeddate');

        // Student/manual recertification settings.
        $mform->hideIf('selfreferencetype', 'selfenabled', 'eq', 0);
        $mform->hideIf('selfafterdays', 'selfenabled', 'eq', 0);

        $this->add_action_buttons();
    }

    /**
     * Validates submitted Moodle form data.
     *
     * @param mixed $data Structured data.
     * @param mixed $files Files.
     * @return array Structured result data.
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['enabled'])) {
            return $errors;
        }

        $trigger = (string)($data['triggertype'] ?? '');
        if (!in_array($trigger, ['enrolment', 'fixeddate', 'completion', 'certificate', 'lastkopere_recert'], true)) {
            $errors['triggertype'] = get_string('required');
        } else if ($trigger === 'fixeddate') {
            $month = (int)($data['fixedmonth'] ?? 0);
            $day = (int)($data['fixedday'] ?? 0);
            if ($month < 1 || $month > 12) {
                $errors['fixedmonth'] = get_string('required');
            }
            if ($day < 1 || $day > 31) {
                $errors['fixedday'] = get_string('required');
            }
        } else if ((int)($data['intervaldays'] ?? 0) <= 0) {
            $errors['intervaldays'] = get_string('intervalmustbepositive', 'local_kopere_recert');
        }

        if (!empty($data['selfenabled'])) {
            $reference = (string)($data['selfreferencetype'] ?? '');
            if (!in_array($reference, ['enrolment', 'completion', 'lastkopere_recert', 'certificate'], true)) {
                $errors['selfreferencetype'] = get_string('invalidselfreference', 'local_kopere_recert');
            }
            if (!isset($data['selfafterdays'])) {
                $errors['selfafterdays'] = get_string('required');
            } else if ((int)$data['selfafterdays'] < 0) {
                $errors['selfafterdays'] = get_string('selfafterdaysinvalid', 'local_kopere_recert');
            }
        }

        if (($trigger === 'certificate'
                || (!empty($data['selfenabled']) && ($data['selfreferencetype'] ?? '') === 'certificate'))
            && empty($data['referencecmid'])) {
            $errors['referencecmid'] = get_string('missingcertificatereference', 'local_kopere_recert');
        }
        return $errors;
    }

    /**
     * Builds a database record from validated form data, retaining inactive settings.
     *
     * @param int $courseid Course ID.
     * @param stdClass $data Validated form data.
     * @param stdClass|null $current Existing configuration, if any.
     * @return stdClass Record ready for persistence.
     */
    public static function build_record(int $courseid, stdClass $data, ?stdClass $current = null): stdClass {
        $enabled = (int)$data->enabled;
        $trigger = $enabled ? $data->triggertype : ($current->triggertype ?? 'completion');
        $selfenabled = $enabled ? (int)($data->selfenabled ?? 0) : (int)($current->selfenabled ?? 0);
        $fixedactive = $enabled && $trigger === 'fixeddate';
        $selfactive = $enabled && $selfenabled;

        return (object)[
            'courseid' => $courseid,
            'enabled' => $enabled,
            'triggertype' => $trigger,
            'intervaldays' => (int)($enabled && !$fixedactive ? $data->intervaldays : ($current->intervaldays ?? 0)),
            'fixedmonth' => (int)($fixedactive ? $data->fixedmonth : ($current->fixedmonth ?? 0)),
            'fixedday' => (int)($fixedactive ? $data->fixedday : ($current->fixedday ?? 0)),
            'referencecmid' => (int)($enabled ? ($data->referencecmid ?? $current->referencecmid ?? 0)
                : ($current->referencecmid ?? 0)) ?: null,
            'resetcompetencies' => (int)($enabled ? ($data->resetcompetencies ?? 0) : ($current->resetcompetencies ?? 0)),
            'selfenabled' => $selfenabled,
            'selfreferencetype' => $selfactive ? $data->selfreferencetype : ($current->selfreferencetype ?? 'enrolment'),
            'selfafterdays' => (int)($selfactive ? $data->selfafterdays : ($current->selfafterdays ?? 0)),
            'configjson' => $current->configjson ?? null,
            'timemodified' => time(),
        ];
    }
}
