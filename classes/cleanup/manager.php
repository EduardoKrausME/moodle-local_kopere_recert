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
 * manager.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\cleanup;

use dml_exception;
use invalid_parameter_exception;
use local_kopere_recertification\task\task_context;

/**
 * Executes generic cleanup through Moodle DML without administrator-authored DELETE SQL.
 */
class manager {
    /**
     * Creates a new manager instance.
     *
     * @param table_discovery $discovery Discovery.
     * @param condition_validator $validator Validator.
     */
    public function __construct(
        private readonly table_discovery $discovery = new table_discovery(),
        private readonly condition_validator $validator = new condition_validator(),
    ) {
    }

    /**
     * Cleans the current user data after history and files have been safely preserved.
     *
     * @param string $component Moodle component name.
     * @param array $config Configuration record.
     * @param task_context $context Execution context.
     * @return int Structured cleanup result.
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function cleanup(string $component, array $config, task_context $context): int {
        global $DB;

        $table = (string)($config['table'] ?? '');
        $conditions = $config['conditions'] ?? [];
        $columns = $this->discovery->assert_allowed($component, $table);
        [$select, $params] = $this->validator->build($conditions, $columns, $context->get_sql_params());

        $count = $DB->count_records_select($table, $select, $params);
        $DB->delete_records_select($table, $select, $params);
        return $count;
    }

    /**
     * Counts records affected by a configured cleanup operation.
     *
     * @param string $component Moodle component name.
     * @param array $config Configuration record.
     * @param task_context $context Execution context.
     * @return int Number of records matching the cleanup configuration.
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function count(string $component, array $config, task_context $context): int {
        global $DB;
        $table = (string)($config['table'] ?? '');
        $columns = $this->discovery->assert_allowed($component, $table);
        [$select, $params] = $this->validator->build(
            $config['conditions'] ?? [],
            $columns,
            $context->get_sql_params()
        );
        return $DB->count_records_select($table, $select, $params);
    }
}
