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
 * restore_local_recertification_plugin.class.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup and restore integration for course kopere_recert data.
 */
class restore_local_kopere_recert_plugin extends restore_local_plugin {
    /**
     * Defines the kopere_recert structures included in course backup or restore.
     */
    protected function define_course_plugin_structure() {
        $paths = [];
        $paths[] = new restore_path_element(
            'local_kopere_recert_config',
            $this->get_pathfor('/kopere_recert_config')
        );
        $paths[] = new restore_path_element(
            'local_kopere_recert_notice',
            $this->get_pathfor('/notices/notice')
        );
        $paths[] = new restore_path_element(
            'local_kopere_recert_cycle',
            $this->get_pathfor('/cycles/cycle')
        );
        $paths[] = new restore_path_element(
            'local_kopere_recert_history',
            $this->get_pathfor('/cycles/cycle/histories/history')
        );
        $paths[] = new restore_path_element(
            'local_kopere_recert_filemeta',
            $this->get_pathfor('/cycles/cycle/histories/history/filemetadata/filemeta')
        );
        return $paths;
    }

    /**
     * Restores course kopere_recert configuration data.
     *
     * @param mixed $data Structured data.
     */
    public function process_local_kopere_recert_config($data): void {
        global $DB;

        $data = (object)$data;
        $data->courseid = $this->get_courseid();
        unset($data->id);

        if ($data->referencecmid) {
            $data->referencecmid = $this->get_mappingid('course_module', $data->referencecmid, 0) ?: null;
        }

        $existing = $DB->get_record('local_kopere_recert_course', ['courseid' => $data->courseid]);
        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('local_kopere_recert_course', $data);
        } else {
            $DB->insert_record('local_kopere_recert_course', $data);
        }
    }

    /**
     * Restores one course kopere_recert notice.
     *
     * @param mixed $data Structured data.
     */
    public function process_local_kopere_recert_notice($data): void {
        global $DB;
        $data = (object)$data;
        unset($data->id);
        $data->courseid = $this->get_courseid();
        $DB->insert_record('local_kopere_recert_notice', $data);
    }

    /**
     * Restores one user kopere_recert cycle.
     *
     * @param mixed $data Structured data.
     */
    public function process_local_kopere_recert_cycle($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);
        $data->courseid = $this->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->createdby = $data->createdby ? $this->get_mappingid('user', $data->createdby, 0) : null;
        if (!$data->userid) {
            return;
        }

        // Avoid sequence collisions with pre-existing history after import.
        $max = $DB->get_field_sql(
            "SELECT MAX(number) FROM {local_kopere_recert_cycle} WHERE courseid = :courseid AND userid = :userid",
            ['courseid' => $data->courseid, 'userid' => $data->userid]
        );
        $data->number = max((int)$data->number, ((int)$max) + 1);

        $newid = $DB->insert_record('local_kopere_recert_cycle', $data);
        $this->set_mapping('local_kopere_recert_cycle', $oldid, $newid);
    }

    /**
     * Restores one historical snapshot.
     *
     * @param mixed $data Structured data.
     */
    public function process_local_kopere_recert_history($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);

        $data->cycleid = $this->get_new_parentid('local_kopere_recert_cycle');
        if (!$data->cycleid) {
            return;
        }
        $data->courseid = $this->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->cmid = $data->cmid ? $this->get_mappingid('course_module', $data->cmid, 0) : null;
        // Global task IDs belong to the source Moodle installation and are not portable.
        $data->taskid = null;

        $newid = $DB->insert_record('local_kopere_recert_history', $data);
        $this->set_mapping('local_kopere_recert_history', $oldid, $newid, true);
        $this->add_related_files('local_kopere_recert', 'historyfiles', 'local_kopere_recert_history', $oldid);
    }


    /**
     * Restores historical file metadata.
     *
     * @param mixed $data Structured data.
     */
    public function process_local_kopere_recert_filemeta($data): void {
        global $DB;

        $data = (object)$data;
        unset($data->id);
        $historyid = $this->get_new_parentid('local_kopere_recert_history');
        if (!$historyid) {
            return;
        }
        $history = $DB->get_record('local_kopere_recert_history', ['id' => $historyid], 'id,cycleid,userid,courseid', MUST_EXIST);
        $data->historyid = $historyid;
        $data->cycleid = $history->cycleid;
        $data->userid = $history->userid;
        $data->courseid = $history->courseid;
        $DB->insert_record('local_kopere_recert_file', $data);
    }
}
