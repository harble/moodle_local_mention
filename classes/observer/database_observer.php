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

        $context = \context_module::instance($cm->id);
        $url = (string)$event->get_url()->out();

        foreach ($reviewerids as $reviewerid) {
            if ($reviewerid == $record->userid) {
                continue;
            }

            self::enqueue_notification([
                'component' => 'mod_data',
                'itemtype' => 'data_record',
                'itemid' => (int)$record->id,
                'contextid' => (int)$context->id,
                'courseid' => (int)$cm->course,
                'userfrom' => (int)$record->userid,
                'userto' => (int)$reviewerid,
                'notiftype' => 'data_review',
                'seq' => 1,
                'subject' => get_string('datareviewsubject', 'local_mention', $submittername),
                'content' => get_string('datareviewcontent', 'local_mention', [
                    'submitter' => $submittername,
                    'url' => $url,
                ]),
                'payload' => json_encode([
                    'cmid' => (int)$cm->id,
                    'recordid' => (int)$record->id,
                    'submitter' => $submittername,
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

        $channelitems = array_filter(array_map('trim', preg_split('/\r?\n/', $channelsvalue)));
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
            $useritems = array_filter(array_map('trim', preg_split('/\r?\n/', $userdata->content)));
            $matched = array_intersect($channelitems, $useritems);
            if (!empty($matched)) {
                $reviewerids[] = (int)$userdata->userid;
            }
        }

        $reviewerids = array_values(array_unique(array_filter($reviewerids)));
        if (empty($reviewerids)) {
            return self::get_fallback_reviewers();
        }

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
            'userto' => $data['userto'],
            'notiftype' => $data['notiftype'],
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