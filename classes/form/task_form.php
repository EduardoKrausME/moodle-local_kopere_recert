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
 * task_form.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\form;

use local_kopere_recert\cleanup\table_discovery;
use moodleform;
use MoodleQuickForm;

/**
 * Moodle form for task configuration.
 */
class task_form extends moodleform {
    /**
     * Defines the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $record = $this->_customdata['record'] ?? null;
        $subplugin = $this->_customdata['subplugin'] ?? null;
        $component = (string)($this->_customdata['component'] ?? ($record->component ?? ($subplugin ? $subplugin::get_component() : '')));

        $mform->addElement('hidden', 'id', $record->id ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('static', 'componentdisplay', get_string('component', 'local_kopere_recert'), s($component));
        $mform->addElement('hidden', 'component', $component);
        $mform->setType('component', PARAM_COMPONENT);

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_kopere_recert'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('header', 'historyhdr', get_string('createhistory', 'local_kopere_recert'));
        $mform->addElement('selectyesno', 'historyenabled', get_string('enabled', 'local_kopere_recert'));
        $mform->setDefault('historyenabled', 1);
        if (!$subplugin) {
            $mform->addElement('textarea', 'historytemplate', get_string('historytemplate', 'local_kopere_recert'), ['rows' => 12, 'cols' => 100]);
            $mform->setType('historytemplate', PARAM_RAW);
            $mform->addElement('static', 'sqlhelp', '', get_string('sqltemplatehelp', 'local_kopere_recert'));
        }

        $mform->addElement('header', 'fileshdr', get_string('copyfiles', 'local_kopere_recert'));
        $mform->addElement('selectyesno', 'filesenabled', get_string('enabled', 'local_kopere_recert'));
        if (!$subplugin) {
            $this->add_file_builder($mform, $component);
        }

        $mform->addElement('header', 'cleanuphdr', get_string('cleanupdata', 'local_kopere_recert'));
        $mform->addElement('selectyesno', 'cleanupenabled', get_string('enabled', 'local_kopere_recert'));
        if (!$subplugin) {
            $this->add_cleanup_builder($mform, $component);
        }

        $this->add_action_buttons();
    }

    /**
     * Adds the generic file-copy configuration fields to the form.
     *
     * @param MoodleQuickForm $mform Mform.
     * @param string $component Moodle component name.
     */
    private function add_file_builder(MoodleQuickForm $mform, string $component): void {
        $mform->addElement('static', 'filebuilderhelp', '', get_string('filebuilderhelp', 'local_kopere_recert'));
        for ($i = 1; $i <= 3; $i++) {
            $mform->addElement('static', "filedefinitionlabel_{$i}", '', get_string('filedefinition', 'local_kopere_recert', $i));
            $mform->addElement('text', "filecomponent_{$i}", get_string('sourcecomponent', 'local_kopere_recert'));
            $mform->setType("filecomponent_{$i}", PARAM_COMPONENT);
            $mform->setDefault("filecomponent_{$i}", $component);
            $mform->addElement('text', "filearea_{$i}", get_string('sourcefilearea', 'local_kopere_recert'));
            $mform->setType("filearea_{$i}", PARAM_ALPHANUMEXT);
            $mform->addElement('text', "fileitemid_{$i}", get_string('sourceitemid', 'local_kopere_recert'));
            $mform->setType("fileitemid_{$i}", PARAM_RAW_TRIMMED);
            $mform->setDefault("fileitemid_{$i}", ':userid');
            $mform->addElement('text', "filecontextid_{$i}", get_string('sourcecontextid', 'local_kopere_recert'));
            $mform->setType("filecontextid_{$i}", PARAM_RAW_TRIMMED);
            $mform->setDefault("filecontextid_{$i}", ':contextid');
            $mform->addElement('text', "filepath_{$i}", get_string('filepath', 'local_kopere_recert'));
            $mform->setType("filepath_{$i}", PARAM_PATH);
            $mform->setDefault("filepath_{$i}", '/');
            $mform->addElement('text', "filename_{$i}", get_string('filename', 'local_kopere_recert'));
            $mform->setType("filename_{$i}", PARAM_FILE);
            $mform->addElement('static', "fileuserid_{$i}", get_string('user'), ':userid');
        }
    }

    /**
     * Adds the safe generic cleanup builder fields to the form.
     *
     * @param MoodleQuickForm $mform Mform.
     * @param string $component Moodle component name.
     */
    private function add_cleanup_builder(MoodleQuickForm $mform, string $component): void {
        $discovery = new table_discovery();
        $eligible = $discovery->get_eligible_tables($component);
        $tables = ['' => get_string('none')];
        $allcolumns = [];
        foreach ($eligible as $table => $columns) {
            $tables[$table] = $table;
            foreach ($columns as $column) {
                $allcolumns[$column] = $column;
            }
        }
        ksort($allcolumns);
        $columnoptions = ['' => get_string('none')] + $allcolumns;
        $placeholderoptions = [
            ':userid' => ':userid', ':courseid' => ':courseid', ':cmid' => ':cmid',
            ':instanceid' => ':instanceid', ':contextid' => ':contextid', ':cycleid' => ':cycleid',
            ':kopere_recertid' => ':kopere_recertid',
        ];

        $mform->addElement('static', 'cleanupbuilderhelp', '', get_string('cleanupbuilderhelp', 'local_kopere_recert'));
        if (count($eligible) === 0) {
            $mform->addElement('static', 'nocleanuptables', '', get_string('noeligiblecleanuptables', 'local_kopere_recert'));
            return;
        }

        for ($i = 1; $i <= 3; $i++) {
            $mform->addElement('static', "cleanupdefinitionlabel_{$i}", '', get_string('cleanupdefinition', 'local_kopere_recert', $i));
            $mform->addElement('select', "cleanuptable_{$i}", get_string('table', 'local_kopere_recert'), $tables);
            $mform->addElement('select', "cleanupusercolumn_{$i}", get_string('usercolumn', 'local_kopere_recert'), [
                'userid' => 'userid', 'user_id' => 'user_id',
            ]);
            for ($j = 1; $j <= 3; $j++) {
                $group = [];
                $group[] = $mform->createElement('select', "cleanupcolumn_{$i}_{$j}", '', $columnoptions);
                $group[] = $mform->createElement('static', '', '', '=');
                $group[] = $mform->createElement('select', "cleanupplaceholder_{$i}_{$j}", '', $placeholderoptions);
                $mform->addGroup($group, "cleanupcondition_{$i}_{$j}", get_string('additionalcondition', 'local_kopere_recert', $j), ' ', false);
            }
        }
    }
}
