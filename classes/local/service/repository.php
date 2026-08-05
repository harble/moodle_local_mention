<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\local\service;

defined('MOODLE_INTERNAL') || die();

class repository {
    public const STATUS_NEW = 0;
    public const STATUS_SENT = 1;
    public const STATUS_FAILED = 2;
    public const STATUS_REVOKED = 3;
    public const STATUS_READ = 4;

    public static function sync_mentions(array $payload, array $mentions): array {
        global $DB;

        $now = time();
        $existing = $DB->get_records('local_mention', [
            'component' => $payload['component'],
            'itemtype' => $payload['itemtype'],
            'itemid' => $payload['itemid'],
        ]);

        $existingbyuser = [];
        foreach ($existing as $record) {
            $existingbyuser[(int)$record->mentioneduserid] = $record;
        }

        $targetusers = [];
        $tosend = [];

        foreach ($mentions as $mention) {
            $userid = (int)$mention['userid'];
            $targetusers[$userid] = true;

            if (!isset($existingbyuser[$userid])) {
                $record = (object)[
                    'component' => $payload['component'],
                    'itemtype' => $payload['itemtype'],
                    'itemid' => $payload['itemid'],
                    'contextid' => $payload['contextid'],
                    'courseid' => $payload['courseid'],
                    'authorid' => $payload['authorid'],
                    'mentioneduserid' => $userid,
                    'mentiontext' => $mention['mentiontext'],
                    'contenthash' => $payload['contenthash'] ?? null,
                    'status' => self::STATUS_NEW,
                    'notificationid' => null,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $record->id = $DB->insert_record('local_mention', $record);
                $tosend[] = $record;
                continue;
            }

            $current = $existingbyuser[$userid];
            $current->mentiontext = $mention['mentiontext'];
            $current->contenthash = $payload['contenthash'] ?? null;
            $current->timemodified = $now;

            if ((int)$current->status === self::STATUS_REVOKED) {
                $current->status = self::STATUS_NEW;
                $tosend[] = $current;
            }

            $DB->update_record('local_mention', $current);
        }

        $revoked = 0;
        foreach ($existingbyuser as $userid => $record) {
            if (!isset($targetusers[$userid]) && (int)$record->status !== self::STATUS_REVOKED) {
                $record->status = self::STATUS_REVOKED;
                $record->timemodified = $now;
                $DB->update_record('local_mention', $record);
                $revoked++;
            }
        }

        return [
            'tosend' => $tosend,
            'revoked' => $revoked,
        ];
    }

    public static function mark_status(int $mentionid, int $status, ?string $notificationid = null): void {
        global $DB;

        $record = (object)[
            'id' => $mentionid,
            'status' => $status,
            'timemodified' => time(),
        ];

        if ($notificationid !== null) {
            $record->notificationid = $notificationid;
        }

        $DB->update_record('local_mention', $record);
    }
}
