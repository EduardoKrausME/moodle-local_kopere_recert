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
 * scan_recertifications.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

use core\task\scheduled_task;

/**
 * Scheduled task that discovers due kopere_recertifications and queues ad-hoc tasks.
 */
class scan_kopere_recertifications extends scheduled_task {
    /**
     * Returns the localized name of this provider.
     *
     * @return string Localized provider name.
     */
    public function get_name(): string {
        return get_string('task_scan', 'local_kopere_recertification');
    }

    /**
     * Executes this kopere_recertification operation.
     */
    public function execute(): void {
        (new \local_kopere_recertification\scheduler\manager())->scan();
    }
}
