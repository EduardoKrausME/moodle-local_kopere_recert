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

namespace local_kopere_recertification\files;

use coding_exception;
use context;
use context_course;
use dml_exception;
use file_exception;
use file_storage;
use invalid_parameter_exception;
use local_kopere_recertification\task\file_descriptor;
use local_kopere_recertification\task\task_context;
use stored_file_creation_exception;

/**
 * Copies historical files through the Moodle File API and records source metadata.
 */
class manager {
    /**
     * copy_descriptors
     *
     * @param array $descriptors
     * @param task_context $context
     * @param int $historyid
     * @return int
     * @throws dml_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function copy_descriptors(array $descriptors, task_context $context, int $historyid): int {
        global $DB;

        $fs = get_file_storage();
        $targetcontext = context_course::instance($context->courseid);
        $count = 0;

        foreach ($descriptors as $descriptor) {
            if (!$descriptor instanceof file_descriptor) {
                throw new coding_exception('Invalid file descriptor.');
            }
            if (trim($descriptor->component) === '' || trim($descriptor->filearea) === '') {
                throw new invalid_parameter_exception('A file descriptor requires component and filearea.');
            }
            context::instance_by_id($descriptor->contextid, MUST_EXIST);

            $files = $this->resolve_files($fs, $descriptor);
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }

                if ($context->simulation) {
                    // Validate the exact source selection but do not create a file-pool side effect.
                    $count++;
                    continue;
                }

                $filerecord = [
                    'contextid' => $targetcontext->id,
                    'component' => 'local_kopere_recertification',
                    'filearea' => 'historyfiles',
                    'itemid' => $historyid,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ];
                $newfile = $fs->create_file_from_storedfile($filerecord, $file);

                $DB->insert_record('local_recert_file', (object)[
                    'cycleid' => $context->cycleid,
                    'historyid' => $historyid,
                    'userid' => $context->userid,
                    'courseid' => $context->courseid,
                    'sourcecomponent' => $descriptor->component,
                    'sourcefilearea' => $descriptor->filearea,
                    'sourceitemid' => $descriptor->itemid,
                    'sourcecontextid' => $descriptor->contextid,
                    'filepath' => $newfile->get_filepath(),
                    'filename' => $newfile->get_filename(),
                    'mimetype' => $newfile->get_mimetype(),
                    'filesize' => $newfile->get_filesize(),
                    'contenthash' => $newfile->get_contenthash(),
                    'timecreated' => time(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolves the physical Moodle files described by a task file descriptor.
     *
     * @param file_storage $fs Fs.
     * @param file_descriptor $descriptor File descriptor.
     * @return array Structured result data.
     * @throws coding_exception
     */
    private function resolve_files(file_storage $fs, file_descriptor $descriptor): array {
        if ($descriptor->filename !== null) {
            $file = $fs->get_file(
                $descriptor->contextid,
                $descriptor->component,
                $descriptor->filearea,
                $descriptor->itemid,
                $descriptor->filepath,
                $descriptor->filename
            );
            return $file ? [$file] : [];
        }

        return $fs->get_area_files(
            $descriptor->contextid,
            $descriptor->component,
            $descriptor->filearea,
            $descriptor->itemid,
            'filepath, filename',
            false
        );
    }
}
