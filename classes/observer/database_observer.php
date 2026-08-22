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

        if (self::has_draft_tag($record->id)) {
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

    private static function has_draft_tag(int $recordid): bool {
        $tags = \core_tag_tag::get_item_tags_array('mod_data', 'data_records', $recordid);
        foreach ($tags as $tagname) {
            if (stripos($tagname, 'draft') !== false) {
                return true;
            }
        }
        return false;
    }

    private static function resolve_reviewer_ids(\stdClass $cm, \stdClass $record): array {
        global $DB;

        $reviewerids = [];

        $pluginconfig = get_config('local_mention');
        $configured = $pluginconfig->datareviewers ?? '';
        if (!empty($configured)) {
            $reviewerids = array_filter(array_map('intval', explode(',', $configured)));
        }

        if (empty($reviewerids)) {
            $coursecontext = \context_course::instance($cm->course);
            $admins = get_admins();
            foreach ($admins as $admin) {
                $reviewerids[] = (int)$admin->id;
            }
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