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
 * plan_item.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

use stdClass;

/**
 * Immutable description of one activity or system item in an execution plan.
 */
class plan_item {
    /**
     * Creates a new plan item instance.
     *
     * @param string $component Moodle component name.
     * @param string $origin Origin.
     * @param ?int $taskid Global task configuration ID.
     * @param ?int $cmid Course module ID.
     * @param ?int $instanceid Activity instance ID.
     * @param int $contextid Context ID.
     * @param string $activityname Activityname.
     * @param string $activitytype Activitytype.
     * @param int $sortorder Sortorder.
     * @param bool $historyenabled Historyenabled.
     * @param bool $filesenabled Filesenabled.
     * @param bool $cleanupenabled Cleanupenabled.
     * @param ?task_plugin_interface $plugin Subplugin provider instance.
     * @param ?stdClass $genericconfig Genericconfig.
     */
    public function __construct(
        public readonly string $component,
        public readonly string $origin,
        public readonly ?int $taskid,
        public readonly ?int                   $cmid,
        public readonly ?int                   $instanceid,
        public readonly int                    $contextid,
        public readonly string                 $activityname,
        public readonly string                 $activitytype,
        public readonly int                    $sortorder,
        public readonly bool                   $historyenabled,
        public readonly bool                   $filesenabled,
        public readonly bool                   $cleanupenabled,
        public readonly ?task_plugin_interface $plugin = null,
        public readonly ?stdClass              $genericconfig = null,
    ) {
    }
}
