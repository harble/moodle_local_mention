<?php

namespace local_mention\task;

use local_mention\observer\database_observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Database 条目审核通知的延迟处理任务（adhoc task）
 *
 * 背景：mod_data 的 record_created 事件在 data_content（字段值）尚未写入之前触发，
 * 若在该事件回调中立即解析条目的 channels 值会读到空。因此观察者 record_created
 * 只负责调度本任务并延迟数秒后执行，届时字段已落库，再调用统一的审核通知流程。
 *
 * record_created 与 record_updated（无竞态）最终都汇入 database_observer::
 * process_record_review() 统一处理。
 */
class database_review_notification extends \core\task\adhoc_task {

    /**
     * 执行延迟的审核通知流程
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->cmid) || empty($data->recordid)) {
            return;
        }

        // 重新获取课程模块与条目记录
        $cm = $DB->get_record('course_modules', ['id' => (int)$data->cmid]);
        $record = $DB->get_record('data_records', ['id' => (int)$data->recordid]);
        if (!$cm || !$record) {
            return;
        }

        // 已审核的条目无需通知（与 process_record_review 中的判断保持一致）
        if ((int)$record->approved != 0) {
            return;
        }

        // 构造条目查看 URL（与 mod_data 前端一致）
        $url = (new \moodle_url('/mod/data/view.php', [
            'd' => $cm->instance,
            'rid' => $record->id,
        ]))->out();

        database_observer::process_record_review($cm, $record, $url);
    }
}