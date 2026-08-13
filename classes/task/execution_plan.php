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
 * execution_plan.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

/**
 * Ordered collection of activity and system tasks for one kopere_recertification run.
 */
class execution_plan {
    /** @var plan_item[] */
    private array $activityitems = [];
    /** @var plan_item[] */
    private array $systemitems = [];

    /**
     * Adds an activity item to the execution plan.
     *
     * @param plan_item $item Execution plan item.
     */
    public function add_activity(plan_item $item): void {
        $this->activityitems[] = $item;
    }

    /**
     * Adds a system-level item to the execution plan.
     *
     * @param plan_item $item Execution plan item.
     */
    public function add_system(plan_item $item): void {
        $this->systemitems[] = $item;
    }

    /** @return plan_item[] */
    public function get_activity_items(): array {
        usort($this->activityitems, fn(plan_item $a, plan_item $b) => $a->sortorder <=> $b->sortorder);
        return $this->activityitems;
    }

    /** @return plan_item[] */
    public function get_system_items(): array {
        usort($this->systemitems, fn(plan_item $a, plan_item $b) => $a->sortorder <=> $b->sortorder);
        return $this->systemitems;
    }

    /** @return plan_item[] */
    public function get_all_items(): array {
        return array_merge($this->get_activity_items(), $this->get_system_items());
    }
}
