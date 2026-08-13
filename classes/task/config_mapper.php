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
 * config_mapper.php
 *
 * @package   local_kopere_recertification
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recertification\task;

use invalid_parameter_exception;
use local_kopere_recertification\cleanup\condition_validator;
use local_kopere_recertification\cleanup\table_discovery;
use stdClass;

/**
 * Maps dynamic task form values to safe persisted configuration.
 */
class config_mapper {
    /**
     * Converts a stored task configuration into a form record.
     *
     * @param stdClass $record Database record.
     * @return stdClass Result of the operation.
     */
    public function to_form_record(stdClass $record): stdClass {
        $data = clone $record;

        $files = json_decode((string)($record->fileconfigjson ?? ''), true);
        if (is_array($files)) {
            $files = isset($files[0]) ? $files : [$files];
            foreach (array_slice($files, 0, 3) as $index => $file) {
                $i = $index + 1;
                $data->{"filecomponent_{$i}"} = $file['component'] ?? $record->component;
                $data->{"filearea_{$i}"} = $file['filearea'] ?? '';
                $data->{"fileitemid_{$i}"} = $file['itemid'] ?? ':userid';
                $data->{"filecontextid_{$i}"} = $file['contextid'] ?? ':contextid';
                $data->{"filepath_{$i}"} = $file['filepath'] ?? '/';
                $data->{"filename_{$i}"} = $file['filename'] ?? '';
            }
        }

        $cleanups = json_decode((string)($record->cleanupconfigjson ?? ''), true);
        if (is_array($cleanups)) {
            $cleanups = isset($cleanups[0]) ? $cleanups : [$cleanups];
            foreach (array_slice($cleanups, 0, 3) as $index => $cleanup) {
                $i = $index + 1;
                $data->{"cleanuptable_{$i}"} = $cleanup['table'] ?? '';
                $extra = [];
                foreach (($cleanup['conditions'] ?? []) as $condition) {
                    $column = (string)($condition['column'] ?? '');
                    $placeholder = (string)($condition['placeholder'] ?? '');
                    if (($column === 'userid' || $column === 'user_id') && ltrim($placeholder, ':') === 'userid') {
                        $data->{"cleanupusercolumn_{$i}"} = $column;
                    } else {
                        $extra[] = $condition;
                    }
                }
                foreach (array_slice($extra, 0, 3) as $jindex => $condition) {
                    $j = $jindex + 1;
                    $data->{"cleanupcolumn_{$i}_{$j}"} = $condition['column'] ?? '';
                    $data->{"cleanupplaceholder_{$i}_{$j}"} = ':' . ltrim((string)($condition['placeholder'] ?? ''), ':');
                }
            }
        }
        return $data;
    }

    /**
     * Converts submitted file-copy fields into the stored configuration structure.
     *
     * @param stdClass $data Structured data.
     * @return string Resulting string value.
     */
    public function files_from_form(stdClass $data): string {
        $rows = [];
        for ($i = 1; $i <= 3; $i++) {
            $filearea = trim((string)($data->{"filearea_{$i}"} ?? ''));
            if ($filearea === '') {
                continue;
            }
            $component = trim((string)($data->{"filecomponent_{$i}"} ?? $data->component));
            $itemid = trim((string)($data->{"fileitemid_{$i}"} ?? ':userid'));
            $contextid = trim((string)($data->{"filecontextid_{$i}"} ?? ':contextid'));
            $this->validate_numeric_or_placeholder($itemid);
            $this->validate_numeric_or_placeholder($contextid);
            // stored_file has no userid column. A generic file rule is therefore only safe when
            // the File API itemid itself is the user id. More complex ownership belongs in a subplugin.
            if ($itemid !== ':userid') {
                throw new invalid_parameter_exception(
                    'Generic file copy requires itemid = :userid. Use a specialized subplugin for other ownership models.'
                );
            }
            if ($component === '') {
                throw new invalid_parameter_exception('Source component is required for file copy.');
            }
            if ($component !== (string)$data->component) {
                throw new invalid_parameter_exception(
                    'Generic file copy can only read the selected task component. Use a specialized subplugin for cross-component file ownership.'
                );
            }
            $rows[] = [
                'component' => $component,
                'filearea' => $filearea,
                'itemid' => $this->normalise_numeric($itemid),
                'contextid' => $this->normalise_numeric($contextid),
                'userid' => ':userid',
                'filepath' => (string)($data->{"filepath_{$i}"} ?? '/'),
                'filename' => trim((string)($data->{"filename_{$i}"} ?? '')),
            ];
        }
        if (!empty($data->filesenabled) && !$rows) {
            throw new invalid_parameter_exception('At least one file definition is required when file copy is enabled.');
        }
        return $rows ? json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * Converts submitted cleanup fields into the stored safe condition structure.
     *
     * @param stdClass $data Structured data.
     * @return string Resulting string value.
     */
    public function cleanup_from_form(stdClass $data): string {
        $discovery = new table_discovery();
        $validator = new condition_validator();
        $rows = [];
        $dummy = [
            'userid' => 1, 'courseid' => 1, 'cmid' => 1, 'instanceid' => 1,
            'contextid' => 1, 'cycleid' => 1, 'kopere_recertificationid' => 1,
        ];

        for ($i = 1; $i <= 3; $i++) {
            $table = trim((string)($data->{"cleanuptable_{$i}"} ?? ''));
            if ($table === '') {
                continue;
            }
            $columns = $discovery->assert_allowed((string)$data->component, $table);
            $usercolumn = (string)($data->{"cleanupusercolumn_{$i}"} ?? 'userid');
            if (!in_array($usercolumn, ['userid', 'user_id'], true) || !in_array($usercolumn, $columns, true)) {
                throw new invalid_parameter_exception('The selected user column does not exist in the selected table.');
            }
            $conditions = [[
                'column' => $usercolumn,
                'operator' => '=',
                'placeholder' => ':userid',
            ]];
            for ($j = 1; $j <= 3; $j++) {
                $column = trim((string)($data->{"cleanupcolumn_{$i}_{$j}"} ?? ''));
                if ($column === '') {
                    continue;
                }
                $conditions[] = [
                    'column' => $column,
                    'operator' => '=',
                    'placeholder' => (string)($data->{"cleanupplaceholder_{$i}_{$j}"} ?? ':cmid'),
                ];
            }
            $validator->build($conditions, $columns, $dummy);
            $rows[] = ['table' => $table, 'conditions' => $conditions];
        }

        if (!empty($data->cleanupenabled) && !$rows) {
            throw new invalid_parameter_exception('At least one eligible cleanup table is required when cleanup is enabled.');
        }
        return $rows ? json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * Validates a numeric literal or an allowed execution placeholder.
     *
     * @param string $value Value to validate or transform.
     */
    private function validate_numeric_or_placeholder(string $value): void {
        if (preg_match('/^\d+$/', $value)) {
            return;
        }
        if (!str_starts_with($value, ':')) {
            throw new invalid_parameter_exception('File item/context values must be an integer or an allowed placeholder.');
        }
        $name = substr($value, 1);
        if (!in_array($name, condition_validator::ALLOWED_PLACEHOLDERS, true)) {
            throw new invalid_parameter_exception("Unsupported placeholder: {$value}");
        }
    }

    /**
     * Normalizes a numeric form value for safe storage.
     *
     * @param string $value Value to validate or transform.
     * @return int|string Result of the operation.
     */
    private function normalise_numeric(string $value): int|string {
        return preg_match('/^\d+$/', $value) ? (int)$value : $value;
    }
}
