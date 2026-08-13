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
 * task_test.php
 *
 * @package   recerttask_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_forum;

use advanced_testcase;
use local_kopere_recert\task\task_plugin_interface;

/**
 * Tests kopere_recert behavior for forum.
 */
final class task_test extends advanced_testcase {
    /**
     * Tests that provider contract.
     */
    public function test_provider_contract(): void {
        $this->assertSame('mod_forum', task::get_component());
        $this->assertSame(false, task::is_system_task());
        $this->assertInstanceOf(task_plugin_interface::class, new task());
    }
}
