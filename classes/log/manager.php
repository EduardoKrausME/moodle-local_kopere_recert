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
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\log;

/**
 * Writes sanitized kopere_recert execution log entries.
 */
class manager {
    /**
     * Sanitizes a message before it is written to the execution log.
     *
     * @param string $message Log or notification message.
     * @return string Resulting string value.
     */
    public static function sanitize_message(string $message): string {
        $message = preg_replace('/\b(Bearer)\s+[A-Za-z0-9._~+\/-]+=*/i', '$1 [redacted]', $message);
        $message = preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key|authorization)\s*([:=])\s*([^\s,;]+)/i',
            '$1$2[redacted]',
            $message
        );
        return mb_substr((string)$message, 0, 65535);
    }

    /**
     * Adds a structured execution log entry.
     *
     * @param int $cycleid Recertification cycle ID.
     * @param ?int $taskid Global task configuration ID.
     * @param string $action Execution action name.
     * @param ?string $component Moodle component name.
     * @param ?int $cmid Course module ID.
     * @param string $status Cycle or execution status.
     * @param string $message Log or notification message.
     * @param ?float $duration Execution duration.
     */
    public function add(
        int $cycleid,
        ?int $taskid,
        string $action,
        ?string $component,
        ?int $cmid,
        string $status,
        string $message = '',
        ?float $duration = null,
    ): void {
        global $DB;
        $DB->insert_record('local_kopere_recert_log', (object)[
            'cycleid' => $cycleid,
            'taskid' => $taskid,
            'action' => $action,
            'component' => $component,
            'cmid' => $cmid,
            'status' => $status,
            'message' => self::sanitize_message($message),
            'duration' => $duration,
            'timecreated' => time(),
        ]);
    }
}
