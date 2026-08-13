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
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\subplugin;

use coding_exception;
use core_plugin_manager;
use local_kopere_recertification\task\task_plugin_interface;

/**
 * Discovers recerttask subplugins and resolves the component each provider represents.
 */
class manager {
    /** @var array<string, task_plugin_interface>|null */
    private ?array $plugins = null;

    /**
     * @return array<string, task_plugin_interface>
     */
    public function get_plugins(): array {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $this->plugins = [];
        $pluginman = core_plugin_manager::instance();
        $plugins = $pluginman->get_plugins_of_type('recerttask');

        foreach ($plugins as $name => $info) {
            if (!$info->rootdir) {
                continue;
            }
            $classname = '\\recerttask_' . $name . '\\task';
            if (!class_exists($classname)) {
                debugging("Missing kopere_recertification task class {$classname}", DEBUG_DEVELOPER);
                continue;
            }
            $instance = new $classname();
            if (!$instance instanceof task_plugin_interface) {
                debugging("{$classname} must implement task_plugin_interface", DEBUG_DEVELOPER);
                continue;
            }
            $component = $instance::get_component();
            if (isset($this->plugins[$component])) {
                throw new coding_exception("Duplicate kopere_recertification component provider: {$component}");
            }
            $this->plugins[$component] = $instance;
        }

        return $this->plugins;
    }

    /**
     * Returns for component.
     *
     * @param string $component Moodle component name.
     * @return ?task_plugin_interface Result of the operation.
     */
    public function get_for_component(string $component): ?task_plugin_interface {
        return $this->get_plugins()[$component] ?? null;
    }

    /**
     * Handles the represents operation.
     *
     * @param string $component Moodle component name.
     * @return bool Boolean result.
     */
    public function represents(string $component): bool {
        return isset($this->get_plugins()[$component]);
    }
}
