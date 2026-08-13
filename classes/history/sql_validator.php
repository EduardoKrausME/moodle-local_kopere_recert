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
 * sql_validator.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\history;

use invalid_parameter_exception;

/**
 * Validates administrator-authored history SQL and enforces read-only execution.
 */
class sql_validator {
    /** SQL keywords that are forbidden in history template queries. */
    private const FORBIDDEN = [
        'DELETE',
        'UPDATE',
        'INSERT',
        'REPLACE',
        'ALTER',
        'DROP',
        'TRUNCATE',
        'CREATE',
        'GRANT',
        'REVOKE',
        'CALL',
        'EXECUTE',
        'MERGE',
        'UPSERT',
        'COPY',
        'LOAD',
        'INTO',
        'LOCK',
        'UNLOCK',
    ];

    /**
     * Validates the supplied SQL statement.
     *
     * @param string $sql SQL statement to validate.
     */
    public function validate(string $sql): void {
        $sql = trim($sql);
        if ($sql === '') {
            throw new invalid_parameter_exception('SQL cannot be empty.');
        }

        $withoutstrings = $this->strip_literals_and_comments($sql);
        if ($this->contains_statement_separator($withoutstrings)) {
            throw new invalid_parameter_exception('Multiple SQL statements are not allowed.');
        }

        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($withoutstrings)));
        if (!preg_match('/^(SELECT\b|WITH\b)/', $normalized)) {
            throw new invalid_parameter_exception('Only SELECT or WITH ... SELECT queries are allowed.');
        }
        if (str_starts_with($normalized, 'WITH ') && !preg_match('/\bSELECT\b/', $normalized)) {
            throw new invalid_parameter_exception('A WITH query must end in a SELECT operation.');
        }

        foreach (self::FORBIDDEN as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (preg_match($pattern, $withoutstrings)) {
                throw new invalid_parameter_exception("Forbidden SQL operation: {$keyword}");
            }
        }

        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $withoutstrings)) {
            throw new invalid_parameter_exception('File-writing SQL is not allowed.');
        }
        if (preg_match('/\bFOR\s+UPDATE\b/i', $withoutstrings)) {
            throw new invalid_parameter_exception('SELECT FOR UPDATE is not allowed in history templates.');
        }

        // Some functions mutate server or session state even when called from SELECT.
        $statechanging = '/\b('
            . 'pg_(try_)?advisory_(xact_)?lock|pg_advisory_unlock|nextval|setval|'
            . 'get_lock|release_lock|sleep|benchmark|lo_(import|export)|dblink_exec'
            . ')\s*\(/i';
        if (preg_match($statechanging, $withoutstrings)) {
            throw new invalid_parameter_exception('State-changing or blocking SQL functions are not allowed.');
        }
        if (str_contains($withoutstrings, ':=')) {
            throw new invalid_parameter_exception('SQL variable assignment is not allowed.');
        }
    }

    /**
     * Replaces SQL literals and comments with spaces before keyword inspection.
     *
     * @param string $sql SQL statement to normalize.
     * @return string SQL with literals and comments removed.
     */
    private function strip_literals_and_comments(string $sql): string {
        $out = '';
        $length = strlen($sql);
        $state = 'normal';

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($state === 'single') {
                if ($ch === "'" && $next === "'") {
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $state = 'normal';
                }
                $out .= ' ';
                continue;
            }

            if ($state === 'double') {
                if ($ch === '"' && $next === '"') {
                    $i++;
                    continue;
                }
                if ($ch === '"') {
                    $state = 'normal';
                }
                $out .= ' ';
                continue;
            }

            if ($state === 'linecomment') {
                if ($ch === "\n") {
                    $state = 'normal';
                    $out .= "\n";
                } else {
                    $out .= ' ';
                }
                continue;
            }

            if ($state === 'blockcomment') {
                if ($ch === '*' && $next === '/') {
                    $state = 'normal';
                    $out .= '  ';
                    $i++;
                } else {
                    $out .= ' ';
                }
                continue;
            }

            if ($ch === "'") {
                $state = 'single';
                $out .= ' ';
            } else if ($ch === '"') {
                $state = 'double';
                $out .= ' ';
            } else if ($ch === '-' && $next === '-') {
                $state = 'linecomment';
                $out .= '  ';
                $i++;
            } else if ($ch === '/' && $next === '*') {
                $state = 'blockcomment';
                $out .= '  ';
                $i++;
            } else {
                $out .= $ch;
            }
        }

        if ($state === 'single' || $state === 'double' || $state === 'blockcomment') {
            throw new invalid_parameter_exception('Unterminated SQL literal or comment.');
        }

        return $out;
    }

    /**
     * Reports whether SQL contains more than one statement separator.
     *
     * @param string $sql SQL statement to inspect.
     * @return bool True when an additional statement separator exists.
     */
    private function contains_statement_separator(string $sql): bool {
        $trimmed = rtrim($sql);
        if (str_ends_with($trimmed, ';')) {
            $trimmed = rtrim(substr($trimmed, 0, -1));
        }

        return str_contains($trimmed, ';');
    }
}
