<?php

namespace local_mention\task;

defined('MOODLE_INTERNAL') || die();

/**
 * 通知队列定时任务处理器
 *
 * 主要职责：
 * 1. process_pending_notifications：处理待发送的通知记录（status=0）
 *    - 逐条发送给审核人，支持多接收人（userto 为 JSON 数组）
 *    - 发送失败自动重试（最多3次），3次后标记为永久失败（status=2）
 *
 * 2. generate_periodic_reminders：生成周期性提醒通知
 *    - 基于条目创建时间，每7天生成一次提醒，最多4次（含初始通知）
 *    - 仅处理未审核（approved=0）、非草稿、45天内活跃的条目
 *    - 通过 seq 字段区分通知序号（1=初始，2/3/4=第N次提醒）
 *    - userto 取最大 seq 记录中的值（确保使用最新的审核人列表）
 *
 * 状态流转：
 *   0=待发送 → 1=已发送 / 2=永久失败 / 3=已取消
 *   退休记录：seq 设为负数（-id），避免唯一键冲突
 */
class queue_processor extends \core\task\scheduled_task {

/*
// 临时测试常量
const PERIODIC_INTERVAL = 5 * MINSECS;     // 5分钟 instead of 7天
const MAX_NOTIFICATIONS = 4;
const QUERY_TIME_WINDOW = 2 * HOURSECS;    // 2小时 instead of 45天
*/
    // 提醒间隔：每7天触发一次
    const PERIODIC_INTERVAL = 7 * DAYSECS;
    // 最大通知次数：初始 + 3次提醒 = 共4次
    const MAX_NOTIFICATIONS = 4;
    // 查询时间窗口：只查询45天内创建的通知记录，避免全表扫描
    const QUERY_TIME_WINDOW = 45 * DAYSECS;

    public function get_name(): string {
        return 'local_mention notification queue processor';
    }

    /**
     * 定时任务入口
     * 执行顺序：先处理待发送通知，再生成周期性提醒
     */
    public function execute(): void {
        $this->process_pending_notifications();
        $this->generate_periodic_reminders();
    }

    /**
     * 处理待发送的通知记录（status=0）
     *
     * 查询 scheduledtime <= 当前时间 的待发送记录，逐条调用 send_notification 发送
     * 每次最多处理200条，避免单次执行时间过长
     */
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

    /**
     * 发送单条通知给审核人
     *
     * 流程：
     * 1. 重新读取记录并二次确认状态（防并发）
     * 2. 调用 is_record_valid 检查条目是否仍有效（未审核+非草稿）
     *    - 无效则标记为已取消（status=3），不再重试
     * 3. 解析 userto 字段（支持单个ID或JSON数组）
     * 4. 遍历所有接收人，逐个发送通知消息
     * 5. 根据发送结果更新状态：
     *    - 全部成功：status=1（已发送）
     *    - 部分/全部失败：retrycount+1，3次后 status=2（永久失败）
     *      未达3次则延迟5分钟重试
     */
    private function send_notification(\stdClass $record): void {
        global $DB;

        // 重新读取记录，确保状态未被其他并发任务修改
        $record = $DB->get_record('local_mention_notify_queue', ['id' => $record->id]);
        if (!$record || (int)$record->status != 0) {
            return;
        }

        // 检查条目是否仍满足发送条件（未审核、非草稿）
        // 如果条目已被审核或标记为草稿，则取消该通知
        if (!$this->is_record_valid($record)) {
            $DB->update_record('local_mention_notify_queue', [
                'id' => $record->id,
                'status' => 3,
                'timemodified' => time(),
            ]);
            return;
        }

        // 解析接收人列表：支持旧格式（单个整数ID）和新格式（JSON数组）
        $userids = json_decode($record->userto, true);
        if (!is_array($userids)) {
            $userids = [(int)$record->userto];
        }

        // 获取发件人信息，不存在则使用系统 noreply 用户
        $userfrom = $DB->get_record('user', ['id' => $record->userfrom]);
        if (!$userfrom) {
            $userfrom = \core_user::get_noreply_user();
        }

        $hassuccess = false;
        $hasfailure = false;

        // 遍历每个审核人，逐个发送通知
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

            // 构造 Moodle 消息对象
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

            // 如果 payload 中有 URL，则设置消息跳转链接
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

        // 所有接收人发送完毕后，更新记录状态
        if ($hassuccess) {
            // 至少有一人发送成功，标记为已发送
            $DB->update_record('local_mention_notify_queue', [
                'id' => $record->id,
                'status' => 1,
                'senttime' => time(),
                'timemodified' => time(),
            ]);
        } elseif ($hasfailure) {
            // 所有人都失败了，根据重试次数决定后续策略
            $retrycount = (int)$record->retrycount + 1;
            if ($retrycount >= 3) {
                // 重试3次仍失败，标记为永久失败，不再重试
                $DB->update_record('local_mention_notify_queue', [
                    'id' => $record->id,
                    'status' => 2,
                    'retrycount' => $retrycount,
                    'timemodified' => time(),
                ]);
            } else {
                // 未达最大重试次数，延迟5分钟后重试
                $DB->update_record('local_mention_notify_queue', [
                    'id' => $record->id,
                    'retrycount' => $retrycount,
                    'scheduledtime' => time() + 300,
                    'timemodified' => time(),
                ]);
            }
        }
    }

    /**
     * 检查通知对应的条目是否仍然有效
     *
     * 有效性条件（仅 mod_data 类型需检查，其他类型默认有效）：
     * 1. 条目必须存在
     * 2. 条目尚未通过审核（approved=0）
     * 3. 条目未标记为草稿（标签中不含 "draft"，不区分大小写）
     *
     * @param \stdClass $record 通知队列记录
     * @return bool 有效返回 true，无效返回 false
     */
    private function is_record_valid(\stdClass $record): bool {
        global $DB;

        // 非 mod_data 类型的通知默认有效
        if ($record->component !== 'mod_data' || $record->itemtype !== 'data_record') {
            return true;
        }

        // 检查条目是否存在
        $datarecord = $DB->get_record('data_records', ['id' => $record->itemid]);
        if (!$datarecord) {
            return false;
        }

        // 检查审核状态：已审核通过的条目不再需要通知
        if ((int)$datarecord->approved != 0) {
            return false;
        }

        // 检查草稿标签：包含 "draft"（不区分大小写）的条目视为草稿
        $tags = \core_tag_tag::get_item_tags_array('mod_data', 'data_records', $record->itemid);
        foreach ($tags as $tagname) {
            if (stripos($tagname, 'draft') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 生成周期性提醒通知
     *
     * 核心逻辑：
     * 1. 查询45天内创建的、未审核、非草稿的活跃通知记录（status!=3，seq>=1）
     * 2. 按条目ID分组，计算每个条目的 maxseq（已发送/待发送的最大序号）
     *    - userto 取最大 seq 记录中的值（因为 database_observer 会在更新时
     *      将所有 pending 记录的 userto 更新为最新审核人，seq 越大越新）
     * 3. 根据条目创建时间计算应该发送到第几次（shouldnotify）
     *    - shouldnotify = min(floor(elapsed / 7天) + 1, 4)
     * 4. 如果 shouldnotify > maxseq，说明需要生成新的提醒记录
     *    - 从 maxseq+1 开始，逐个插入新记录，直到 shouldnotify
     * 5. 插入时捕获唯一键冲突（uniq_pending），防止并发重复生成
     */
    private function generate_periodic_reminders(): void {
        global $DB;

        $now = time();

        // 查询活跃通知记录：
        // - JOIN data_records 过滤已审核的条目（数据库层面先过滤，减少PHP处理量）
        // - status!=3 排除已取消的记录（草稿期间被取消的）
        // - seq>=1 排除退休记录（seq为负数的）
        // - timecreated>=45天 限制时间窗口，避免全表扫描
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

        // 按条目分组，计算 maxseq 和最新 userto
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

            // 每次迭代都覆盖 userto，确保最终保存的是最大 seq 记录的审核人列表
            // 因为 database_observer 在条目更新时，会同时更新所有 pending 记录的 userto
            // 所以 seq 越大，其 userto 越新
            $userids = json_decode($r->userto, true);
            if (!is_array($userids)) {
                $userids = [(int)$r->userto];
            }
            $grouped[$key]['userto'] = $userids;

            $grouped[$key]['maxseq'] = max($grouped[$key]['maxseq'], $seq);
        }

        // 遍历每个条目，判断是否需要生成新的提醒
        foreach ($grouped as $key => $info) {
            // 计算自条目创建以来经过的天数
            $elapsed = $now - $info['recordcreated'];
            // 计算应该发送到第几次通知（初始=1，第1次提醒=2，以此类推）
            $shouldnotify = min(floor($elapsed / self::PERIODIC_INTERVAL) + 1, self::MAX_NOTIFICATIONS);

            // 已发送/待发送的次数达到或超过应有次数，跳过
            if ($shouldnotify <= $info['maxseq']) {
                continue;
            }

            // 获取课程模块信息（用于构建跳转链接）
            $cm = $DB->get_record('course_modules', ['course' => $info['courseid'], 'module' => $DB->get_field('modules', 'id', ['name' => 'data'])]);
            if (!$cm) {
                continue;
            }

            // 二次检查条目状态（防止 SQL 查询后状态发生变化）
            $datarecord = $DB->get_record('data_records', ['id' => $info['itemid']]);
            if (!$datarecord) {
                continue;
            }

            // PHP 层双重检查：条目是否仍未审核
            if ((int)$datarecord->approved != 0) {
                continue;
            }

            // PHP 层双重检查：是否仍为非草稿状态
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

            // 获取提交人姓名
            $submitter = $DB->get_record('user', ['id' => $datarecord->userid]);
            $submittername = $submitter ? fullname($submitter) : get_string('user');

            // 获取 Database 活动名称
            $data = $DB->get_record('data', ['id' => $cm->instance]);
            $dataname = $data ? $data->name : 'Database';

            // 构建条目跳转链接（与初始通知保持一致：d=dataid, rid=recordid）
            $url = (string)(new \moodle_url('/mod/data/view.php', ['d' => $cm->instance, 'rid' => $info['itemid']]))->out();

            // 从 maxseq+1 开始，逐个生成缺失的提醒记录
            for ($seq = $info['maxseq'] + 1; $seq <= $shouldnotify; $seq++) {
                // 每条提醒按间隔分散发送：第一条立即发送，后续每条间隔一个周期
                $scheduledtime = $now + ($seq - $info['maxseq'] - 1) * self::PERIODIC_INTERVAL;
                // 计算该条提醒对应的待审核天数（基于 scheduledtime 而非当前时间）
                $seqelapsed = $scheduledtime - $info['recordcreated'];
                $elapseddays = max(0, floor($seqelapsed / DAYSECS));

                $payload = [
                    'cmid' => (int)$cm->id,
                    'recordid' => (int)$info['itemid'],
                    'submitter' => $submittername,
                    'dataname' => $dataname,
                    'url' => $url,
                    'seq' => $seq,
                    'elapseddays' => $elapseddays,
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
                            'elapseddays' => $elapseddays,
                            'url' => $url,
                        ]),
                        'payload' => json_encode($payload),
                        'status' => 0,
                        'scheduledtime' => $scheduledtime,
                        'retrycount' => 0,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                } catch (\dml_write_exception $e) {
                    // 唯一键冲突：说明该 seq 的记录已存在（并发场景），跳过继续
                    if (strpos($e->getMessage(), 'uniq_pending') !== false) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
    }
}