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
 * tasks.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_recertification\task\manager;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/kopere_recertification:managetasks', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kopere_recertification/tasks.php'));
$PAGE->set_title(get_string('tasks', 'local_kopere_recertification'));
$PAGE->set_heading(get_string('tasks', 'local_kopere_recertification'));

$subplugins = (new \local_kopere_recertification\subplugin\manager())->get_plugins();
$configs = (new manager())->get_global_tasks();
$configbycomponent = [];
foreach ($configs as $config) {
    $configbycomponent[$config->component] = $config;
}

$rows = [];
foreach ($subplugins as $component => $plugin) {
    $config = $configbycomponent[$component] ?? null;
    $installed = true;
    if (str_starts_with($component, 'mod_')) {
        $installed = array_key_exists(substr($component, 4), core_component::get_plugin_list('mod'));
    }

    $rows[] = [
        'component' => $component,
        'type' => get_string('subplugin', 'local_kopere_recertification'),
        'history' => $config && $plugin::supports_history() && $config->historyenabled ? get_string('yes') : get_string('no'),
        'files' => $config && $plugin::supports_files() && $config->filesenabled ? get_string('yes') : get_string('no'),
        'cleanup' => $config && $plugin::supports_cleanup() && $config->cleanupenabled ? get_string('yes') : get_string('no'),
        'origin' => preg_replace('/^\\\\/', '', get_class($plugin)),
        'status' => !$installed
            ? get_string('componentnotinstalled', 'local_kopere_recertification')
            : (!$config ? get_string('notconfigured', 'local_kopere_recertification')
                : ($config->enabled ? get_string('enabled', 'local_kopere_recertification') : get_string('disabled', 'local_kopere_recertification'))),
        'editurl' => (new moodle_url('/local/kopere_recertification/taskedit.php', $config
            ? ['id' => $config->id]
            : ['component' => $component]))->out(false),
    ];
}

foreach ($configs as $task) {
    if (($task->origin ?? 'generic') === 'subplugin' && !isset($subplugins[$task->component])) {
        $rows[] = [
            'component' => $task->component,
            'type' => get_string('subplugin', 'local_kopere_recertification'),
            'history' => $task->historyenabled ? get_string('yes') : get_string('no'),
            'files' => $task->filesenabled ? get_string('yes') : get_string('no'),
            'cleanup' => $task->cleanupenabled ? get_string('yes') : get_string('no'),
            'origin' => get_string('subpluginmissing', 'local_kopere_recertification'),
            'status' => get_string('componentnotinstalled', 'local_kopere_recertification'),
            'editurl' => '',
        ];
        continue;
    }
    if (($task->origin ?? 'generic') !== 'generic') {
        continue;
    }
    $installed = true;
    if (str_starts_with($task->component, 'mod_')) {
        $installed = array_key_exists(substr($task->component, 4), core_component::get_plugin_list('mod'));
    }
    $rows[] = [
        'component' => $task->component,
        'type' => get_string('generic', 'local_kopere_recertification'),
        'history' => $task->historyenabled ? get_string('yes') : get_string('no'),
        'files' => $task->filesenabled ? get_string('yes') : get_string('no'),
        'cleanup' => $task->cleanupenabled ? get_string('yes') : get_string('no'),
        'origin' => get_string('configuration', 'local_kopere_recertification'),
        'status' => $installed && $task->enabled
            ? get_string('enabled', 'local_kopere_recertification')
            : ($installed ? get_string('disabled', 'local_kopere_recertification') : get_string('componentnotinstalled', 'local_kopere_recertification')),
        'editurl' => (new moodle_url('/local/kopere_recertification/taskedit.php', ['id' => $task->id]))->out(false),
    ];
}
usort($rows, fn($a, $b) => strcmp($a['component'], $b['component']));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_kopere_recertification/tasks', [
    'rows' => $rows,
    'newurl' => (new moodle_url('/local/kopere_recertification/taskedit.php'))->out(false),
]);
echo $OUTPUT->footer();
