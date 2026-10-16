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
 * Tests for optional recertification MCP providers and safe previews.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert;

use advanced_testcase;
use local_kopere_recert\cycle\manager as cycle_manager;
use local_kopere_recert\mcp\report_service;
use local_kopere_recert\mcp\get_my_history;
use local_kopere_recert\mcp\list_my_cycles;
use local_kopere_recert\mcp\list_course_cycles;
use local_kopere_recert\mcp\queue_recertification;
use local_kopere_recert\mcp\queue_bulk;
use local_kopere_recert\mcp\simulate_recertification;
use moodle_exception;

/**
 * Ensure recertification tools respect user isolation and do not write during previews.
 *
 * @covers \local_kopere_recert\mcp\report_service
 * @covers \local_kopere_recert\mcp\access
 * @covers \local_kopere_recert\mcp\list_my_cycles
 * @covers \local_kopere_recert\mcp\get_my_history
 * @covers \local_kopere_recert\mcp\queue_recertification
 * @covers \local_kopere_recert\mcp\queue_bulk
 * @covers \local_kopere_recert\mcp\simulate_recertification
 */
final class mcp_tools_test extends advanced_testcase {
    /**
     * Create a course and an enrolled student for isolated recertification tests.
     *
     * @return array Course and enrolled student.
     */
    private function fixture(): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$student->id, (int)$course->id);
        return [$course, $student];
    }

    /**
     * Skip optional MCP integration checks when the dependency is not installed.
     *
     * @return void
     */
    private function require_mcp(): void {
        if (!class_exists(\local_mcp\security\authenticated_identity::class)) {
            $this->markTestSkipped("Optional local_mcp is not installed.");
        }
    }

    /**
     * State tokens must reflect every newly created cycle.
     *
     * @return void
     */
    public function test_cycle_state_hash_changes_and_is_enforced(): void {
        global $USER;
        $this->resetAfterTest();
        [$course, $student] = $this->fixture();
        $first = report_service::state_hash((int)$course->id, (int)$student->id);
        $cycle = (new cycle_manager())->create((int)$course->id, (int)$student->id,
            "New cycle", "Audit test", cycle_manager::SOURCE_API, (int)$USER->id);
        $this->assertGreaterThan(0, $cycle->id);
        $this->assertNotSame($first, report_service::state_hash((int)$course->id, (int)$student->id));
        $result = report_service::cycles((int)$course->id, (int)$student->id, 0, 10);
        $this->assertSame(1, $result["total"]);
        $this->assertSame((int)$cycle->id, $result["cycles"][0]["cycleid"]);
        $this->expectException(moodle_exception::class);
        report_service::require_state((int)$course->id, (int)$student->id, $first);
    }

    /**
     * Cycles and archived snapshots are never mixed across learners or courses.
     *
     * @return void
     */
    public function test_history_reads_reject_other_user_and_course(): void {
        global $USER;
        $this->resetAfterTest();
        [$course, $student] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $cycle = (new cycle_manager())->create((int)$course->id, (int)$student->id,
            "New cycle", "Test", cycle_manager::SOURCE_API, (int)$USER->id);
        $result = report_service::history((int)$cycle->id, (int)$student->id,
            (int)$course->id, 0, 10);
        $this->assertSame(0, $result["total"]);
        $this->expectException(moodle_exception::class);
        report_service::history((int)$cycle->id, (int)$other->id,
            (int)$course->id, 0, 10);
    }

    /**
     * A non-owner must not retrieve a private cycle even with a guessed identifier.
     *
     * @return void
     */
    public function test_my_history_denies_foreign_cycle(): void {
        global $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $student] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$other->id, (int)$course->id);
        $cycle = (new cycle_manager())->create((int)$course->id, (int)$student->id,
            "Cycle", "Test", cycle_manager::SOURCE_API, (int)$USER->id);
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$other->id, "oauth", ["read"]
        );
        $this->expectException(moodle_exception::class);
        (new get_my_history())->execute(["cycleid" => (int)$cycle->id], $identity);
    }

    /**
     * No cycle, scheduled task or notification is created while preparing confirmation.
     *
     * @return void
     */
    public function test_queue_preview_is_side_effect_free(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $student] = $this->fixture();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $before = $DB->count_records("local_kopere_recert_cycle");
        $hash = report_service::state_hash((int)$course->id, (int)$student->id);
        $preview = (new queue_recertification())->preview([
            "courseid" => $course->id,
            "userid" => $student->id,
            "reason" => "Renew certificate",
            "expected_state_hash" => $hash,
        ], $identity);
        $this->assertTrue($preview["will_queue_native_adhoc_task"]);
        $this->assertSame($before, $DB->count_records("local_kopere_recert_cycle"));
    }

    /**
     * Batch preview rejects duplicate learners before any queueing happens.
     *
     * @return void
     */
    public function test_bulk_preview_rejects_duplicates(): void {
        global $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $student] = $this->fixture();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $hash = report_service::state_hash((int)$course->id, (int)$student->id);
        $this->expectException(moodle_exception::class);
        (new queue_bulk())->preview([
            "courseid" => $course->id,
            "reason" => "Compliance review",
            "targets" => [
                ["userid" => $student->id, "expected_state_hash" => $hash],
                ["userid" => $student->id, "expected_state_hash" => $hash],
            ],
        ], $identity);
    }

    /**
     * A simulation preview never invokes the rollback-only destructive pipeline.
     *
     * @return void
     */
    public function test_simulation_preview_is_side_effect_free(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $student] = $this->fixture();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $before = $DB->count_records("local_kopere_recert_cycle");
        $preview = (new simulate_recertification())->preview([
            "courseid" => $course->id,
            "userid" => $student->id,
            "reason" => "Preview renewal",
            "expected_state_hash" => report_service::state_hash((int)$course->id, (int)$student->id),
        ], $identity);
        $this->assertTrue($preview["always_rolls_back"]);
        $this->assertSame($before, $DB->count_records("local_kopere_recert_cycle"));
    }

    /**
     * Confirmed single queue reuses the native locking and status management.
     *
     * @return void
     */
    public function test_confirmed_queue_creates_pending_cycle(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $student] = $this->fixture();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $result = (new queue_recertification())->execute([
            "courseid" => $course->id,
            "userid" => $student->id,
            "reason" => "Retraining required",
            "expected_state_hash" => report_service::state_hash((int)$course->id, (int)$student->id),
        ], $identity);
        $this->assertTrue($result["queued"]);
        $cycle = $DB->get_record("local_kopere_recert_cycle", [
            "id" => $result["cycleid"],
        ], "*", MUST_EXIST);
        $this->assertSame(cycle_manager::STATUS_PENDING, $cycle->status);
        $this->assertSame((int)$student->id, (int)$cycle->userid);
    }
}
