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
 * course_form_test.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert;

use advanced_testcase;
use local_kopere_recert\form\course_form;

/**
 * Regression tests for conditional course configuration fields.
 *
 * @covers \local_kopere_recert\form\course_form
 */
final class course_form_test extends advanced_testcase {
    /**
     * The issue #6 submission must persist without the four hidden properties.
     */
    public function test_save_completion_without_hidden_fields(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $form = new course_form(null, ['courseid' => $course->id]);
        $data = [
            'enabled' => 1,
            'triggertype' => 'completion',
            'intervaldays' => 1,
            'referencecmid' => 0,
            'resetcompetencies' => 0,
            'selfenabled' => 0,
        ];

        $this->assertSame([], $form->validation($data, []));
        $record = course_form::build_record($course->id, (object)$data);
        $record->timecreated = time();
        $id = $DB->insert_record('local_kopere_recert_course', $record);
        $saved = $DB->get_record('local_kopere_recert_course', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('completion', $saved->triggertype);
        $this->assertSame('enrolment', $saved->selfreferencetype);
        $this->assertSame(0, (int)$saved->fixedmonth);
        $this->assertSame(0, (int)$saved->fixedday);
        $this->assertSame(0, (int)$saved->selfafterdays);
    }

    /**
     * Hidden values must survive mode switches and disabling the configuration.
     */
    public function test_preserve_inactive_configuration(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $form = new course_form(null, ['courseid' => $course->id]);
        $initial = [
            'enabled' => 1,
            'triggertype' => 'fixeddate',
            'fixedmonth' => 9,
            'fixedday' => 15,
            'selfenabled' => 1,
            'selfreferencetype' => 'completion',
            'selfafterdays' => 30,
            'resetcompetencies' => 1,
        ];
        $this->assertSame([], $form->validation($initial, []));
        $current = course_form::build_record($course->id, (object)$initial);
        $current->timecreated = time();
        $current->id = $DB->insert_record('local_kopere_recert_course', $current);

        $changed = [
            'enabled' => 1,
            'triggertype' => 'enrolment',
            'intervaldays' => 365,
            'selfenabled' => 0,
            'resetcompetencies' => 1,
        ];
        $this->assertSame([], $form->validation($changed, []));
        $updated = course_form::build_record($course->id, (object)$changed, $current);
        $updated->id = $current->id;
        $DB->update_record('local_kopere_recert_course', $updated);
        $this->assertSame(9, $updated->fixedmonth);
        $this->assertSame(15, $updated->fixedday);
        $this->assertSame('completion', $updated->selfreferencetype);
        $this->assertSame(30, $updated->selfafterdays);

        $this->assertSame([], $form->validation(['enabled' => 0], []));
        $disabled = course_form::build_record($course->id, (object)['enabled' => 0], $updated);
        $disabled->id = $current->id;
        $DB->update_record('local_kopere_recert_course', $disabled);
        $saved = $DB->get_record('local_kopere_recert_course', ['id' => $current->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$saved->enabled);
        $this->assertSame('enrolment', $saved->triggertype);
        $this->assertSame(365, (int)$saved->intervaldays);
        $this->assertSame(1, (int)$saved->resetcompetencies);
        $this->assertSame(1, $DB->count_records('local_kopere_recert_course', ['courseid' => $course->id]));
    }

    /**
     * Active fields must still be validated instead of silently defaulted.
     */
    public function test_active_fields_are_required(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $form = new course_form(null, ['courseid' => $course->id]);

        $errors = $form->validation(['enabled' => 1, 'triggertype' => 'completion'], []);
        $this->assertArrayHasKey('intervaldays', $errors);

        $errors = $form->validation(['enabled' => 1, 'triggertype' => 'fixeddate'], []);
        $this->assertArrayHasKey('fixedmonth', $errors);
        $this->assertArrayHasKey('fixedday', $errors);

        $errors = $form->validation(['enabled' => 1, 'triggertype' => 'certificate', 'intervaldays' => 1], []);
        $this->assertArrayHasKey('referencecmid', $errors);

        $data = ['enabled' => 1, 'triggertype' => 'completion', 'intervaldays' => 1, 'selfenabled' => 1];
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('selfreferencetype', $errors);
        $this->assertArrayHasKey('selfafterdays', $errors);

        $errors = $form->validation($data + ['selfreferencetype' => 'certificate', 'selfafterdays' => -1], []);
        $this->assertArrayHasKey('referencecmid', $errors);
        $this->assertArrayHasKey('selfafterdays', $errors);
    }
}
