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
 * cycle_and_dates_test.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification;

use advanced_testcase;
use local_kopere_recertification\course\date_calculator;
use local_kopere_recertification\cycle\manager;

/**
 * Tests kopere_recertification behavior for cycle and dates.
 */
final class cycle_and_dates_test extends advanced_testcase {
    /**
     * Tests that cycle numbering.
     */
    public function test_cycle_numbering(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $manager = new manager();
        $one = $manager->create($course->id, $user->id, 'One', 'One', 'api', null);
        $two = $manager->create($course->id, $user->id, 'Two', 'Two', 'api', null);
        $this->assertSame(1, (int)$one->number);
        $this->assertSame(2, (int)$two->number);
    }

    /**
     * Tests that calculation from enrolment.
     */
    public function test_calculation_from_enrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $enrolled = (int)$DB->get_field_sql('SELECT MIN(ue.timecreated) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE e.courseid=:c AND ue.userid=:u', ['c'=>$course->id,'u'=>$user->id]);
        $config = (object)['courseid'=>$course->id,'triggertype'=>'enrolment','intervaldays'=>365];
        $dates = (new date_calculator())->calculate($config, $user->id);
        $this->assertSame($enrolled + 365 * DAYSECS, $dates['dueat']);
    }

    /**
     * Tests that calculation from completion.
     */
    public function test_calculation_from_completion(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $completed = time() - DAYSECS;
        $DB->insert_record('course_completions', (object)['userid'=>$user->id,'course'=>$course->id,'timeenrolled'=>1,'timestarted'=>1,'timecompleted'=>$completed,'reaggregate'=>0]);
        $config = (object)['courseid'=>$course->id,'triggertype'=>'completion','intervaldays'=>30];
        $dates = (new date_calculator())->calculate($config, $user->id);
        $this->assertSame($completed + 30 * DAYSECS, $dates['dueat']);
    }

    /**
     * Tests that fixed date uses next occurrence.
     */
    public function test_fixed_date_uses_next_occurrence(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $config = (object)['courseid'=>$course->id,'triggertype'=>'fixeddate','intervaldays'=>0,'fixedmonth'=>1,'fixedday'=>10];
        $now = make_timestamp(2026, 2, 1, 12, 0, 0, 99, false);
        $dates = (new date_calculator())->calculate($config, $user->id, $now);
        $this->assertSame('2027-01-10', userdate($dates['dueat'], '%Y-%m-%d', 99, false));
    }

    /**
     * Tests that calculation after last kopere_recertification.
     */
    public function test_calculation_after_last_kopere_recertification(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $cycles = new manager();
        $cycle = $cycles->create($course->id, $user->id, 'Old', 'Old', 'api', null);
        $completed = time() - 10 * DAYSECS;
        $cycles->mark_completed($cycle->id, $completed);
        $config = (object)['courseid'=>$course->id,'triggertype'=>'lastkopere_recertification','intervaldays'=>365];
        $dates = (new date_calculator())->calculate($config, $user->id);
        $this->assertSame($completed + 365 * DAYSECS, $dates['dueat']);
    }
}
