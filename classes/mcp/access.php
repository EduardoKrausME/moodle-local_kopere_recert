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
 * Validate Moodle course/user authorization for MCP recertification.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\mcp;

use context_course;
use local_mcp\security\authenticated_identity;
use moodle_exception;
use stdClass;

/**
 * Keep each action under the connected user's native Moodle permissions.
 */
final class access {
    /**
     * Resolve the course context for capability checks.
     *
     * @param int $courseid Course.
     * @return context_course
     */
    public static function context(int $courseid): context_course {
        get_course($courseid);
        return context_course::instance($courseid, MUST_EXIST);
    }

    /**
     * Validate course access for the connected Moodle user.
     *
     * @param int $courseid Course.
     * @param authenticated_identity $identity Caller.
     * @param string $capability Moodle capability.
     * @return context_course
     */
    public static function require_course(
        int $courseid, authenticated_identity $identity, string $capability
    ): context_course {
        $context = self::context($courseid);
        require_capability($capability, $context, $identity->userid);
        return $context;
    }

    /**
     * Resolve a valid enrolled target before recertification or simulation.
     *
     * @param int $courseid Course.
     * @param int $userid User.
     * @param authenticated_identity $identity Caller.
     * @param string $capability Capability.
     * @return context_course
     */
    public static function require_enrolled(
        int $courseid, int $userid, authenticated_identity $identity, string $capability
    ): context_course {
        global $DB;
        $context = self::require_course($courseid, $identity, $capability);
        $DB->get_record("user", ["id" => $userid, "deleted" => 0, "suspended" => 0],
            "id", MUST_EXIST);
        if (!is_enrolled($context, $userid, "", true)) {
            throw new moodle_exception("usernotenrolled", "local_kopere_recert");
        }
        return $context;
    }

    /**
     * Authorize access to the requested learner history.
     *
     * @param int $courseid Course.
     * @param int $userid Target.
     * @param authenticated_identity $identity Connected user.
     * @return void
     */
    public static function require_history(
        int $courseid, int $userid, authenticated_identity $identity
    ): void {
        self::require_course($courseid, $identity, $userid === $identity->userid
            ? "local/kopere_recert:viewownhistory" : "local/kopere_recert:viewallhistory");
    }

    /**
     * Validate manager operations and the native global identity requirement.
     *
     * @param authenticated_identity $identity Identity.
     * @return void
     */
    public static function require_current_identity(authenticated_identity $identity): void {
        global $USER;
        if (empty($USER->id) || (int)$USER->id !== $identity->userid) {
            throw new moodle_exception("mcpidentitymismatch", "local_kopere_recert");
        }
    }

    /**
     * Determine whether a renewal request is self-service or administrative.
     *
     * @param int $courseid Course.
     * @param int $userid Target user.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public static function request_context(int $courseid, int $userid, authenticated_identity $identity): array {
        $self = $userid === $identity->userid;
        $source = $self ? \local_kopere_recert\cycle\manager::SOURCE_MANUAL_USER
            : \local_kopere_recert\cycle\manager::SOURCE_MANUAL_ADMIN;
        self::require_enrolled($courseid, $userid, $identity, $self
            ? "local/kopere_recert:recertifyself" : "local/kopere_recert:recertify");
        self::require_current_identity($identity);
        $availability = $self
            ? (new \local_kopere_recert\recertification\manager())->get_self_availability($courseid, $userid)
            : ["allowed" => true, "availableat" => 0];
        if (!$availability["allowed"]) {
            throw new moodle_exception("selfkopere_recertnotavailable", "local_kopere_recert",
                "", userdate((int)$availability["availableat"]));
        }
        return [$source, $availability];
    }
}
