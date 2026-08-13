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
 * task_plugin_interface.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

/**
 * Contract implemented by specialized kopere_recertification task subplugins.
 */
interface task_plugin_interface {
    /**
     * Returns the Moodle component represented by this task provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string;

    /**
     * Returns the localized name of this provider.
     *
     * @return string Localized provider name.
     */
    public static function get_name(): string;

    /**
     * Reports whether the provider can create historical snapshots.
     *
     * @return bool True when history creation is supported.
     */
    public static function supports_history(): bool;

    /**
     * Reports whether the provider can preserve files.
     *
     * @return bool True when file preservation is supported.
     */
    public static function supports_files(): bool;

    /**
     * Reports whether the provider can clean user data.
     *
     * @return bool True when cleanup is supported.
     */
    public static function supports_cleanup(): bool;

    /**
     * Reports whether this provider represents a system-level task.
     *
     * @return bool True for a system-level task.
     */
    public static function is_system_task(): bool;

    /**
     * Returns the ordering value used for system-level execution.
     *
     * @return int System execution order.
     */
    public static function get_system_order(): int;

    /**
     * Builds the historical snapshot for the current kopere_recertification context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result;

    /**
     * Returns file descriptors to be copied by the parent.
     *
     * Subplugins must not insert local_recert_file rows directly.
     *
     * @return file_descriptor[]
     */
    public function get_files(task_context $context, int $historyid): array;

    /**
     * Cleans the current user data after history and files have been safely preserved.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result;

    /**
     * Optional non-destructive inspection. The real simulator still executes the real methods in a rollback transaction.
     */
    public function describe(task_context $context): array;
}
