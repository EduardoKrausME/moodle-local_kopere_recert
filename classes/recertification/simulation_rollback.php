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
 * simulation_rollback.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\kopere_recertification;

use moodle_exception;

/**
 * Internal exception used to force rollback after a successful simulation.
 */
class simulation_rollback extends moodle_exception {
    private array $report = [];

    /**
     * Creates a new simulation rollback instance.
     *
     * @param array $report Report.
     */
    public function __construct(array $report = []) {
        $this->report = $report;
        parent::__construct('simulationrollback', 'local_kopere_recertification');
    }

    /**
     * Returns the detailed report produced by the latest simulation.
     *
     * @return array Detailed simulation report.
     */
    public function get_report(): array {
        return $this->report;
    }
}
