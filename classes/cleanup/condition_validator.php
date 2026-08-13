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
 * condition_validator.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\cleanup;

use invalid_parameter_exception;

/**
 * Validates generic cleanup conditions, columns, and execution placeholders.
 */
class condition_validator {
    public const ALLOWED_PLACEHOLDERS = [
        'userid', 'courseid', 'cmid', 'instanceid', 'contextid', 'cycleid', 'kopere_recertid',
    ];

    /**
     * Validates that a cleanup column exists and is allowed.
     *
     * @param string $column Database column name.
     * @throws invalid_parameter_exception
     */
    public function validate_column(string $column): void {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
            throw new invalid_parameter_exception('Invalid database column.');
        }
    }

    /**
     * Validates an execution placeholder used by the cleanup builder.
     *
     * @param string $placeholder Placeholder.
     * @throws invalid_parameter_exception
     */
    public function validate_placeholder(string $placeholder): void {
        $placeholder = ltrim($placeholder, ':');
        if (!in_array($placeholder, self::ALLOWED_PLACEHOLDERS, true)) {
            throw new invalid_parameter_exception('Invalid cleanup placeholder.');
        }
    }

    /**
     * Builds the safe SQL condition and bound values used by Moodle DML.
     *
     * @param array $conditions Configured cleanup conditions.
     * @param array $knowncolumns Knowncolumns.
     * @param array $params Bound SQL parameters.
     * @return array Safe SQL condition and bound parameters.
     * @throws invalid_parameter_exception
     */
    public function build(array $conditions, array $knowncolumns, array $params): array {
        if (!$conditions) {
            throw new invalid_parameter_exception('At least one cleanup condition is required.');
        }

        $parts = [];
        $bound = [];
        $hasuser = false;

        foreach ($conditions as $condition) {
            $column = (string)($condition['column'] ?? '');
            $operator = (string)($condition['operator'] ?? '=');
            $placeholder = ltrim((string)($condition['placeholder'] ?? ''), ':');

            $this->validate_column($column);
            $this->validate_placeholder($placeholder);

            if (!in_array($column, $knowncolumns, true)) {
                throw new invalid_parameter_exception("Unknown column: {$column}");
            }
            if ($operator !== '=') {
                throw new invalid_parameter_exception('Only equality conditions are supported for generic cleanup.');
            }
            if (!array_key_exists($placeholder, $params)) {
                throw new invalid_parameter_exception("Missing parameter: {$placeholder}");
            }

            if (($column === 'userid' || $column === 'user_id') && $placeholder === 'userid') {
                $hasuser = true;
            }

            $paramname = 'p' . count($bound);
            $parts[] = "{$column} = :{$paramname}";
            $bound[$paramname] = $params[$placeholder];
        }

        if (!$hasuser) {
            throw new invalid_parameter_exception('Generic cleanup must contain userid/user_id = :userid.');
        }

        return [implode(' AND ', $parts), $bound];
    }
}
