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
 * transaction_and_notifications_test.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification;

use advanced_testcase;
use core\event\course_completed;
use core\lock\lock_config;
use local_kopere_recertification\cycle\manager;
use local_kopere_recertification\kopere_recertification\simulator;
use moodle_exception;
use Throwable;

/**
 * Tests kopere_recertification behavior for transaction and notifications.
 */
final class transaction_and_notifications_test extends advanced_testcase {
    /**
     * Creates a generic page task fixture used by transaction tests.
     *
     * @param int $courseid Course ID.
     * @param string $template Mustache template source.
     * @param bool $files Files.
     * @param bool $cleanup Cleanup.
     * @return array Structured result data.
     */
    private function make_page_task(int $courseid, string $template = '', bool $files = false, bool $cleanup = false): array {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course'=>$courseid,'name'=>'History page']);
        $cm = get_coursemodule_from_id('page', $page->cmid, $courseid, false, MUST_EXIST);
        $DB->insert_record('local_recert_task', (object)[
            'component'=>'mod_page','origin'=>'generic','enabled'=>1,'historyenabled'=>1,'filesenabled'=>$files ? 1 : 0,'cleanupenabled'=>$cleanup ? 1 : 0,
            'historytemplate'=>$template,
            'fileconfigjson'=>$files ? json_encode(['component'=>'mod_page','filearea'=>'intro','itemid'=>':instanceid','contextid'=>999999,'userid'=>':userid']) : '',
            'cleanupconfigjson'=>$cleanup ? json_encode(['table'=>'page','conditions'=>[['column'=>'id','operator'=>'=','placeholder'=>':userid']]]) : '',
            'timecreated'=>time(),'timemodified'=>time(),
        ]);
        return [$page, $cm];
    }

    /**
     * Tests that history failure rolls back everything.
     */
    public function test_history_failure_rolls_back_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $this->make_page_task($course->id, '{{#sqlecho}}DELETE FROM {user}{{/sqlecho}}');
        try {
            (new simulator())->simulate($course->id,$user->id,'Test','Test','api',null);
            $this->fail('Expected history failure.');
        } catch (Throwable $e) {
            $this->assertSame(0, $DB->count_records('local_recert_cycle', ['courseid'=>$course->id,'userid'=>$user->id]));
            $this->assertSame(0, $DB->count_records('local_recert_history', ['courseid'=>$course->id,'userid'=>$user->id]));
        }
    }

    /**
     * Tests that file failure rolls back everything.
     */
    public function test_file_failure_rolls_back_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $this->make_page_task($course->id, '', true, false);
        try {
            (new simulator())->simulate($course->id,$user->id,'Test','Test','api',null);
            $this->fail('Expected file failure.');
        } catch (Throwable $e) {
            $this->assertSame(0, $DB->count_records('local_recert_cycle', ['courseid'=>$course->id,'userid'=>$user->id]));
        }
    }

    /**
     * Tests that cleanup failure rolls back everything.
     */
    public function test_cleanup_failure_rolls_back_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $this->make_page_task($course->id, '', false, true);
        try {
            (new simulator())->simulate($course->id,$user->id,'Test','Test','api',null);
            $this->fail('Expected cleanup failure.');
        } catch (Throwable $e) {
            $this->assertSame(0, $DB->count_records('local_recert_cycle', ['courseid'=>$course->id,'userid'=>$user->id]));
        }
    }

    /**
     * Tests that simulation always rolls back.
     */
    public function test_simulation_always_rolls_back(): void {
        global $DB;
        $this->resetAfterTest();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $this->make_page_task($course->id);
        $report=(new simulator())->simulate($course->id,$user->id,'Test','Test','api',null);
        $this->assertTrue($report['rolledback']);
        $this->assertSame(0, $DB->count_records('local_recert_cycle', ['courseid'=>$course->id,'userid'=>$user->id]));
        $this->assertSame(0, $DB->count_records('local_recert_history', ['courseid'=>$course->id,'userid'=>$user->id]));
    }

    /**
     * Tests that lock prevents duplicate execution.
     */
    public function test_lock_prevents_duplicate_execution(): void {
        $this->resetAfterTest();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $factory= lock_config::get_lock_factory('local_kopere_recertification');
        $lock=$factory->get_lock("local_kopere_recertification:{$course->id}:{$user->id}", 1);
        $this->assertNotFalse($lock);
        try {
            $this->expectException(moodle_exception::class);
            (new simulator())->simulate($course->id,$user->id,'Test','Test','api',null);
        } finally {
            $lock->release();
        }
    }

    /**
     * Tests that multiple notices do not duplicate.
     */
    public function test_multiple_notices_do_not_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        $sink=$this->redirectMessages();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $cycle=(new manager())->create($course->id,$user->id,'Future','Future','automatic',null,null,time()-DAYSECS,time()+DAYSECS,'scheduled');
        foreach ([30,7] as $offset) {
            $DB->insert_record('local_recert_notice',(object)['courseid'=>$course->id,'eventtype'=>'expiration_warning','offsetdays'=>$offset,'enabled'=>1,'subject'=>'Warning','body'=>'Body','timecreated'=>time(),'timemodified'=>time()]);
        }
        $manager=new \local_kopere_recertification\notification\manager();
        $manager->send_due_notices($cycle,time());
        $manager->send_due_notices($cycle,time());
        $this->assertSame(2,$DB->count_records('local_recert_notice_log',['cycleid'=>$cycle->id]));
        $this->assertCount(2,$sink->get_messages());
        $sink->close();
    }

    /**
     * Tests that new completion finishes active cycle.
     */
    public function test_new_completion_finishes_active_cycle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectMessages();
        $course=$this->getDataGenerator()->create_course(); $user=$this->getDataGenerator()->create_user();
        $cycle=(new manager())->create($course->id,$user->id,'Active','Active','api',null);
        (new manager())->mark_active($cycle->id);
        $completion=(object)['userid'=>$user->id,'course'=>$course->id,'timeenrolled'=>1,'timestarted'=>time()-100,'timecompleted'=>time(),'reaggregate'=>0];
        $completion->id=$DB->insert_record('course_completions',$completion);
        $event= course_completed::create_from_completion($completion);
        observer::course_completed($event);
        $saved=$DB->get_record('local_recert_cycle',['id'=>$cycle->id]);
        $this->assertSame('completed',$saved->status);
        $this->assertGreaterThan(0,(int)$saved->completedat);
    }
}
