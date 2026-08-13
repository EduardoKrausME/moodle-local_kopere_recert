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
 * sql_engine_test.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification;

use advanced_testcase;
use invalid_parameter_exception;
use local_kopere_recertification\history\sql_engine;
use local_kopere_recertification\history\sql_validator;

/**
 * Tests kopere_recertification behavior for sql engine.
 */
final class sql_engine_test extends advanced_testcase {
    /**
     * Tests that select is allowed.
     */
    public function test_select_is_allowed(): void {
        (new sql_validator())->validate('SELECT id FROM {user} WHERE id = :userid');
        $this->assertTrue(true);
    }

    /**
     * Tests that safe cte is allowed.
     */
    public function test_safe_cte_is_allowed(): void {
        (new sql_validator())->validate('WITH x AS (SELECT id FROM {user}) SELECT id FROM x');
        $this->assertTrue(true);
    }

    /**
     * Tests that delete is forbidden.
     */
    public function test_delete_is_forbidden(): void {
        $this->expectException(invalid_parameter_exception::class);
        (new sql_validator())->validate('DELETE FROM {user}');
    }

    /**
     * Tests that update is forbidden.
     */
    public function test_update_is_forbidden(): void {
        $this->expectException(invalid_parameter_exception::class);
        (new sql_validator())->validate('UPDATE {user} SET firstname = \'x\'');
    }

    /**
     * Tests that multiple queries are forbidden.
     */
    public function test_multiple_queries_are_forbidden(): void {
        $this->expectException(invalid_parameter_exception::class);
        (new sql_validator())->validate('SELECT id FROM {user}; SELECT id FROM {course}');
    }

    /**
     * Tests that sqlecho returns one value.
     */
    public function test_sqlecho_returns_one_value(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $value = (new sql_engine())->echo_value(
            'SELECT id FROM {user} WHERE id = :userid',
            ['userid' => $user->id, 'courseid' => 999]
        );
        $this->assertSame((string)$user->id, $value);
    }

    /**
     * Tests that sqltable escapes values.
     */
    public function test_sqltable_escapes_values(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => '<b>A</b>']);
        $html = (new sql_engine())->table(
            'SELECT id, firstname FROM {user} WHERE id = :userid',
            ['userid' => $user->id]
        );
        $this->assertStringContainsString('&lt;b&gt;A&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>A</b>', $html);
    }

    /**
     * Tests that unknown placeholder is forbidden.
     */
    public function test_unknown_placeholder_is_forbidden(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new sql_engine())->echo_value(
            'SELECT id FROM {user} WHERE id = :evil', ['evil' => 1]
        );
    }

    /**
     * Tests that select into is forbidden.
     */
    public function test_select_into_is_forbidden(): void {
        $this->expectException(invalid_parameter_exception::class);
        (new sql_validator())->validate('SELECT id INTO audit_copy FROM {user}');
    }

    /**
     * Tests that state changing select function is forbidden.
     */
    public function test_state_changing_select_function_is_forbidden(): void {
        $this->expectException(invalid_parameter_exception::class);
        (new sql_validator())->validate("SELECT nextval('some_sequence')");
    }
}
