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
 * task history result.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

/**
 * Structured historical data returned by task providers to the parent plugin.
 */
class history_result {
    /** @var int|null Completion timestamp. */
    public ?int $completedat;

    /** @var float|null Grade value. */
    public ?float $grade;

    /** @var string Rendered historical HTML. */
    public string $html;

    /** @var array Structured historical data. */
    public array $data;

    /** @var array File descriptors or file metadata associated with the history. */
    public array $files;

    /** @var array Informational messages produced while creating the history. */
    public array $messages;

    /**
     * Creates a new history result instance.
     *
     * @param int|null $completedat Completion timestamp.
     * @param float|null $grade Grade.
     * @param string $html Rendered historical HTML.
     * @param array $data Structured data.
     * @param array $files Files.
     * @param array $messages Messages.
     */
    public function __construct(
        ?int $completedat = null,
        ?float $grade = null,
        string $html = '',
        array $data = [],
        array $files = [],
        array $messages = []
    ) {
        $this->completedat = $completedat;
        $this->grade = $grade;
        $this->html = $html;
        $this->data = $data;
        $this->files = $files;
        $this->messages = $messages;
    }
}
