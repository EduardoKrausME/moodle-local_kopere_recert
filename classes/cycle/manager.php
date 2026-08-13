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

namespace local_kopere_recertification\cycle;

use invalid_parameter_exception;
use stdClass;
use Throwable;

/**
 * Applies kopere_recertification cycle lifecycle transitions and business rules.
 */
class manager {
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_MANUAL_USER = 'manual_user';
    public const SOURCE_MANUAL_ADMIN = 'manual_admin';
    public const SOURCE_BULK = 'bulk';
    public const SOURCE_API = 'api';

    /**
     * Creates a new manager instance.
     *
     * @param repository $repository Repository.
     */
    public function __construct(private readonly repository $repository = new repository()) {
    }

    /**
     * Creates a new kopere_recertification cycle.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param string $name Human-readable name.
     * @param string $reason Human-readable kopere_recertification reason.
     * @param string $source Recertification source.
     * @param ?int $createdby User ID that created the cycle.
     * @param ?int $previouscompletedat Previous completion timestamp.
     * @param ?int $availableat Recertification availability timestamp.
     * @param ?int $dueat Recertification due timestamp.
     * @param string $status Cycle or execution status.
     * @return stdClass Result of the operation.
     */
    public function create(
        int $courseid,
        int $userid,
        string $name,
        string $reason,
        string $source,
        ?int $createdby,
        ?int $previouscompletedat = null,
        ?int $availableat = null,
        ?int $dueat = null,
        string $status = self::STATUS_PENDING,
    ): stdClass {
        global $DB;

        $sources = [
            self::SOURCE_AUTOMATIC, self::SOURCE_MANUAL_USER, self::SOURCE_MANUAL_ADMIN,
            self::SOURCE_BULK, self::SOURCE_API,
        ];
        $statuses = [
            self::STATUS_SCHEDULED, self::STATUS_PENDING, self::STATUS_PROCESSING,
            self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED,
        ];
        if (!in_array($source, $sources, true) || !in_array($status, $statuses, true)) {
            throw new invalid_parameter_exception('Invalid kopere_recertification cycle source or status.');
        }
        if (!$DB->record_exists('course', ['id' => $courseid]) || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new invalid_parameter_exception('Invalid kopere_recertification course or user.');
        }
        if ($createdby !== null && !$DB->record_exists('user', ['id' => $createdby, 'deleted' => 0])) {
            throw new invalid_parameter_exception('Invalid cycle creator.');
        }

        if ($previouscompletedat === null) {
            $currentcompletion = $DB->get_field('course_completions', 'timecompleted', [
                'course' => $courseid,
                'userid' => $userid,
            ]);
            $previouscompletedat = $currentcompletion ? (int)$currentcompletion : null;
        }

        $now = time();
        $record = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'number' => $this->repository->get_next_number($courseid, $userid),
            'name' => $name,
            'reason' => $reason,
            'source' => $source,
            'status' => $status,
            'previouscompletedat' => $previouscompletedat,
            'availableat' => $availableat,
            'dueat' => $dueat,
            'startedat' => null,
            'completedat' => null,
            'createdby' => $createdby,
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $this->repository->insert($record);
        return $record;
    }


    /**
     * Marks a cycle as pending kopere_recertification.
     *
     * @param int $cycleid Recertification cycle ID.
     */
    public function mark_pending(int $cycleid): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_PENDING;
        $this->repository->update($cycle);
    }

    /**
     * Marks a cycle as currently being processed.
     *
     * @param int $cycleid Recertification cycle ID.
     */
    public function mark_processing(int $cycleid): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_PROCESSING;
        $cycle->startedat = time();
        $cycle->errorcode = null;
        $cycle->errormessage = null;
        $this->repository->update($cycle);
    }

    /**
     * Marks a cycle as active after the reset transaction succeeds.
     *
     * @param int $cycleid Recertification cycle ID.
     */
    public function mark_active(int $cycleid): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_ACTIVE;
        $this->repository->update($cycle);
    }

    /**
     * Records a cycle failure after the destructive transaction has rolled back.
     *
     * @param int $cycleid Recertification cycle ID.
     * @param Throwable $e E.
     */
    public function mark_failed(int $cycleid, Throwable $e): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_FAILED;
        $cycle->errorcode = substr(get_class($e), 0, 100);
        $cycle->errormessage = \local_kopere_recertification\log\manager::sanitize_message($e->getMessage());
        $this->repository->update($cycle);
    }

    /**
     * Marks an active cycle as successfully completed.
     *
     * @param int $cycleid Recertification cycle ID.
     * @param int $completedat Completion timestamp.
     */
    public function mark_completed(int $cycleid, int $completedat): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_COMPLETED;
        $cycle->completedat = $completedat;
        $this->repository->update($cycle);
    }

    /**
     * Returns active.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return ?stdClass Result of the operation.
     */
    public function get_active(int $courseid, int $userid): ?stdClass {
        return $this->repository->get_active($courseid, $userid);
    }
}
