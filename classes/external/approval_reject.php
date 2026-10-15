<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\external;

use core\message\message;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_mention\observer\database_observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Web service to reject a Database record by an authorised reviewer.
 *
 * Provides two functions:
 *  - local_mention_get_rejections: return the past rejection history (with
 *    reviewer name and time) so the client can pre-fill the rejection dialog.
 *  - local_mention_reject_record: set the record back to "unapproved", persist
 *    the rejection reason and send a notification to the record author with a
 *    link to the single-entry view.
 *
 * Only the assigned reviewer (or a site/manager admin) may reject a record;
 * the same permission used for approving is reused here.
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approval_reject extends external_api {

    /**
     * Returns the parameters of the get_rejections function.
     *
     * @return external_function_parameters
     */
    public static function get_rejections_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'Database record id'),
        ]);
    }

    /**
     * Returns the structure of the get_rejections result.
     *
     * @return external_single_structure
     */
    public static function get_rejections_returns(): external_single_structure {
        return new external_single_structure([
            'rejections' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Rejection id'),
                    'reviewer' => new external_value(PARAM_TEXT, 'Reviewer full name'),
                    'reason' => new external_value(PARAM_RAW, 'Rejection reason'),
                    'timecreated' => new external_value(PARAM_INT, 'Unix timestamp'),
                ])
            ),
        ]);
    }

    /**
     * Retrieve the rejection history for a record.
     *
     * @param int $recordid The data_records id.
     * @return array
     */
    public static function get_rejections(int $recordid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::get_rejections_parameters(), ['recordid' => $recordid]);
        $recordid = (int)$params['recordid'];
        if ($recordid <= 0) {
            return ['rejections' => []];
        }

        $record = $DB->get_record('data_records', ['id' => $recordid]);
        if (!$record) {
            return ['rejections' => []];
        }

        $data = $DB->get_record('data', ['id' => (int)$record->dataid]);
        $cm = $data ? get_coursemodule_from_instance('data', (int)$data->id, 0, false, MUST_EXIST) : null;
        if (!$cm) {
            return ['rejections' => []];
        }

        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $rejections = [];
        $rows = $DB->get_records('local_mention_rejection', ['recordid' => $recordid], 'timecreated ASC');
        foreach ($rows as $row) {
            $reviewer = $DB->get_record('user', ['id' => (int)$row->reviewerid]);
            $rejections[] = [
                'id' => (int)$row->id,
                'reviewer' => $reviewer ? fullname($reviewer) : get_string('user'),
                'reason' => (string)$row->reason,
                'timecreated' => (int)$row->timecreated,
            ];
        }

        return ['rejections' => $rejections];
    }

    /**
     * Returns the parameters of the reject_record function.
     *
     * @return external_function_parameters
     */
    public static function reject_record_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'Database record id'),
            'reason' => new external_value(PARAM_RAW, 'Rejection reason'),
        ]);
    }

    /**
     * Returns the structure of the reject_record result.
     *
     * @return external_single_structure
     */
    public static function reject_record_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the rejection was applied'),
            'error' => new external_value(PARAM_TEXT, 'Error message when not successful'),
        ]);
    }

    /**
     * Reject a database record.
     *
     * Sets the record approval back to 0, persists the reason and notifies the
     * record author with a link to the single-entry view.
     *
     * @param int $recordid The data_records id.
     * @param string $reason The rejection reason.
     * @return array
     */
    public static function reject_record(int $recordid = 0, string $reason = ''): array {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::reject_record_parameters(), [
            'recordid' => $recordid,
            'reason' => $reason,
        ]);
        $recordid = (int)$params['recordid'];
        $reason = trim((string)$params['reason']);

        $record = $DB->get_record('data_records', ['id' => $recordid]);
        if (!$record) {
            return ['success' => false, 'error' => get_string('reject_norecord', 'local_mention')];
        }

        $data = $DB->get_record('data', ['id' => (int)$record->dataid]);
        $cm = $data ? get_coursemodule_from_instance('data', (int)$data->id, 0, false, MUST_EXIST) : null;
        if (!$cm) {
            return ['success' => false, 'error' => get_string('reject_norecord', 'local_mention')];
        }

        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // The same permission gate used for approving applies here.
        if (!has_capability('mod/data:approve', $context) || !database_observer::is_reviewer_for_record($cm, $record)) {
            return ['success' => false, 'error' => get_string('reject_nopermission', 'local_mention')];
        }

        if ($reason === '') {
            return ['success' => false, 'error' => get_string('reject_noreason', 'local_mention')];
        }

        // Reset the approval flag to "unapproved".
        require_once($CFG->dirroot . '/mod/data/locallib.php');
        data_approve_entry($record->id, false);

        // Persist the rejection record.
        $DB->insert_record('local_mention_rejection', [
            'recordid' => (int)$record->id,
            'reviewerid' => (int)$USER->id,
            'reason' => $reason,
            'timecreated' => time(),
        ]);

        // Notify the author.
        self::notify_author($record, $data, $cm, $reason);

        return ['success' => true, 'error' => ''];
    }

    /**
     * Send a rejection notification to the record author via the message system.
     *
     * @param \stdClass $record The data_records record.
     * @param \stdClass $data The data activity.
     * @param \stdClass $cm The course module.
     * @param string $reason The rejection reason.
     * @return void
     */
    private static function notify_author(\stdClass $record, \stdClass $data, \stdClass $cm, string $reason): void {
        global $DB;

        $author = $DB->get_record('user', ['id' => (int)$record->userid]);
        if (!$author) {
            return;
        }

        $url = (new \moodle_url('/mod/data/view.php', ['d' => $cm->instance, 'rid' => $record->id]))->out();

        // 查找条目的 subject 字段值（字段通过 description = 'subject' 标识）。
        $subjectfield = $DB->get_record_sql(
            "SELECT * FROM {data_fields} WHERE dataid = :dataid AND " . $DB->sql_compare_text('description') . " = :subject",
            ['dataid' => (int)$cm->instance, 'subject' => 'subject']
        );
        $entrysubject = '';
        if ($subjectfield) {
            $entrysubject = (string)$DB->get_field('data_content', 'content', [
                'recordid' => $record->id,
                'fieldid' => $subjectfield->id,
            ]);
        }

        $subject = get_string('reject_subject', 'local_mention', (object) [
            'dataname' => $data->name,
        ]);
        $content = get_string('reject_content', 'local_mention', (object) [
            'dataname' => $data->name,
            'subject' => $entrysubject,
            'reason' => $reason,
            'url' => $url,
        ]);

        $message = new message();
        $message->component = 'local_mention';
        $message->name = 'mentions';
        $message->notification = 1;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $author;
        $message->subject = $subject;
        $message->fullmessage = $content;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $content;
        $message->smallmessage = $subject;
        $message->contexturl = $url;
        $message->contexturlname = $subject;

        message_send($message);
    }
}