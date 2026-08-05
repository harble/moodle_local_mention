<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\local\service;

defined('MOODLE_INTERNAL') || die();

class notifier {
    public static function send_mentions(array $mentions, array $payload): array {
        global $DB;

        $sent = 0;
        $failed = 0;

        $author = $DB->get_record('user', ['id' => $payload['authorid']]);
        if (!$author) {
            $author = \core_user::get_noreply_user();
        }

        foreach ($mentions as $mention) {
            $userto = $DB->get_record('user', ['id' => $mention->mentioneduserid]);
            if (!$userto) {
                repository::mark_status((int)$mention->id, repository::STATUS_FAILED);
                $failed++;
                continue;
            }

            $eventdata = new \core\message\message();
            $eventdata->component = 'local_mention';
            $eventdata->name = 'mentions';
            $eventdata->notification = 1;
            $eventdata->userfrom = $author;
            $eventdata->userto = $userto;
            $eventdata->subject = get_string('mentionnotificationsubject', 'local_mention');
            $eventdata->fullmessage = get_string('mentionnotificationfullmessage', 'local_mention', (object) [
                'author' => fullname($author, true),
                'item' => $payload['itemtype'],
                'url' => $payload['url'] ?? '',
            ]);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = nl2br(s($eventdata->fullmessage));
            $eventdata->smallmessage = get_string('mentionnotificationsmall', 'local_mention', fullname($author, true));

            if (!empty($payload['url'])) {
                $eventdata->contexturl = $payload['url'];
                $eventdata->contexturlname = $payload['subject'] ?? get_string('pluginname', 'local_mention');
            }

            $result = message_send($eventdata);
            if (!$result) {
                repository::mark_status((int)$mention->id, repository::STATUS_FAILED);
                $failed++;
                continue;
            }

            repository::mark_status((int)$mention->id, repository::STATUS_SENT, (string)$result);
            $sent++;
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }
}
