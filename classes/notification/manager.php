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
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\notification;

use core\message\message;
use core_user;
use dml_write_exception;
use moodle_url;
use stdClass;
use Throwable;

/**
 * Builds, sends, and deduplicates Moodle kopere_recert notifications.
 */
class manager {
    /**
     * Sends one Moodle message for a kopere_recert event.
     *
     * @param int $cycleid Recertification cycle ID.
     * @param string $eventtype Notification event type.
     * @param ?int $noticeid Notice configuration ID.
     * @return bool Boolean result.
     */
    public function send_event(int $cycleid, string $eventtype, ?int $noticeid = null): bool {
        global $DB;

        $cycle = $DB->get_record('local_kopere_recert_cycle', ['id' => $cycleid], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $cycle->userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cycle->courseid], '*', MUST_EXIST);

        $notice = null;
        if ($noticeid) {
            $notice = $DB->get_record('local_kopere_recert_notice', ['id' => $noticeid, 'courseid' => $cycle->courseid], '*', MUST_EXIST);
            if ($DB->record_exists('local_kopere_recert_notice_log', ['cycleid' => $cycleid, 'noticeid' => $noticeid])) {
                return false;
            }
        }

        $provider = $this->provider_for_event($eventtype);
        $subject = $notice && trim((string)$notice->subject) !== ''
            ? $notice->subject
            : get_string('notification_subject_' . $eventtype, 'local_kopere_recert', $course->fullname);
        $body = $notice && trim((string)$notice->body) !== ''
            ? $notice->body
            : get_string('notification_body_' . $eventtype, 'local_kopere_recert', (object)[
                'course' => $course->fullname,
                'cycle' => $cycle->name,
            ]);

        $body = $this->replace_placeholders($body, $cycle, $user, $course);
        $subject = $this->replace_placeholders($subject, $cycle, $user, $course);

        $message = new message();
        $message->component = 'local_kopere_recert';
        $message->name = $provider;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($body);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = format_text($body, FORMAT_HTML, ['trusted' => true]);
        $message->smallmessage = shorten_text(strip_tags($body), 200);
        $message->notification = 1;
        $message->contexturl = (new moodle_url('/local/kopere_recert/history.php', [
            'courseid' => $cycle->courseid,
            'userid' => $cycle->userid,
            'cycleid' => $cycleid,
        ]))->out(false);
        $message->contexturlname = get_string('history', 'local_kopere_recert');

        $reservednotice = false;
        if ($notice) {
            try {
                $DB->insert_record('local_kopere_recert_notice_log', (object)[
                    'cycleid' => $cycleid,
                    'noticeid' => $notice->id,
                    'userid' => $cycle->userid,
                    'sentat' => 0,
                ]);
                $reservednotice = true;
            } catch (dml_write_exception $e) {
                // Another worker reserved or sent this notice first.
                return false;
            }
        }

        try {
            $messageid = message_send($message);
            if (!$messageid) {
                if ($reservednotice) {
                    $DB->delete_records('local_kopere_recert_notice_log', [
                        'cycleid' => $cycleid,
                        'noticeid' => $notice->id,
                    ]);
                }
                return false;
            }
            if ($reservednotice) {
                $log = $DB->get_record('local_kopere_recert_notice_log', [
                    'cycleid' => $cycleid,
                    'noticeid' => $notice->id,
                ], '*', MUST_EXIST);
                $log->sentat = time();
                $DB->update_record('local_kopere_recert_notice_log', $log);
            }
            return true;
        } catch (Throwable $e) {
            if ($reservednotice) {
                $DB->delete_records('local_kopere_recert_notice_log', [
                    'cycleid' => $cycleid,
                    'noticeid' => $notice->id,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Sends all configured notices associated with a kopere_recert event.
     *
     * @param int $cycleid Recertification cycle ID.
     * @param string $eventtype Notification event type.
     * @param bool $fallback Fallback.
     */
    public function send_configured_event(int $cycleid, string $eventtype, bool $fallback = true): void {
        global $DB;

        $cycle = $DB->get_record('local_kopere_recert_cycle', ['id' => $cycleid], '*', MUST_EXIST);
        $notices = $DB->get_records('local_kopere_recert_notice', [
            'courseid' => $cycle->courseid,
            'eventtype' => $eventtype,
            'enabled' => 1,
        ], 'id ASC');

        if (!$notices && $fallback) {
            $this->send_event($cycleid, $eventtype);
            return;
        }
        foreach ($notices as $notice) {
            $this->send_event($cycleid, $eventtype, (int)$notice->id);
        }
    }

    /**
     * Sends due warning notices that have not already been delivered.
     *
     * @param stdClass $cycle Cycle.
     * @param int $now Reference timestamp; zero uses the current time.
     */
    public function send_due_notices(stdClass $cycle, int $now): void {
        global $DB;

        $notices = $DB->get_records('local_kopere_recert_notice', [
            'courseid' => $cycle->courseid,
            'enabled' => 1,
        ], 'offsetdays DESC, id ASC');

        foreach ($notices as $notice) {
            if (!in_array($notice->eventtype, ['expiration_warning', 'kopere_recert_available', 'kopere_recert_due', 'kopere_recert_expired'], true)) {
                continue;
            }

            $sendat = (int)$cycle->dueat - ((int)$notice->offsetdays * DAYSECS);
            if ($sendat > $now) {
                continue;
            }
            if ($DB->record_exists('local_kopere_recert_notice_log', ['cycleid' => $cycle->id, 'noticeid' => $notice->id])) {
                continue;
            }

            $this->send_event((int)$cycle->id, (string)$notice->eventtype, (int)$notice->id);
        }
    }

    /**
     * Maps an internal notification event to its Moodle message provider.
     *
     * @param string $eventtype Notification event type.
     * @return string Moodle message provider name.
     */
    private function provider_for_event(string $eventtype): string {
        return match ($eventtype) {
            'kopere_recert_available' => 'kopere_recert_available',
            'expiration_warning', 'kopere_recert_due' => 'kopere_recert_warning',
            'kopere_recert_created', 'kopere_recert_started' => 'kopere_recert_started',
            'kopere_recert_expired' => 'kopere_recert_expired',
            'kopere_recert_completed' => 'kopere_recert_completed',
            default => 'kopere_recert_warning',
        };
    }

    /**
     * Replaces supported notification placeholders with cycle and user values.
     *
     * @param string $text Text.
     * @param stdClass $cycle Cycle.
     * @param stdClass $user User.
     * @param stdClass $course Course record.
     * @return string Resulting string value.
     */
    private function replace_placeholders(string $text, stdClass $cycle, stdClass $user, stdClass $course): string {
        return strtr($text, [
            '{{user.fullname}}' => fullname($user),
            '{{course.fullname}}' => format_string($course->fullname),
            '{{cycle.name}}' => $cycle->name,
            '{{cycle.number}}' => (string)$cycle->number,
            '{{cycle.dueat}}' => $cycle->dueat ? userdate($cycle->dueat) : '',
            '{{cycle.availableat}}' => $cycle->availableat ? userdate($cycle->availableat) : '',
        ]);
    }
}
