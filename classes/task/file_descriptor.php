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
 * task file descriptor.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

/**
 * Describes a Moodle File API source that must be preserved in history.
 */
class file_descriptor {
    /** @var int Source context ID. */
    public readonly int $contextid;

    /** @var string Source component. */
    public readonly string $component;

    /** @var string Source file area. */
    public readonly string $filearea;

    /** @var int Source item ID. */
    public readonly int $itemid;

    /** @var string Source file path. */
    public readonly string $filepath;

    /** @var string|null Optional source file name. */
    public readonly ?string $filename;

    /**
     * Creates a new file descriptor instance.
     *
     * @param int $contextid Context ID.
     * @param string $component Moodle component name.
     * @param string $filearea File area.
     * @param int $itemid Item ID.
     * @param string $filepath File path.
     * @param string|null $filename File name.
     */
    public function __construct(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath = '/',
        ?string $filename = null
    ) {
        $this->contextid = $contextid;
        $this->component = $component;
        $this->filearea = $filearea;
        $this->itemid = $itemid;
        $this->filepath = $filepath;
        $this->filename = $filename;
    }
}
