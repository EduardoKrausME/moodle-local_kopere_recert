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
 * task execution plan item.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

use stdClass;

/**
 * Immutable description of one activity or system item in an execution plan.
 */
class plan_item {
    /** @var string Moodle component name. */
    public readonly string $component;

    /** @var string Plan item origin. */
    public readonly string $origin;

    /** @var int|null Global task configuration ID. */
    public readonly ?int $taskid;

    /** @var int|null Course module ID. */
    public readonly ?int $cmid;

    /** @var int|null Activity instance ID. */
    public readonly ?int $instanceid;

    /** @var int Moodle context ID. */
    public readonly int $contextid;

    /** @var string Activity display name. */
    public readonly string $activityname;

    /** @var string Activity type. */
    public readonly string $activitytype;

    /** @var int Execution sort order. */
    public readonly int $sortorder;

    /** @var bool Whether history creation is enabled. */
    public readonly bool $historyenabled;

    /** @var bool Whether file preservation is enabled. */
    public readonly bool $filesenabled;

    /** @var bool Whether cleanup is enabled. */
    public readonly bool $cleanupenabled;

    /** @var task_plugin_interface|null Optional subplugin provider. */
    public readonly ?task_plugin_interface $plugin;

    /** @var stdClass|null Optional generic task configuration. */
    public readonly ?stdClass $genericconfig;

    /**
     * Creates a new plan item instance.
     *
     * @param string $component Moodle component name.
     * @param string $origin Origin.
     * @param int|null $taskid Global task configuration ID.
     * @param int|null $cmid Course module ID.
     * @param int|null $instanceid Activity instance ID.
     * @param int $contextid Context ID.
     * @param string $activityname Activity name.
     * @param string $activitytype Activity type.
     * @param int $sortorder Sort order.
     * @param bool $historyenabled Whether history creation is enabled.
     * @param bool $filesenabled Whether file preservation is enabled.
     * @param bool $cleanupenabled Whether cleanup is enabled.
     * @param task_plugin_interface|null $plugin Subplugin provider instance.
     * @param stdClass|null $genericconfig Generic task configuration.
     */
    public function __construct(
        string $component,
        string $origin,
        ?int $taskid,
        ?int $cmid,
        ?int $instanceid,
        int $contextid,
        string $activityname,
        string $activitytype,
        int $sortorder,
        bool $historyenabled,
        bool $filesenabled,
        bool $cleanupenabled,
        ?task_plugin_interface $plugin = null,
        ?stdClass $genericconfig = null
    ) {
        $this->component = $component;
        $this->origin = $origin;
        $this->taskid = $taskid;
        $this->cmid = $cmid;
        $this->instanceid = $instanceid;
        $this->contextid = $contextid;
        $this->activityname = $activityname;
        $this->activitytype = $activitytype;
        $this->sortorder = $sortorder;
        $this->historyenabled = $historyenabled;
        $this->filesenabled = $filesenabled;
        $this->cleanupenabled = $cleanupenabled;
        $this->plugin = $plugin;
        $this->genericconfig = $genericconfig;
    }
}
