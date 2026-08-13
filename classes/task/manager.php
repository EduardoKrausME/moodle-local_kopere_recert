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
 * manager.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\task;

use context_course;
use context_module;
use core_component;
use invalid_parameter_exception;
use local_kopere_recert\subplugin\manager as subplugin_manager;
use moodle_exception;
use stdClass;

/**
 * Discovers configured tasks and builds course execution plans.
 */
class manager {
    /**
     * Creates a new manager instance.
     *
     * @param subplugin_manager $subplugins Subplugins.
     */
    public function __construct(private readonly subplugin_manager $subplugins = new subplugin_manager()) {
    }

    /**
     * Returns global task configurations indexed by component.
     *
     * @return array Structured result data.
     */
    public function get_global_tasks(): array {
        global $DB;
        return $DB->get_records('local_kopere_recert_task', null, 'component ASC');
    }

    /**
     * Generic activity components available for backend validation.
     * Components represented by a subplugin never appear here.
     */
    public function get_available_components(): array {
        $groups = $this->get_available_component_groups();
        return $groups[get_string('installedactivities', 'local_kopere_recert')] ?? [];
    }

    /**
     * Components available for a new global task, grouped for the administration UI.
     */
    public function get_available_component_groups(): array {
        global $DB;

        $configured = array_fill_keys(array_keys($DB->get_records_menu(
            'local_kopere_recert_task',
            null,
            '',
            'component,id'
        )), true);
        $installedmods = core_component::get_plugin_list('mod');
        $plugins = $this->subplugins->get_plugins();

        $activities = [];
        foreach ($installedmods as $name => $path) {
            $component = 'mod_' . $name;
            if (isset($plugins[$component]) || isset($configured[$component])) {
                continue;
            }
            $activities[$component] = get_string('modulename', $component);
        }

        $supported = [];
        $system = [];
        foreach ($plugins as $component => $plugin) {
            if (isset($configured[$component])) {
                continue;
            }
            if (str_starts_with($component, 'mod_') && !isset($installedmods[substr($component, 4)])) {
                continue;
            }
            if ($plugin::is_system_task()) {
                $system[$component] = $plugin::get_name();
            } else {
                $supported[$component] = $plugin::get_name();
            }
        }

        asort($activities);
        asort($supported);
        asort($system);

        $groups = [];
        if ($activities) {
            $groups[get_string('installedactivities', 'local_kopere_recert')] = $activities;
        }
        if ($supported) {
            $groups[get_string('supportedplugins', 'local_kopere_recert')] = $supported;
        }
        if ($system) {
            $groups[get_string('systemcomponents', 'local_kopere_recert')] = $system;
        }
        return $groups;
    }

    /**
     * Validates and saves a global task configuration.
     *
     * @param stdClass $record Database record.
     * @return int Resulting integer value.
     */
    public function save(stdClass $record): int {
        global $DB;

        if (!empty($record->filesenabled) && empty($record->historyenabled)) {
            throw new invalid_parameter_exception('File copy requires history to be enabled because historyid is the archive item identifier.');
        }

        $issubplugin = ($record->origin ?? 'generic') === 'subplugin';
        if ($issubplugin) {
            if (!$this->subplugins->represents($record->component)) {
                throw new moodle_exception('subplugincomponentmissing', 'local_kopere_recert');
            }
        } else if ($this->subplugins->represents($record->component)) {
            throw new moodle_exception('componentrepresentedbysubplugin', 'local_kopere_recert');
        }

        $existing = $DB->get_record('local_kopere_recert_task', ['component' => $record->component]);
        if ($existing && empty($record->id)) {
            $record->id = $existing->id;
        }

        $now = time();
        $record->timemodified = $now;
        if (!empty($record->id)) {
            $DB->update_record('local_kopere_recert_task', $record);
            return (int)$record->id;
        }

        $record->timecreated = $now;
        return (int)$DB->insert_record('local_kopere_recert_task', $record);
    }

    /**
     * Builds the ordered execution plan for a course.
     *
     * @param int $courseid Course ID.
     * @return execution_plan Ordered execution plan.
     */
    public function build_plan(int $courseid): execution_plan {
        global $DB;

        $plan = new execution_plan();
        $modinfo = get_fast_modinfo($courseid);
        $configs = $DB->get_records('local_kopere_recert_task');
        $configbycomponent = [];
        foreach ($configs as $record) {
            $configbycomponent[$record->component] = $record;
        }

        $plugins = $this->subplugins->get_plugins();
        $courseconfig = $DB->get_record('local_kopere_recert_course', ['courseid' => $courseid]);
        $sortorder = 0;

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section || empty($section->sequence)) {
                continue;
            }
            foreach (explode(',', $section->sequence) as $cmidraw) {
                $cmid = (int)$cmidraw;
                if (!$cmid || !isset($modinfo->cms[$cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$cmid];
                if ($cm->deletioninprogress) {
                    continue;
                }

                $component = 'mod_' . $cm->modname;
                $plugin = $plugins[$component] ?? null;
                $config = $configbycomponent[$component] ?? null;

                if ($plugin) {
                    if (!$config || empty($config->enabled) || ($config->origin ?? '') !== 'subplugin') {
                        continue;
                    }
                } else {
                    if (!$config || empty($config->enabled) || ($config->origin ?? 'generic') !== 'generic') {
                        continue;
                    }
                }

                $historyenabled = $plugin
                    ? $plugin::supports_history() && !empty($config->historyenabled)
                    : !empty($config->historyenabled);
                $filesenabled = $plugin
                    ? $plugin::supports_files() && !empty($config->filesenabled)
                    : !empty($config->filesenabled);
                $cleanupenabled = $plugin
                    ? $plugin::supports_cleanup() && !empty($config->cleanupenabled)
                    : !empty($config->cleanupenabled);

                $modulecontext = context_module::instance($cm->id);
                $plan->add_activity(new plan_item(
                    component: $component,
                    origin: $plugin ? 'subplugin' : 'generic',
                    taskid: $config ? (int)$config->id : null,
                    cmid: (int)$cm->id,
                    instanceid: (int)$cm->instance,
                    contextid: $modulecontext->id,
                    activityname: format_string($cm->name, true, ['context' => $modulecontext]),
                    activitytype: $cm->modname,
                    sortorder: ++$sortorder,
                    historyenabled: $historyenabled,
                    filesenabled: $filesenabled,
                    cleanupenabled: $cleanupenabled,
                    plugin: $plugin,
                    genericconfig: $plugin ? null : $config,
                ));
            }
        }

        foreach ($plugins as $component => $plugin) {
            if (!$plugin::is_system_task()) {
                continue;
            }

            $config = $configbycomponent[$component] ?? null;
            if (!$config || empty($config->enabled) || ($config->origin ?? '') !== 'subplugin') {
                continue;
            }

            $plan->add_system(new plan_item(
                component: $component,
                origin: 'subplugin',
                taskid: $config ? (int)$config->id : null,
                cmid: null,
                instanceid: null,
                contextid: context_course::instance($courseid)->id,
                activityname: $plugin::get_name(),
                activitytype: 'system',
                sortorder: 100000 + $plugin::get_system_order(),
                historyenabled: $plugin::supports_history() && !empty($config->historyenabled),
                filesenabled: $plugin::supports_files() && !empty($config->filesenabled),
                cleanupenabled: $plugin::supports_cleanup()
                    && !empty($config->cleanupenabled)
                    && ($component !== 'core_competency' || ($courseconfig && !empty($courseconfig->resetcompetencies))),
                plugin: $plugin,
            ));
        }

        return $plan;
    }
}
