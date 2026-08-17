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
 * Recertification cycle manager.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\cycle;

use invalid_parameter_exception;
use stdClass;
use Throwable;

/**
 * Applies recertification cycle lifecycle transitions and business rules.
 */
class manager {
    /** Cycle has been scheduled for a future date. */
    public const STATUS_SCHEDULED = 'scheduled';

    /** Cycle is ready to be processed. */
    public const STATUS_PENDING = 'pending';

    /** Cycle is currently being processed. */
    public const STATUS_PROCESSING = 'processing';

    /** Cycle reset succeeded and the new certification period is active. */
    public const STATUS_ACTIVE = 'active';

    /** Cycle was completed by a new course completion. */
    public const STATUS_COMPLETED = 'completed';

    /** Cycle processing failed. */
    public const STATUS_FAILED = 'failed';

    /** Cycle was cancelled before processing. */
    public const STATUS_CANCELLED = 'cancelled';

    /** Cycle was created by the automatic scheduler. */
    public const SOURCE_AUTOMATIC = 'automatic';

    /** Cycle was requested by the learner. */
    public const SOURCE_MANUAL_USER = 'manual_user';

    /** Cycle was requested manually by an administrator. */
    public const SOURCE_MANUAL_ADMIN = 'manual_admin';

    /** Cycle was created by the bulk operation. */
    public const SOURCE_BULK = 'bulk';

    /** Cycle was requested through an API integration. */
    public const SOURCE_API = 'api';

    /** @var repository Cycle persistence repository. */
    private readonly repository $repository;

    /**
     * Creates a new manager instance.
     *
     * @param repository|null $repository Cycle persistence repository.
     */
    public function __construct(?repository $repository = null) {
        $this->repository = $repository ?? new repository();
    }

    /**
     * Creates a new recertification cycle.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param string $name Human-readable name.
     * @param string $reason Human-readable recertification reason.
     * @param string $source Recertification source.
     * @param int|null $createdby User ID that created the cycle.
     * @param int|null $previouscompletedat Previous completion timestamp.
     * @param int|null $availableat Recertification availability timestamp.
     * @param int|null $dueat Recertification due timestamp.
     * @param string $status Cycle or execution status.
     * @return stdClass Created cycle.
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
            self::SOURCE_AUTOMATIC,
            self::SOURCE_MANUAL_USER,
            self::SOURCE_MANUAL_ADMIN,
            self::SOURCE_BULK,
            self::SOURCE_API,
        ];
        $statuses = [
            self::STATUS_SCHEDULED,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
        if (!in_array($source, $sources, true) || !in_array($status, $statuses, true)) {
            throw new invalid_parameter_exception('Invalid kopere_recert cycle source or status.');
        }
        if (
            !$DB->record_exists('course', ['id' => $courseid])
            || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])
        ) {
            throw new invalid_parameter_exception('Invalid kopere_recert course or user.');
        }
        if ($createdby !== null && !$DB->record_exists('user', ['id' => $createdby, 'deleted' => 0])) {
            throw new invalid_parameter_exception('Invalid cycle creator.');
        }

        if ($previouscompletedat === null) {
            $currentcompletion = $DB->get_field('course_completions', 'timecompleted', [
                'course' => $courseid,
                'userid' => $userid,
            ]);
            $previouscompletedat = $currentcompletion ? (int) $currentcompletion : null;
        }

        $now = time();
        $record = (object) [
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
     * Marks a cycle as pending recertification.
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
     * @param Throwable $e Failure exception.
     */
    public function mark_failed(int $cycleid, Throwable $e): void {
        $cycle = $this->repository->get($cycleid);
        $cycle->status = self::STATUS_FAILED;
        $cycle->errorcode = substr(get_class($e), 0, 100);
        $cycle->errormessage = \local_kopere_recert\log\manager::sanitize_message($e->getMessage());
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
     * Returns the active cycle for a user and course.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return stdClass|null Active cycle, or null when no cycle is active.
     */
    public function get_active(int $courseid, int $userid): ?stdClass {
        return $this->repository->get_active($courseid, $userid);
    }
}
