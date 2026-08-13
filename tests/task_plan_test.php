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
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification;

use advanced_testcase;
use invalid_parameter_exception;
use local_kopere_recertification\cleanup\table_discovery;
use local_kopere_recertification\task\manager;
use moodle_exception;

/**
 * Tests kopere_recertification behavior for task plan.
 */
final class task_plan_test extends advanced_testcase {
    /**
     * Tests that global component is unique.
     */
    public function test_global_component_is_unique(): void {
        global $DB;
        $this->resetAfterTest();
        $manager = new manager();
        $base = (object)['component'=>'mod_page','origin'=>'generic','enabled'=>1,'historyenabled'=>1,'filesenabled'=>0,'cleanupenabled'=>0,'historytemplate'=>'','fileconfigjson'=>'','cleanupconfigjson'=>''];
        $manager->save(clone $base);
        $manager->save(clone $base);
        $this->assertSame(1, $DB->count_records('local_recert_task', ['component'=>'mod_page']));
    }

    /**
     * Tests that subplugin hides generic duplicate.
     */
    public function test_subplugin_hides_generic_duplicate(): void {
        $this->resetAfterTest();
        $this->assertArrayNotHasKey('mod_quiz', (new manager())->get_available_components());
        $this->expectException(moodle_exception::class);
        (new manager())->save((object)[
            'component'=>'mod_quiz','origin'=>'generic','enabled'=>1,'historyenabled'=>1,'filesenabled'=>0,'cleanupenabled'=>1,
            'historytemplate'=>'','fileconfigjson'=>'','cleanupconfigjson'=>'',
        ]);
    }

    /**
     * Tests that five instances execute as five plan items in course order.
     */
    public function test_five_instances_execute_as_five_plan_items_in_course_order(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections'=>2]);
        $manager = new manager();
        $manager->save((object)[
            'component'=>'mod_page','origin'=>'generic','enabled'=>1,'historyenabled'=>1,'filesenabled'=>0,'cleanupenabled'=>0,
            'historytemplate'=>'','fileconfigjson'=>'','cleanupconfigjson'=>'',
        ]);
        $ids = [];
        for ($i=1; $i<=5; $i++) {
            $page = $this->getDataGenerator()->create_module('page', ['course'=>$course->id,'section'=>$i <= 3 ? 1 : 2,'name'=>'Page '.$i]);
            $ids[] = $page->cmid;
        }
        $items = array_values(array_filter($manager->build_plan($course->id)->get_activity_items(), fn($item) => $item->component === 'mod_page'));
        $this->assertCount(5, $items);
        $this->assertSame($ids, array_map(fn($item) => $item->cmid, $items));
    }

    /**
     * Tests that primary module table is protected.
     */
    public function test_primary_module_table_is_protected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new table_discovery())->assert_allowed('mod_page', 'page');
    }

    /**
     * Tests that table without user link is rejected.
     */
    public function test_table_without_user_link_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new table_discovery())->assert_allowed('mod_page', 'course');
    }
}
