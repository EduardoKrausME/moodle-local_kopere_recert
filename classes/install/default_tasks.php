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
 * default_tasks.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\install;

use core_component;

/**
 * Creates the default global recertification task configuration.
 *
 * @package local_kopere_recert
 */
final class default_tasks {
    /** Bundled task providers that should be configured automatically. */
    private const PROVIDERS = [
        'activitycompletion',
        'competency',
        'coursecompletion',
        'enrolment',
        'forum',
        'grades',
        'quiz',
        'supervideo',
        'certificatebeautiful',
        'childcourse',
        'videoprogress',
    ];

    /** Standard Moodle activity modules that should be registered automatically when installed. */
    private const MOODLE_STANDARD_MODULES = [
        'assign',
        'bigbluebuttonbn',
        'book',
        'choice',
        'data',
        'feedback',
        'folder',
        'forum',
        'glossary',
        'h5pactivity',
        'imscp',
        'label',
        'lesson',
        'lti',
        'page',
        'qbank',
        'quiz',
        'resource',
        'scorm',
        'subsection',
        'url',
        'wiki',
        'workshop',
    ];

    /** Content-only standard modules that should exist in the configuration but start disabled. */
    private const INACTIVE_STANDARD_MODULES = [
        'book',
        'folder',
        'imscp',
        'label',
        'page',
        'qbank',
        'resource',
        'subsection',
        'url',
    ];

    /**
     * Creates every missing bundled and installed activity task using safe defaults.
     *
     * Existing rows are never changed.
     */
    public static function create(): void {
        global $CFG, $DB;

        $installedmods = core_component::get_plugin_list('mod');
        $now = time();

        foreach (self::PROVIDERS as $name) {
            $classname = '\\recerttask_' . $name . '\\task';
            $classfile = $CFG->dirroot . '/local/kopere_recert/recerttask/' . $name . '/classes/task.php';

            if (!class_exists($classname, false) && is_readable($classfile)) {
                require_once($classfile);
            }
            if (!class_exists($classname)) {
                continue;
            }

            $component = $classname::get_component();
            if (str_starts_with($component, 'mod_')) {
                $modname = substr($component, 4);
                if (!isset($installedmods[$modname])) {
                    continue;
                }
            }

            if ($DB->record_exists('local_kopere_recert_task', ['component' => $component])) {
                continue;
            }

            $DB->insert_record('local_kopere_recert_task', (object)[
                'component' => $component,
                'origin' => 'subplugin',
                'enabled' => 1,
                'historyenabled' => $classname::supports_history() ? 1 : 0,
                'filesenabled' => $classname::supports_files() ? 1 : 0,
                'cleanupenabled' => $classname::supports_cleanup() ? 1 : 0,
                'historytemplate' => '',
                'fileconfigjson' => '',
                'cleanupconfigjson' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        foreach ($installedmods as $modname => $path) {
            $component = 'mod_' . $modname;

            if ($DB->record_exists('local_kopere_recert_task', ['component' => $component])) {
                continue;
            }

            $isstandard = in_array($modname, self::MOODLE_STANDARD_MODULES, true);
            $isinactive = in_array($modname, self::INACTIVE_STANDARD_MODULES, true);
            $enabled = $isstandard && !$isinactive;

            $DB->insert_record('local_kopere_recert_task', (object)[
                'component' => $component,
                'origin' => 'generic',
                'enabled' => $enabled ? 1 : 0,
                'historyenabled' => $enabled ? 1 : 0,
                'filesenabled' => 0,
                'cleanupenabled' => 0,
                'historytemplate' => '',
                'fileconfigjson' => '',
                'cleanupconfigjson' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }
}
