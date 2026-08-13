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
 * settings.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_kopere_recertification_cat',
        get_string('pluginname', 'local_kopere_recertification')
    ));
    $ADMIN->add('local_kopere_recertification_cat', new admin_externalpage(
        'local_kopere_recertification_tasks',
        get_string('tasks', 'local_kopere_recertification'),
        new moodle_url('/local/kopere_recertification/tasks.php'),
        'local/kopere_recertification:managetasks'
    ));
    $settings = new admin_settingpage(
        'local_kopere_recertification_settings',
        get_string('settings', 'local_kopere_recertification')
    );
    $ADMIN->add('local_kopere_recertification_cat', $settings);
    $settings->add(new admin_setting_configcheckbox(
        'local_kopere_recertification/showkopereemailrecommendation',
        get_string('showkopereemailrecommendation', 'local_kopere_recertification'),
        get_string('showkopereemailrecommendation_desc', 'local_kopere_recertification'),
        1
    ));

    $messageplugins = core_component::get_plugin_list('message');
    if (!isset($messageplugins['kopereemail']) && get_config('local_kopere_recertification', 'showkopereemailrecommendation')) {
        $url = new moodle_url('https://eduardokraus.com/marketplace-plugins/plugin/message_kopereemail');
        $settings->add(new admin_setting_heading(
            'local_kopere_recertification/kopereemailrecommendation',
            '',
            html_writer::link($url, get_string('kopereemailrecommendation', 'local_kopere_recertification'), [
                'target' => '_blank',
                'rel' => 'noopener',
            ])
        ));
    }
}
