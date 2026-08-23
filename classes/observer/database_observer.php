<?php

namespace local_mention\observer;

defined('MOODLE_INTERNAL') || die();

class database_observer {

    public static function record_created(\mod_data\event\record_created $event): void {
        self::handle_record_event($event);
    }

    public static function record_updated(\mod_data\event\record_updated $event): void {
        self::handle_record_event($event);
    }

    private static function handle_record_event($event): void {
        global $DB;

        $record = $DB->get_record('data_records', ['id' => $event->objectid]);
        if (!$record) {
            return;
        }

        if ((int)$record->approved != 0) {
            return;
        }

        $cm = $DB->get_record('course_modules', ['id' => $event->contextinstanceid]);
        if (!$cm) {
            return;
        }

        $reviewerids = self::resolve_reviewer_ids($cm, $record);
        if (empty($reviewerids)) {
            return;
        }

        $submitter = $DB->get_record('user', ['id' => $record->userid]);
        $submittername = $submitter ? fullname($submitter) : get_string('user');

        $data = $DB->get_record('data', ['id' => $cm->instance]);
        $dataname = $data ? $data->name : 'Database';

        $context = \context_module::instance($cm->id);
        $url = (string)$event->get_url()->out();

        $pendingrecords = $DB->get_records('local_mention_notify_queue', [
            'component' => 'mod_data',
            'itemtype' => 'data_record',
            'itemid' => $record->id,
            'notiftype' => 'data_review',
            'status' => 0,
        ]);

        foreach ($pendingrecords as $pending) {
            $DB->update_record('local_mention_notify_queue', [
                'id' => $pending->id,
                'userto' => json_encode($reviewerids),
                'subject' => get_string('datareviewsubject', 'local_mention', [
                    'dataname' => $dataname,
                    'submitter' => $submittername,
                ]),
                'content' => get_string('datareviewcontent', 'local_mention', [
                    'dataname' => $dataname,
                    'submitter' => $submittername,
                    'url' => $url,
                ]),
                'timemodified' => time(),
            ]);
        }

        if (empty($pendingrecords)) {
            self::enqueue_notification([
                'component' => 'mod_data',
                'itemtype' => 'data_record',
                'itemid' => (int)$record->id,
                'contextid' => (int)$context->id,
                'courseid' => (int)$cm->course,
                'userfrom' => (int)$record->userid,
                'userto' => json_encode($reviewerids),
                'notiftype' => 'data_review',
                'seq' => 1,
                'subject' => get_string('datareviewsubject', 'local_mention', [
                    'dataname' => $dataname,
                    'submitter' => $submittername,
                ]),
                'content' => get_string('datareviewcontent', 'local_mention', [
                    'dataname' => $dataname,
                    'submitter' => $submittername,
                    'url' => $url,
                ]),
                'payload' => json_encode([
                    'cmid' => (int)$cm->id,
                    'recordid' => (int)$record->id,
                    'submitter' => $submittername,
                    'dataname' => $dataname,
                    'url' => $url,
                ]),
                'scheduledtime' => time(),
            ]);
        }
    }

    private static function resolve_reviewer_ids(\stdClass $cm, \stdClass $record): array {
        global $DB;

        $reviewerids = [];

        $data = $DB->get_record('data', ['id' => $cm->instance]);
        if (!$data) {
            return self::get_fallback_reviewers();
        }

        $channelsfield = $DB->get_record('data_fields', [
            'dataid' => $cm->instance,
            'description' => 'channels',
        ]);
        if (!$channelsfield) {
            return self::get_fallback_reviewers();
        }

        $channelsvalue = $DB->get_field('data_content', 'content', [
            'recordid' => $record->id,
            'fieldid' => $channelsfield->id,
        ]);
        if (empty($channelsvalue)) {
            return self::get_fallback_reviewers();
        }

        $channelitems = array_values(array_filter(array_map('trim', preg_split('/##/', $channelsvalue))));
        $normalizecb = function($v) {
            $v = str_replace(['\\', '／', '＼'], '/', $v);
            $v = preg_replace('/\s+/', '', $v);
            return $v;
        };
        $channelitems = array_values(array_map($normalizecb, $channelitems));
        if (empty($channelitems)) {
            return self::get_fallback_reviewers();
        }

        $fieldname = $data->name . '审批';

        $userfield = $DB->get_record('user_info_field', ['name' => $fieldname]);
        if (!$userfield) {
            return self::get_fallback_reviewers();
        }

        $userdatas = $DB->get_records('user_info_data', ['fieldid' => $userfield->id]);
        foreach ($userdatas as $userdata) {
            $rawdata = $userdata->data;
            if ((int)$userdata->dataformat === 1) {
                $rawdata = preg_replace('/<br\s*\/?>/i', "\n", $rawdata);
                $rawdata = preg_replace('/<\/(p|div|li|h[1-6]|tr)>/i', "\n", $rawdata);
                $rawdata = strip_tags($rawdata);
            }
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $rawdata)));
            $normalizedlines = array_values(array_map($normalizecb, $lines));
            $matched = array_intersect($channelitems, $normalizedlines);
            if (!empty($matched)) {
                $reviewerids[] = (int)$userdata->userid;
            }
        }

        $reviewerids = array_values(array_unique(array_filter($reviewerids)));
        if (empty($reviewerids)) {
            return self::get_fallback_reviewers();
        }

        $reviewerids = array_values(array_filter($reviewerids, function($id) use ($record) {
            return $id != $record->userid;
        }));

        return $reviewerids;
    }

    private static function get_fallback_reviewers(): array {
        global $DB;

        $reviewerids = [];
        $admins = get_admins();
        foreach ($admins as $admin) {
            $reviewerids[] = (int)$admin->id;
        }

        return array_values(array_unique(array_filter($reviewerids)));
    }

    private static function enqueue_notification(array $data): void {
        global $DB;

        $data['timecreated'] = time();
        $data['timemodified'] = time();

        $existing = $DB->get_record('local_mention_notify_queue', [
            'component' => $data['component'],
            'itemtype' => $data['itemtype'],
            'itemid' => $data['itemid'],
            'notiftype' => $data['notiftype'],
            'seq' => $data['seq'],
            'status' => 0,
        ]);

        if ($existing) {
            return;
        }

        try {
            $DB->insert_record('local_mention_notify_queue', $data);
        } catch (\dml_write_exception $e) {
            if (strpos($e->getMessage(), 'uniq_pending') !== false) {
                return;
            }
            throw $e;
        }
    }
}