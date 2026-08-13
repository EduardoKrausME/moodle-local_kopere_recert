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
 * backup_local_recertification_plugin.class.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup and restore integration for course kopere_recert data.
 */
class backup_local_kopere_recert_plugin extends backup_local_plugin {
    /**
     * Defines the kopere_recert structures included in course backup or restore.
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element(null, $this->get_include_condition(), 'included');
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        $config = new backup_nested_element('kopere_recert_config', ['id'], [
            'enabled', 'triggertype', 'intervaldays', 'fixedmonth', 'fixedday',
            'referencecmid', 'resetcompetencies', 'selfenabled', 'selfreferencetype', 'selfafterdays', 'configjson',
            'timecreated', 'timemodified',
        ]);
        $wrapper->add_child($config);
        $config->set_source_table('local_kopere_recert_course', ['courseid' => backup::VAR_COURSEID]);

        $notices = new backup_nested_element('notices');
        $notice = new backup_nested_element('notice', ['id'], [
            'eventtype', 'offsetdays', 'enabled', 'subject', 'body', 'timecreated', 'timemodified',
        ]);
        $wrapper->add_child($notices);
        $notices->add_child($notice);
        $notice->set_source_table('local_kopere_recert_notice', ['courseid' => backup::VAR_COURSEID]);

        if ($this->get_setting_value('userinfo')) {
            $cycles = new backup_nested_element('cycles');
            $cycle = new backup_nested_element('cycle', ['id'], [
                'userid', 'number', 'name', 'reason', 'source', 'status', 'previouscompletedat',
                'dueat', 'availableat', 'startedat', 'completedat', 'createdby',
                'errorcode', 'errormessage', 'timecreated', 'timemodified',
            ]);
            $histories = new backup_nested_element('histories');
            $history = new backup_nested_element('history', ['id'], [
                'userid', 'taskid', 'component', 'cmid', 'instanceid', 'activityname',
                'activitytype', 'completedat', 'grade', 'html', 'datajson', 'sortorder', 'timecreated',
            ]);
            $filemetadata = new backup_nested_element('filemetadata');
            $filemeta = new backup_nested_element('filemeta', ['id'], [
                'userid', 'sourcecomponent', 'sourcefilearea', 'sourceitemid', 'sourcecontextid',
                'filepath', 'filename', 'mimetype', 'filesize', 'contenthash', 'timecreated',
            ]);

            $wrapper->add_child($cycles);
            $cycles->add_child($cycle);
            $cycle->add_child($histories);
            $histories->add_child($history);
            $history->add_child($filemetadata);
            $filemetadata->add_child($filemeta);

            $cycle->set_source_table('local_kopere_recert_cycle', ['courseid' => backup::VAR_COURSEID]);
            $history->set_source_table('local_kopere_recert_history', ['cycleid' => backup::VAR_PARENTID]);
            $filemeta->set_source_table('local_kopere_recert_file', ['historyid' => backup::VAR_PARENTID]);

            $cycle->annotate_ids('user', 'userid');
            $cycle->annotate_ids('user', 'createdby');
            $history->annotate_ids('user', 'userid');
            $history->annotate_ids('course_module', 'cmid');
            $history->annotate_files('local_kopere_recert', 'historyfiles', 'id');
        }

        return $plugin;
    }
}
