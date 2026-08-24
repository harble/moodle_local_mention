<?php

namespace local_mention\task;

defined('MOODLE_INTERNAL') || die();

class queue_processor extends \core\task\scheduled_task {

    const PERIODIC_INTERVAL = 7 * DAYSECS;
    const MAX_NOTIFICATIONS = 4;
    const QUERY_TIME_WINDOW = 45 * DAYSECS;

    public function get_name(): string {
        return 'local_mention notification queue processor';
    }

    public function execute(): void {
        $this->process_pending_notifications();
        $this->generate_periodic_reminders();
    }

    private function process_pending_notifications(): void {
        global $DB;

        $now = time();

        $sql = "SELECT *
                FROM {local_mention_notify_queue}
                WHERE status = 0
                  AND scheduledtime <= ?
                ORDER BY scheduledtime ASC
                LIMIT 200";
        $records = $DB->get_records_sql($sql, [$now]);

        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {
            $this->send_notification($record);
        }
    }

    private function send_notification(\stdClass $record): void {
        global $DB;

        $record = $DB->get_record('local_mention_notify_queue', ['id' => $record->id]);
        if (!$record || (int)$record->status != 0) {
            return;
        }

        if (!$this->is_record_valid($record)) {
            $DB->update_record('local_mention_notify_queue', [
                'id' => $record->id,
                'status' => 3,
                'timemodified' => time(),
            ]);
            return;
        }

        $userids = json_decode($record->userto, true);
        if (!is_array($userids)) {
            $userids = [(int)$record->userto];
        }

        $userfrom = $DB->get_record('user', ['id' => $record->userfrom]);
        if (!$userfrom) {
            $userfrom = \core_user::get_noreply_user();
        }

        $hassuccess = false;
        $hasfailure = false;

        foreach ($userids as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) {
                continue;
            }

            $userto = $DB->get_record('user', ['id' => $uid]);
            if (!$userto) {
                $hasfailure = true;
                continue;
            }

            $message = new \core\message\message();
            $message->component = 'local_mention';
            $message->name = 'mentions';
            $message->notification = 1;
            $message->userfrom = $userfrom;
            $message->userto = $userto;
            $message->subject = $record->subject ?: '';
            $message->fullmessage = $record->content ?: '';
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $record->content ?: '';
            $message->smallmessage = $record->subject ?: '';

            $payload = json_decode($record->payload ?: '', true);
            if (is_array($payload) && !empty($payload['url'])) {
                $message->contexturl = $payload['url'];
                $message->contexturlname = $record->subject ?: get_string('notification');
            }

            $result = message_send($message);
            if ($result) {
                $hassuccess = true;
            } else {
                $hasfailure = true;
            }
        }

        if ($hassuccess) {
            $DB->update_record('local_mention_notify_queue', [
                'id' => $record->id,
                'status' => 1,
                'senttime' => time(),
                'timemodified' => time(),
            ]);
        } elseif ($hasfailure) {
            $retrycount = (int)$record->retrycount + 1;
            if ($retrycount >= 3) {
                $DB->update_record('local_mention_notify_queue', [
                    'id' => $record->id,
                    'status' => 2,
                    'retrycount' => $retrycount,
                    'timemodified' => time(),
                ]);
            } else {
                $DB->update_record('local_mention_notify_queue', [
                    'id' => $record->id,
                    'retrycount' => $retrycount,
                    'scheduledtime' => time() + 300,
                    'timemodified' => time(),
                ]);
            }
        }
    }

    private function is_record_valid(\stdClass $record): bool {
        global $DB;

        if ($record->component !== 'mod_data' || $record->itemtype !== 'data_record') {
            return true;
        }

        $datarecord = $DB->get_record('data_records', ['id' => $record->itemid]);
        if (!$datarecord) {
            return false;
        }

        if ((int)$datarecord->approved != 0) {
            return false;
        }

        $tags = \core_tag_tag::get_item_tags_array('mod_data', 'data_records', $record->itemid);
        foreach ($tags as $tagname) {
            if (stripos($tagname, 'draft') !== false) {
                return false;
            }
        }

        return true;
    }

    private function generate_periodic_reminders(): void {
        global $DB;

        $now = time();

        $sql = "SELECT DISTINCT r.*, dr.approved, dr.timecreated AS recordcreated
                FROM {local_mention_notify_queue} r
                JOIN {data_records} dr ON dr.id = r.itemid
                WHERE r.component = 'mod_data'
                  AND r.itemtype = 'data_record'
                  AND dr.approved = 0
                  AND r.seq >= 1
                  AND r.status != 3
                  AND r.timecreated >= ?
                ORDER BY r.itemid, r.seq";
        $allrecords = $DB->get_records_sql($sql, [$now - self::QUERY_TIME_WINDOW]);

        if (empty($allrecords)) {
            return;
        }

        $grouped = [];
        foreach ($allrecords as $r) {
            $key = $r->itemid;
            $seq = (int)$r->seq;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'itemid' => $r->itemid,
                    'userto' => [],
                    'courseid' => $r->courseid,
                    'contextid' => $r->contextid,
                    'userfrom' => $r->userfrom,
                    'recordcreated' => $r->recordcreated,
                    'maxseq' => 0,
                ];
            }

            $userids = json_decode($r->userto, true);
            if (!is_array($userids)) {
                $userids = [(int)$r->userto];
            }
            $grouped[$key]['userto'] = $userids;

            $grouped[$key]['maxseq'] = max($grouped[$key]['maxseq'], $seq);
        }

        foreach ($grouped as $key => $info) {
            $elapsed = $now - $info['recordcreated'];
            $shouldnotify = min(floor($elapsed / self::PERIODIC_INTERVAL) + 1, self::MAX_NOTIFICATIONS);

            if ($shouldnotify <= $info['maxseq']) {
                continue;
            }

            $cm = $DB->get_record('course_modules', ['course' => $info['courseid'], 'module' => $DB->get_field('modules', 'id', ['name' => 'data'])]);
            if (!$cm) {
                continue;
            }
            $datarecord = $DB->get_record('data_records', ['id' => $info['itemid']]);
            if (!$datarecord) {
                continue;
            }

            if ((int)$datarecord->approved != 0) {
                continue;
            }

            $tags = \core_tag_tag::get_item_tags_array('mod_data', 'data_records', $info['itemid']);
            $hasdraft = false;
            foreach ($tags as $tagname) {
                if (stripos($tagname, 'draft') !== false) {
                    $hasdraft = true;
                    break;
                }
            }
            if ($hasdraft) {
                continue;
            }

            $submitter = $DB->get_record('user', ['id' => $datarecord->userid]);
            $submittername = $submitter ? fullname($submitter) : get_string('user');

            $data = $DB->get_record('data', ['id' => $cm->instance]);
            $dataname = $data ? $data->name : 'Database';

            $url = (string)(new \moodle_url('/mod/data/view.php', ['id' => $cm->id]))->out() . '#record-' . $info['itemid'];

            for ($seq = $info['maxseq'] + 1; $seq <= $shouldnotify; $seq++) {
                $payload = [
                    'cmid' => (int)$cm->id,
                    'recordid' => (int)$info['itemid'],
                    'submitter' => $submittername,
                    'dataname' => $dataname,
                    'url' => $url,
                    'seq' => $seq,
                    'elapseddays' => floor($elapsed / DAYSECS),
                ];

                try {
                    $DB->insert_record('local_mention_notify_queue', [
                        'component' => 'mod_data',
                        'itemtype' => 'data_record',
                        'itemid' => (int)$info['itemid'],
                        'contextid' => (int)$info['contextid'],
                        'courseid' => (int)$info['courseid'],
                        'userfrom' => (int)$info['userfrom'],
                        'userto' => json_encode($info['userto']),
                        'notiftype' => 'data_review',
                        'seq' => $seq,
                        'subject' => get_string('datareviewremindersubject', 'local_mention', [
                            'dataname' => $dataname,
                            'seq' => $seq,
                        ]),
                        'content' => get_string('datareviewremindercontent', 'local_mention', [
                            'dataname' => $dataname,
                            'seq' => $seq,
                            'submitter' => $submittername,
                            'elapseddays' => floor($elapsed / DAYSECS),
                            'url' => $url,
                        ]),
                        'payload' => json_encode($payload),
                        'status' => 0,
                        'scheduledtime' => $now,
                        'retrycount' => 0,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                } catch (\dml_write_exception $e) {
                    if (strpos($e->getMessage(), 'uniq_pending') !== false) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
    }
}