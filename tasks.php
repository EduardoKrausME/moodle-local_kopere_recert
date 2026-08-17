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
 * Task configuration page.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_recert\task\manager;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/kopere_recert:managetasks', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kopere_recert/tasks.php'));
$PAGE->set_title(get_string('tasks', 'local_kopere_recert'));
$PAGE->set_heading(get_string('tasks', 'local_kopere_recert'));

$subplugins = (new \local_kopere_recert\subplugin\manager())->get_plugins();
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
        'type' => get_string('subplugin', 'local_kopere_recert'),
        'history' => $config && $plugin::supports_history() && $config->historyenabled
            ? get_string('yes')
            : get_string('no'),
        'files' => $config && $plugin::supports_files() && $config->filesenabled ? get_string('yes') : get_string('no'),
        'cleanup' => $config && $plugin::supports_cleanup() && $config->cleanupenabled
            ? get_string('yes')
            : get_string('no'),
        'origin' => preg_replace('/^\\\\/', '', get_class($plugin)),
        'status' => !$installed
            ? get_string('componentnotinstalled', 'local_kopere_recert')
            : (!$config
                ? get_string('notconfigured', 'local_kopere_recert')
                : ($config->enabled
                    ? get_string('enabled', 'local_kopere_recert')
                    : get_string('disabled', 'local_kopere_recert'))),
        'editurl' => (new moodle_url('/local/kopere_recert/taskedit.php', $config
            ? ['id' => $config->id]
            : ['component' => $component]))->out(false),
    ];
}

foreach ($configs as $task) {
    if (($task->origin ?? 'generic') === 'subplugin' && !isset($subplugins[$task->component])) {
        $rows[] = [
            'component' => $task->component,
            'type' => get_string('subplugin', 'local_kopere_recert'),
            'history' => $task->historyenabled ? get_string('yes') : get_string('no'),
            'files' => $task->filesenabled ? get_string('yes') : get_string('no'),
            'cleanup' => $task->cleanupenabled ? get_string('yes') : get_string('no'),
            'origin' => get_string('subpluginmissing', 'local_kopere_recert'),
            'status' => get_string('componentnotinstalled', 'local_kopere_recert'),
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
        'type' => get_string('generic', 'local_kopere_recert'),
        'history' => $task->historyenabled ? get_string('yes') : get_string('no'),
        'files' => $task->filesenabled ? get_string('yes') : get_string('no'),
        'cleanup' => $task->cleanupenabled ? get_string('yes') : get_string('no'),
        'origin' => get_string('configuration', 'local_kopere_recert'),
        'status' => $installed && $task->enabled
            ? get_string('enabled', 'local_kopere_recert')
            : ($installed
                ? get_string('disabled', 'local_kopere_recert')
                : get_string('componentnotinstalled', 'local_kopere_recert')),
        'editurl' => new moodle_url('/local/kopere_recert/taskedit.php', ['id' => $task->id]),
    ];
}
usort($rows, fn($a, $b) => strcmp($a['component'], $b['component']));

echo $OUTPUT->header();
$courseid = optional_param('courseid', false, PARAM_INT);
if ($courseid) {
    echo $OUTPUT->render_from_template('local_kopere_recert/course_header', [
        'courseurl' => new moodle_url('/local/kopere_recert/course.php', ['courseid' => $courseid]),
        'noticesurl' => new moodle_url('/local/kopere_recert/notices.php', ['courseid' => $courseid]),
        'bulkurl' => new moodle_url('/local/kopere_recert/bulk.php', ['courseid' => $courseid]),
        'historyurl' => new moodle_url('/local/kopere_recert/history.php', ['courseid' => $courseid]),
        'tasksurl' => new moodle_url('/local/kopere_recert/tasks.php', ['courseid' => $courseid]),
        'noticescount' => $DB->count_records('local_kopere_recert_notice', ['courseid' => $courseid]),
        'tasksactive' => true,
    ]);
}

echo $OUTPUT->render_from_template('local_kopere_recert/tasks', [
    'rows' => $rows,
    'newurl' => (new moodle_url('/local/kopere_recert/taskedit.php'))->out(false),
]);
echo $OUTPUT->footer();
