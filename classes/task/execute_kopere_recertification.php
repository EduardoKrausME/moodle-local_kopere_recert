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
 * execute_kopere_recert.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

use coding_exception;
use core\task\adhoc_task;
use local_kopere_recert\cycle\repository;
use local_kopere_recert\recertification\executor;

/**
 * Ad-hoc task that executes one user/course recertification cycle.
 */
class execute_kopere_recert extends adhoc_task {
    /**
     * Executes the recertification operation represented by the task payload.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data->userid) || empty($data->courseid) || empty($data->cycleid)) {
            throw new coding_exception('Invalid kopere_recert adhoc task payload.');
        }

        $cycle = (new repository())->get((int) $data->cycleid);
        if ((int) $cycle->userid !== (int) $data->userid || (int) $cycle->courseid !== (int) $data->courseid) {
            throw new coding_exception('Adhoc payload does not match cycle.');
        }

        (new executor())->execute((int) $data->cycleid);
    }
}
