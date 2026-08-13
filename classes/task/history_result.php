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
 * history_result.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

/**
 * Structured historical data returned by task providers to the parent plugin.
 */
class history_result {
    /**
     * Creates a new history result instance.
     *
     * @param ?int $completedat Completion timestamp.
     * @param ?float $grade Grade.
     * @param string $html Html.
     * @param array $data Structured data.
     * @param array $files Files.
     * @param array $messages Messages.
     */
    public function __construct(
        public ?int $completedat = null,
        public ?float $grade = null,
        public string $html = '',
        public array $data = [],
        public array $files = [],
        public array $messages = [],
    ) {
    }
}
