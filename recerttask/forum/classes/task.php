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
 * Forum recertification task provider.
 *
 * @package   recerttask_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recerttask_forum;

use context_module;
use local_kopere_recert\task\cleanup_result;
use local_kopere_recert\task\file_descriptor;
use local_kopere_recert\task\history_result;
use local_kopere_recert\task\task_context;
use local_kopere_recert\task\task_plugin_interface;
use moodle_exception;

/**
 * Specialized kopere_recert task provider for forums.
 */
final class task implements task_plugin_interface {
    /**
     * Returns the Moodle component handled by this provider.
     *
     * @return string Moodle component name.
     */
    public static function get_component(): string {
        return 'mod_forum';
    }

    /**
     * Returns the localized provider name.
     *
     * @return string Localized provider name.
     */
    public static function get_name(): string {
        return get_string('pluginname', 'recerttask_forum');
    }

    /**
     * Checks whether history creation is supported.
     *
     * @return bool True when history creation is supported.
     */
    public static function supports_history(): bool {
        return true;
    }

    /**
     * Checks whether file preservation is supported.
     *
     * @return bool True when file preservation is supported.
     */
    public static function supports_files(): bool {
        return true;
    }

    /**
     * Checks whether cleanup is supported.
     *
     * @return bool True when cleanup is supported.
     */
    public static function supports_cleanup(): bool {
        return true;
    }

    /**
     * Checks whether this is a system-level task.
     *
     * @return bool True for a system-level task.
     */
    public static function is_system_task(): bool {
        return false;
    }

    /**
     * Returns the system execution order.
     *
     * @return int System execution order.
     */
    public static function get_system_order(): int {
        return 0;
    }

    /**
     * Builds the historical snapshot for the current kopere_recert context.
     *
     * @param task_context $context Execution context.
     * @return history_result Structured history result.
     */
    public function create_history(task_context $context): history_result {
        global $DB, $OUTPUT;

        $forum = $DB->get_record('forum', ['id' => $context->instanceid], '*', MUST_EXIST);
        $sql = "SELECT p.id, p.discussion, p.parent, p.subject, p.message, p.created, p.modified,
                       d.name AS discussionname
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid
                   AND p.userid = :userid
              ORDER BY d.id, p.created, p.id";
        $posts = $DB->get_records_sql($sql, ['forumid' => $forum->id, 'userid' => $context->userid]);

        $discussionids = [];
        $rows = [];
        foreach ($posts as $post) {
            $discussionids[$post->discussion] = true;
            $rows[] = [
                'discussionname' => format_string($post->discussionname),
                'subject' => format_string($post->subject),
                'created' => userdate($post->created),
                'message' => format_text($post->message, FORMAT_HTML, [
                    'context' => context_module::instance($context->cmid),
                    'filter' => false,
                ]),
            ];
        }

        $html = $OUTPUT->render_from_template('recerttask_forum/history', [
            'forumname' => format_string($forum->name),
            'discussioncount' => count($discussionids),
            'postcount' => count($rows),
            'posts' => $rows,
        ]);

        return new history_result(
            html: $html,
            data: [
                'discussions' => count($discussionids),
                'posts' => count($rows),
                'postids' => array_map('intval', array_keys($posts)),
            ],
            messages: [get_string('archivedcount', 'recerttask_forum', count($rows))]
        );
    }

    /**
     * Returns file descriptors that must be copied into historical storage.
     *
     * @param task_context $context Execution context.
     * @param int $historyid History record ID.
     * @return array File descriptors to preserve.
     */
    public function get_files(task_context $context, int $historyid): array {
        global $DB;

        $sql = "SELECT p.id
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid
                   AND p.userid = :userid";
        $postids = $DB->get_fieldset_sql($sql, [
            'forumid' => $context->instanceid,
            'userid' => $context->userid,
        ]);

        $result = [];
        foreach ($postids as $postid) {
            $result[] = new file_descriptor($context->contextid, 'mod_forum', 'attachment', (int)$postid);
            $result[] = new file_descriptor($context->contextid, 'mod_forum', 'post', (int)$postid);
        }
        return $result;
    }

    /**
     * Cleans the current user data after history and files have been safely preserved.
     *
     * @param task_context $context Execution context.
     * @return cleanup_result Structured cleanup result.
     */
    public function cleanup(task_context $context): cleanup_result {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $course = get_course($context->courseid);
        $cm = get_coursemodule_from_id('forum', $context->cmid, $context->courseid, false, MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $context->instanceid], '*', MUST_EXIST);

        $sql = "SELECT p.*
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid
                   AND p.userid = :userid
              ORDER BY p.id DESC";
        $posts = $DB->get_records_sql($sql, ['forumid' => $forum->id, 'userid' => $context->userid]);

        $count = 0;

        // Discussions started by this user can only be removed if every post in them belongs to the same user.
        // This avoids deleting another participant's contribution through forum_delete_discussion().
        foreach ($posts as $postid => $post) {
            if ((int)$post->parent !== 0 || !$DB->record_exists('forum_posts', ['id' => $postid])) {
                continue;
            }
            $otherposts = $DB->count_records_select(
                'forum_posts',
                'discussion = :discussion AND userid <> :userid',
                ['discussion' => $post->discussion, 'userid' => $context->userid]
            );
            if ($otherposts > 0) {
                throw new moodle_exception(
                    'forumdiscussionhasotherusers',
                    'local_kopere_recert',
                    '',
                    (object)['discussionid' => $post->discussion, 'count' => $otherposts]
                );
            }

            $ownedcount = $DB->count_records('forum_posts', [
                'discussion' => $post->discussion,
                'userid' => $context->userid,
            ]);
            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
            if (!forum_delete_discussion($discussion, false, $course, $cm, $forum)) {
                throw new moodle_exception('forumcleanupfailed', 'local_kopere_recert');
            }
            $count += $ownedcount;
        }

        // For replies inside somebody else's discussion, remove leaves first. We never recursively delete
        // children because a child may belong to another user. If no safe leaf remains, abort and rollback.
        while (true) {
            $remaining = $DB->get_records_sql($sql, [
                'forumid' => $forum->id,
                'userid' => $context->userid,
            ]);
            if (!$remaining) {
                break;
            }

            $progress = false;
            foreach ($remaining as $post) {
                if ((int)$post->parent === 0) {
                    // A remaining root means another user's content prevented safe discussion deletion above.
                    continue;
                }
                if ($DB->record_exists('forum_posts', ['parent' => $post->id])) {
                    continue;
                }
                if (!forum_delete_post($post, false, $course, $cm, $forum, true)) {
                    throw new moodle_exception('forumcleanupfailed', 'local_kopere_recert');
                }
                $count++;
                $progress = true;
            }

            if (!$progress) {
                throw new moodle_exception('forumreplyhaschildren', 'local_kopere_recert');
            }
        }

        return new cleanup_result($count, [get_string('removedcount', 'recerttask_forum', $count)]);
    }

    /**
     * Returns a non-destructive description of the data affected by this provider.
     *
     * @param task_context $context Execution context.
     * @return array Structured non-destructive impact description.
     */
    public function describe(task_context $context): array {
        global $DB;
        $sql = "SELECT COUNT(1)
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid
                   AND p.userid = :userid";
        return ['posts' => (int)$DB->get_field_sql($sql, [
            'forumid' => $context->instanceid,
            'userid' => $context->userid,
        ])];
    }
}
