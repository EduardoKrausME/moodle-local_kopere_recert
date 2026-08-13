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
 * upgrade.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles the xmldb local kopere_recert upgrade operation.
 *
 * @param int $oldversion Oldversion.
 * @return bool Boolean result.
 */
function xmldb_local_kopere_recert_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081201) {
        $table = new xmldb_table('local_kopere_recert_task');
        $field = new xmldb_field('origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'generic', 'component');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026081201, 'local', 'kopere_recert');
    }

    if ($oldversion < 2026081202) {
        $table = new xmldb_table('local_kopere_recert_course');
        $field = new xmldb_field(
            'resetcompetencies',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'referencecmid'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026081202, 'local', 'kopere_recert');
    }

    if ($oldversion < 2026081203) {
        $table = new xmldb_table('local_kopere_recert_course');
        $field = new xmldb_field(
            'selfreferencetype',
            XMLDB_TYPE_CHAR,
            '40',
            null,
            XMLDB_NOTNULL,
            null,
            'enrolment',
            'selfenabled'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026081203, 'local', 'kopere_recert');
    }

    if ($oldversion < 2026081204) {
        // Code-only release: all classes and methods now include PHPDoc documentation.
        upgrade_plugin_savepoint(true, 2026081204, 'local', 'kopere_recert');
    }

    if ($oldversion < 2026081300) {
        // Rename the legacy tables created with the old local_recert_* prefix.
        $tablerenames = [
            'local_recert_cycle' => 'local_kopere_recert_cycle',
            'local_recert_task' => 'local_kopere_recert_task',
            'local_recert_history' => 'local_kopere_recert_history',
            'local_recert_file' => 'local_kopere_recert_file',
            'local_recert_course' => 'local_kopere_recert_course',
            'local_recert_notice' => 'local_kopere_recert_notice',
            'local_recert_notice_log' => 'local_kopere_recert_notice_log',
            'local_recert_log' => 'local_kopere_recert_log',
        ];

        foreach ($tablerenames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);
            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }

        // Older upgrades looked for the final table names before the legacy tables were renamed.
        // Ensure the fields introduced by those releases are present after the rename.
        $table = new xmldb_table('local_kopere_recert_task');
        $field = new xmldb_field(
            'origin',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'generic',
            'component'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_kopere_recert_course');
        $field = new xmldb_field(
            'resetcompetencies',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'referencecmid'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'selfreferencetype',
            XMLDB_TYPE_CHAR,
            '40',
            null,
            XMLDB_NOTNULL,
            null,
            'enrolment',
            'selfenabled'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081300, 'local', 'kopere_recert');
    }

    if ($oldversion < 2026081301) {
        require_once(__DIR__ . '/../classes/install/default_tasks.php');
        \local_kopere_recert\install\default_tasks::create();
        upgrade_plugin_savepoint(true, 2026081301, 'local', 'kopere_recert');
    }

    return true;
}
