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
 * task_plan_test.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert;

use advanced_testcase;
use invalid_parameter_exception;
use local_kopere_recert\cleanup\table_discovery;
use local_kopere_recert\task\manager;
use moodle_exception;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests global task definitions and execution plan generation.
 */
#[CoversClass(manager::class)]
#[CoversClass(table_discovery::class)]
final class task_plan_test extends advanced_testcase {
    /**
     * Tests that a global component has one task definition.
     */
    public function test_global_component_is_unique(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $base = (object) [
            'component' => 'mod_page',
            'origin' => 'generic',
            'enabled' => 1,
            'historyenabled' => 1,
            'filesenabled' => 0,
            'cleanupenabled' => 0,
            'historytemplate' => '',
            'fileconfigjson' => '',
            'cleanupconfigjson' => '',
        ];

        $manager->save(clone $base);
        $manager->save(clone $base);

        $this->assertSame(1, $DB->count_records('local_kopere_recert_task', ['component' => 'mod_page']));
    }

    /**
     * Tests that a subplugin hides its generic duplicate.
     */
    public function test_subplugin_hides_generic_duplicate(): void {
        $this->resetAfterTest();
        $this->assertArrayNotHasKey('mod_quiz', (new manager())->get_available_components());
        $this->expectException(moodle_exception::class);

        (new manager())->save((object) [
            'component' => 'mod_quiz',
            'origin' => 'generic',
            'enabled' => 1,
            'historyenabled' => 1,
            'filesenabled' => 0,
            'cleanupenabled' => 1,
            'historytemplate' => '',
            'fileconfigjson' => '',
            'cleanupconfigjson' => '',
        ]);
    }

    /**
     * Tests that five module instances become five plan items in course order.
     */
    public function test_five_instances_execute_as_five_plan_items_in_course_order(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $manager = new manager();
        $manager->save((object) [
            'component' => 'mod_page',
            'origin' => 'generic',
            'enabled' => 1,
            'historyenabled' => 1,
            'filesenabled' => 0,
            'cleanupenabled' => 0,
            'historytemplate' => '',
            'fileconfigjson' => '',
            'cleanupconfigjson' => '',
        ]);

        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $page = $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'section' => $i <= 3 ? 1 : 2,
                'name' => 'Page ' . $i,
            ]);
            $ids[] = $page->cmid;
        }

        $items = array_values(array_filter(
            $manager->build_plan($course->id)->get_activity_items(),
            static fn($item) => $item->component === 'mod_page'
        ));

        $this->assertCount(5, $items);
        $this->assertSame($ids, array_map(static fn($item) => $item->cmid, $items));
    }

    /**
     * Tests that a module's primary table cannot be configured for generic cleanup.
     */
    public function test_primary_module_table_is_protected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new table_discovery())->assert_allowed('mod_page', 'page');
    }

    /**
     * Tests that a table without an approved user relationship is rejected.
     */
    public function test_table_without_user_link_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new table_discovery())->assert_allowed('mod_page', 'course');
    }
}
