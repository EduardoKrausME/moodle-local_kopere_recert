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
 * task cleanup result.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

/**
 * Structured cleanup result returned by task providers.
 */
class cleanup_result {
    /** @var int Number of affected records. */
    public int $affected;

    /** @var array Informational cleanup messages. */
    public array $messages;

    /**
     * Creates a new cleanup result instance.
     *
     * @param int $affected Number of affected records.
     * @param array $messages Informational cleanup messages.
     */
    public function __construct(int $affected = 0, array $messages = []) {
        $this->affected = $affected;
        $this->messages = $messages;
    }
}
