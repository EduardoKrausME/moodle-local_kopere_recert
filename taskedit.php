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
 * taskedit.php
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_kopere_recert\form\task_component_form;
use local_kopere_recert\form\task_form;
use local_kopere_recert\task\config_mapper;
use local_kopere_recert\task\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/kopere_recert:managetasks', $context);

$id = optional_param('id', 0, PARAM_INT);
$component = optional_param('component', '', PARAM_COMPONENT);
$record = $id ? $DB->get_record('local_kopere_recert_task', ['id' => $id], '*', MUST_EXIST) : null;
$subplugins = new \local_kopere_recert\subplugin\manager();
$taskmanager = new manager();
$mapper = new config_mapper();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kopere_recert/taskedit.php', array_filter([
    'id' => $id ?: null,
    'component' => $component ?: null,
])));
$PAGE->set_title(get_string('edittask', 'local_kopere_recert'));
$PAGE->set_heading(get_string('edittask', 'local_kopere_recert'));

if (!$record && $component === '') {
    $selectform = new task_component_form(null, [
        'components' => $taskmanager->get_available_component_groups(),
    ]);
    if ($selectform->is_cancelled()) {
        redirect(new moodle_url('/local/kopere_recert/tasks.php'));
    }
    if ($data = $selectform->get_data()) {
        require_sesskey();
        redirect(new moodle_url('/local/kopere_recert/taskedit.php', ['component' => $data->component]));
    }
    echo $OUTPUT->header();
    $selectform->display();
    echo $OUTPUT->footer();
    exit;
}

$effectivecomponent = $record->component ?? $component;
$subplugin = $subplugins->get_for_component($effectivecomponent);
if (!$record && !$subplugin) {
    $available = $taskmanager->get_available_components();
    if (!isset($available[$effectivecomponent])) {
        throw new invalid_parameter_exception('Component is not available for a generic kopere_recert task.');
    }
}
if ($record && ($record->origin ?? 'generic') === 'subplugin' && !$subplugin) {
    throw new moodle_exception('subplugincomponentmissing', 'local_kopere_recert');
}

$form = new task_form(null, [
    'record' => $record,
    'subplugin' => $subplugin,
    'component' => $effectivecomponent,
]);

if ($record) {
    $form->set_data(($record->origin ?? 'generic') === 'generic' ? $mapper->to_form_record($record) : $record);
} else if ($subplugin) {
    $form->set_data((object)[
        'component' => $subplugin::get_component(),
        'enabled' => 1,
        'historyenabled' => $subplugin::supports_history() ? 1 : 0,
        'filesenabled' => $subplugin::supports_files() ? 1 : 0,
        'cleanupenabled' => $subplugin::supports_cleanup() ? 1 : 0,
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/kopere_recert/tasks.php'));
}

if ($data = $form->get_data()) {
    require_sesskey();
    $issubplugin = $subplugins->represents($data->component);
    if (!$issubplugin && !str_starts_with($data->component, 'mod_')) {
        throw new invalid_parameter_exception('Generic tasks must represent an activity component.');
    }

    $save = (object)[
        'id' => $data->id ?: null,
        'component' => $data->component,
        'origin' => $issubplugin ? 'subplugin' : 'generic',
        'enabled' => (int)$data->enabled,
        'historyenabled' => (int)$data->historyenabled,
        'filesenabled' => (int)$data->filesenabled,
        'cleanupenabled' => (int)$data->cleanupenabled,
        'historytemplate' => $issubplugin ? '' : ($data->historytemplate ?? ''),
        'fileconfigjson' => $issubplugin ? '' : $mapper->files_from_form($data),
        'cleanupconfigjson' => $issubplugin ? '' : $mapper->cleanup_from_form($data),
    ];
    if (empty($save->id)) {
        unset($save->id);
    }

    $taskmanager->save($save);
    redirect(new moodle_url('/local/kopere_recert/tasks.php'), get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
