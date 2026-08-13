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
 * file_descriptor.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

/**
 * Describes a Moodle File API source that must be preserved in history.
 */
class file_descriptor {
    /**
     * Creates a new file descriptor instance.
     *
     * @param int $contextid Context ID.
     * @param string $component Moodle component name.
     * @param string $filearea Filearea.
     * @param int $itemid Itemid.
     * @param string $filepath File path.
     * @param ?string $filename File name.
     */
    public function __construct(
        public readonly int $contextid,
        public readonly string $component,
        public readonly string $filearea,
        public readonly int $itemid,
        public readonly string $filepath = '/',
        public readonly ?string $filename = null,
    ) {
    }
}
