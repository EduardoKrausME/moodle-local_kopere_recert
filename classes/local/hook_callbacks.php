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
 * hook_callbacks.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\local;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_kopere_recert\status\manager;
use moodle_url;

/**
 * Hook callbacks used to surface logical kopere_recert status in course pages.
 */
class hook_callbacks {
    /**
     * Adds the logical kopere_recert status notice to course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook Hook.
     */
    public static function before_standard_top_of_body_html_generation(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $USER;

        if (during_initial_install() || !isloggedin() || isguestuser() || empty($PAGE->course->id) || $PAGE->course->id == SITEID) {
            return;
        }
        if (!get_config('local_kopere_recert', 'version')) {
            return;
        }

        $courseid = (int)$PAGE->course->id;
        $cycle = (new manager())->get_cycle($courseid, (int)$USER->id);
        if (!$cycle) {
            return;
        }

        $output = $hook->renderer->render_from_template('local_kopere_recert/status_banner', [
            'message' => get_string('kopere_recertstatusmessage', 'local_kopere_recert'),
            'historyurl' => (new moodle_url('/local/kopere_recert/history.php', [
                'courseid' => $courseid,
                'userid' => $USER->id,
                'cycleid' => $cycle->id,
            ]))->out(false),
            'historylabel' => get_string('viewhistory', 'local_kopere_recert'),
        ]);
        $hook->add_html($output);
    }
}
