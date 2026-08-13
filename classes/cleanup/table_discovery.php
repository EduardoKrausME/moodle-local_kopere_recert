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
 * table_discovery.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\cleanup;

use ddl_exception;
use invalid_parameter_exception;
use xmldb_file;

/**
 * Discovers safe user-linked tables from installed plugin XMLDB definitions.
 */
class table_discovery {
    /**
     * Returns user-linked plugin tables eligible for generic cleanup.
     *
     * @param string $component Moodle component name.
     * @return array Eligible table definitions keyed by table name.
     * @throws ddl_exception
     */
    public function get_eligible_tables(string $component): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/xmldb/xmldb_file.php');

        if (!str_starts_with($component, 'mod_')) {
            return [];
        }

        $modname = substr($component, 4);
        $path = $CFG->dirroot . '/mod/' . $modname . '/db/install.xml';
        if (!is_readable($path)) {
            return [];
        }

        $xmldb = new xmldb_file($path);
        if (!$xmldb->fileExists() || !$xmldb->loadXMLStructure()) {
            return [];
        }

        $structure = $xmldb->getStructure();
        $eligible = [];
        foreach ($structure->getTables() as $table) {
            $tablename = $table->getName();
            if ($tablename === $modname) {
                continue;
            }
            $columns = array_keys($table->getFields());
            if (!in_array('userid', $columns, true) && !in_array('user_id', $columns, true)) {
                continue;
            }
            if (!$DB->get_manager()->table_exists($tablename)) {
                continue;
            }
            $eligible[$tablename] = $columns;
        }

        return $eligible;
    }

    /**
     * Ensures that a table is owned by the component and is safe for generic cleanup.
     *
     * @param string $component Moodle component name.
     * @param string $table Database table name.
     * @return array Structured result data.
     * @throws ddl_exception
     * @throws invalid_parameter_exception
     */
    public function assert_allowed(string $component, string $table): array {
        if (!str_starts_with($component, 'mod_')) {
            throw new invalid_parameter_exception('Generic cleanup is only supported for activity components.');
        }
        $modname = substr($component, 4);
        if ($table === $modname) {
            throw new invalid_parameter_exception('The primary activity table is protected.');
        }

        $eligible = $this->get_eligible_tables($component);
        if (!isset($eligible[$table])) {
            throw new invalid_parameter_exception('Table is not eligible for generic cleanup.');
        }
        return $eligible[$table];
    }
}
