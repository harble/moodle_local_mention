<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\local\service;

defined('MOODLE_INTERNAL') || die();

class manager {
    public static function content_saved(array $payload): array {
        $validated = self::validate_payload($payload);
        if (empty($validated)) {
            return ['status' => 'ignored', 'reason' => 'invalid_payload'];
        }

        if (trim((string)$validated['content']) === '') {
            return ['status' => 'ignored', 'reason' => 'empty_content'];
        }

        $content = (string)$validated['content'];
        $userids = parser::extract_userids($content);
        $usernames = parser::extract_usernames($content);
        if (empty($userids) && empty($usernames)) {
            repository::sync_mentions($validated, []);
            return ['status' => 'ok', 'mentions' => 0, 'sent' => 0, 'failed' => 0];
        }

        $context = \context::instance_by_id((int)$validated['contextid'], MUST_EXIST);
        $bypassrestrictions = !empty($validated['searchallusers']);

        $mentions = [];
        if (!empty($userids)) {
            $usersbyid = user_search::resolve_users_by_ids(
                $userids,
                $context,
                (int)$validated['courseid'],
                $bypassrestrictions
            );
            foreach ($userids as $userid) {
                if (!isset($usersbyid[$userid])) {
                    continue;
                }

                $user = $usersbyid[$userid];
                if ((int)$user->id === (int)$validated['authorid']) {
                    continue;
                }

                if (!$bypassrestrictions && is_array($validated['alloweduserids']) &&
                        !in_array((int)$user->id, $validated['alloweduserids'])) {
                    continue;
                }

                /*
                 * Check if the user can access the item being mentioned. This is a special case for hsuforum posts where
                 * a user may be mentioned in a post that they do not have access to. In this case, we do not want to
                 * send them a notification.
                 */
                // if (!self::can_access_payload_item($validated, $user)) {
                //    continue;
                // }

                $mentions[] = [
                    'userid' => (int)$user->id,
                    'mentiontext' => '@' . fullname($user, true),
                ];
            }
        } else {
            $users = user_search::resolve_users_by_usernames(
                $usernames,
                $context,
                (int)$validated['courseid'],
                $bypassrestrictions
            );
            foreach ($usernames as $username) {
                $key = \core_text::strtolower($username);
                if (!isset($users[$key])) {
                    continue;
                }

                $user = $users[$key];
                if ((int)$user->id === (int)$validated['authorid']) {
                    continue;
                }

                if (!$bypassrestrictions && is_array($validated['alloweduserids']) &&
                        !in_array((int)$user->id, $validated['alloweduserids'])) {
                    continue;
                }

                // if (!self::can_access_payload_item($validated, $user)) {
                //    continue;
                // }

                $mentions[] = [
                    'userid' => (int)$user->id,
                    'mentiontext' => '@' . $username,
                ];
            }
        }

        $sync = repository::sync_mentions($validated, $mentions);
        $delivery = notifier::send_mentions($sync['tosend'], $validated);

        return [
            'status' => 'ok',
            'mentions' => count($mentions),
            'created' => count($sync['tosend']),
            'revoked' => $sync['revoked'],
            'sent' => $delivery['sent'],
            'failed' => $delivery['failed'],
        ];
    }

    private static function can_access_payload_item(array $payload, $user): bool {
        global $CFG, $DB;

        if (!$user || empty($user->id)) {
            return false;
        }

        if (($payload['component'] ?? '') !== 'mod_hsuforum' || ($payload['itemtype'] ?? '') !== 'post') {
            return true;
        }

        require_once($CFG->dirroot . '/mod/hsuforum/lib.php');

        $post = $DB->get_record('hsuforum_posts', ['id' => (int)($payload['itemid'] ?? 0)]);
        if (!$post) {
            return false;
        }

        $discussion = $DB->get_record('hsuforum_discussions', ['id' => (int)$post->discussion]);
        if (!$discussion) {
            return false;
        }

        $forum = $DB->get_record('hsuforum', ['id' => (int)$discussion->forum]);
        if (!$forum) {
            return false;
        }

        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course);
        if (!$cm) {
            return false;
        }

        return \hsuforum_user_can_see_post($forum, $discussion, $post, $user, $cm);
    }

    private static function validate_payload(array $payload): array {
        $required = ['component', 'itemtype', 'itemid', 'contextid', 'courseid', 'authorid', 'content'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                return [];
            }
        }

        $sanitized = [];
        $sanitized['component'] = (string)$payload['component'];
        $sanitized['itemtype'] = (string)$payload['itemtype'];
        $sanitized['itemid'] = (int)$payload['itemid'];
        $sanitized['contextid'] = (int)$payload['contextid'];
        $sanitized['courseid'] = (int)$payload['courseid'];
        $sanitized['authorid'] = (int)$payload['authorid'];
        $sanitized['content'] = (string)$payload['content'];
        $sanitized['subject'] = isset($payload['subject']) ? (string)$payload['subject'] : '';
        $sanitized['url'] = isset($payload['url']) ? (string)$payload['url'] : '';
        $sanitized['format'] = isset($payload['format']) ? (int)$payload['format'] : FORMAT_HTML;
        $sanitized['searchallusers'] = !empty($payload['searchallusers']);
        $sanitized['alloweduserids'] = null;
        $sanitized['contenthash'] = sha1((string)$sanitized['content']);

        if (array_key_exists('alloweduserids', $payload) && is_array($payload['alloweduserids'])) {
            $alloweduserids = [];
            foreach ($payload['alloweduserids'] as $userid) {
                $userid = (int)$userid;
                if ($userid > 0) {
                    $alloweduserids[$userid] = $userid;
                }
            }
            $sanitized['alloweduserids'] = array_values($alloweduserids);
        }

        if ($sanitized['component'] === '' || $sanitized['itemtype'] === '' || $sanitized['itemid'] <= 0 ||
            $sanitized['contextid'] <= 0 || $sanitized['authorid'] <= 0) {
            return [];
        }

        return $sanitized;
    }
}
