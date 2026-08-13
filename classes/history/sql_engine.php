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
 * sql_engine.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\history;

use invalid_parameter_exception;
use moodle_exception;

/**
 * Executes validated read-only SQL used by historical Mustache helpers.
 */
class sql_engine {
    /** Read-only SQL validator. */
    private readonly sql_validator $validator;

    /**
     * Creates a new SQL engine instance.
     *
     * @param sql_validator|null $validator Read-only SQL validator.
     */
    public function __construct(?sql_validator $validator = null) {
        $this->validator = $validator ?? new sql_validator();
    }

    /**
     * Executes a read-only query and returns its single scalar value.
     *
     * @param string $sql SQL statement to validate and execute.
     * @param array $params Bound SQL parameters.
     * @return string Scalar query value, or an empty string when no record exists.
     */
    public function echo_value(string $sql, array $params): string {
        global $DB;

        $this->validator->validate($sql);
        $params = $this->filter_params($sql, $params);
        $recordset = $DB->get_recordset_sql($sql, $params, 0, 2);
        $rows = [];
        try {
            foreach ($recordset as $record) {
                $rows[] = $record;
                if (count($rows) > 1) {
                    break;
                }
            }
        } finally {
            $recordset->close();
        }

        if (!$rows) {
            return '';
        }
        if (count($rows) !== 1) {
            throw new moodle_exception('sqlechomultiplerows', 'local_kopere_recert');
        }

        $values = array_values((array) $rows[0]);
        if (count($values) !== 1) {
            throw new moodle_exception('sqlechomultiplecolumns', 'local_kopere_recert');
        }

        return (string) $values[0];
    }

    /**
     * Executes a read-only query and renders the result as an escaped HTML table.
     *
     * @param string $sql SQL statement to validate and execute.
     * @param array $params Bound SQL parameters.
     * @return string Escaped HTML table.
     */
    public function table(string $sql, array $params): string {
        global $DB;

        $this->validator->validate($sql);
        $params = $this->filter_params($sql, $params);
        $recordset = $DB->get_recordset_sql($sql, $params);
        $headers = null;
        $rowshtml = '';

        try {
            foreach ($recordset as $record) {
                $row = (array) $record;
                if ($headers === null) {
                    $headers = array_keys($row);
                }
                $rowshtml .= '<tr>';
                foreach ($headers as $header) {
                    $rowshtml .= '<td>' . s((string) ($row[$header] ?? '')) . '</td>';
                }
                $rowshtml .= '</tr>';
            }
        } finally {
            $recordset->close();
        }

        $html = '<table class="generaltable local-kopere_recert-sqltable">';
        if ($headers !== null) {
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th scope="col">' . s($header) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>' . $rowshtml . '</tbody></table>';

        return $html;
    }

    /**
     * Filters bound parameters to those referenced by the SQL statement.
     *
     * @param string $sql SQL statement to inspect.
     * @param array $params Bound SQL parameters.
     * @return array Filtered bound parameters.
     */
    private function filter_params(string $sql, array $params): array {
        $allowed = [
            'userid',
            'courseid',
            'cmid',
            'instanceid',
            'contextid',
            'cycleid',
            'kopere_recertid',
        ];
        preg_match_all('/(?<!:):([a-z][a-z0-9_]*)/i', $sql, $matches);
        $names = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));

        foreach ($names as $name) {
            if (!in_array($name, $allowed, true)) {
                throw new invalid_parameter_exception("Unsupported SQL placeholder: :{$name}");
            }
            if (!array_key_exists($name, $params)) {
                throw new invalid_parameter_exception("Missing SQL placeholder value: :{$name}");
            }
        }

        return array_intersect_key($params, array_flip($names));
    }
}
