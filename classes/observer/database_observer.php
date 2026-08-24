<?php

namespace local_mention\observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Database 活动模块事件观察者
 *
 * 监听 mod_data\event\record_created 和 record_updated 事件，
 * 当学生提交/更新待审核条目时，自动通知相关审核人员。
 *
 * 核心流程（handle_record_event）：
 * 1. 检查条目是否仍为待审核状态（approved=0）
 * 2. 解析审核人列表（resolve_reviewer_ids）
 *    - 根据条目的 channels 字段值，匹配用户自定义字段中的审核人配置
 *    - 匹配失败则使用系统管理员兜底
 * 3. 更新现有待发送记录（status=0）的审核人列表和内容
 *    - 应对审核人变更场景
 * 4. 退休已发送/已取消的旧记录（seq 设为 -id）
 *    - 确保唯一键不冲突
 * 5. 如果没有活跃的 seq=1 记录，则创建新的初始通知
 *    - 每次更新都重置提醒周期
 */
class database_observer {

    /**
     * 条目创建事件处理
     */
    public static function record_created(\mod_data\event\record_created $event): void {
        self::handle_record_event($event);
    }

    /**
     * 条目更新事件处理
     */
    public static function record_updated(\mod_data\event\record_updated $event): void {
        self::handle_record_event($event);
    }

    /**
     * 处理条目事件（创建或更新）
     *
     * 完整流程：
     * 1. 获取条目数据，检查审核状态（已审核则跳过）
     * 2. 获取课程模块信息
     * 3. 解析审核人列表
     * 4. 查找该条目所有待发送的通知记录（status=0）
     *    - 更新它们的 userto 为最新审核人列表
     *    - 更新 subject/content 为最新内容
     *    - 应对 channels 变更导致的审核人变化
     * 5. 退休所有非待发送的活跃记录（seq>=1, status!=0）
     *    - 将 seq 设为 -id，确保唯一键不冲突
     *    - 这些记录已发送/已取消，不再需要参与周期提醒
     * 6. 判断是否需要创建新的 seq=1 记录
     *    - 如果存在待发送的 seq=1 记录，不需要新建（已在第4步更新）
     *    - 如果不存在，则创建新的初始通知，重置提醒周期
     */
    private static function handle_record_event($event): void {
        global $DB;

        // 获取条目数据
        $record = $DB->get_record('data_records', ['id' => $event->objectid]);
        if (!$record) {
            return;
        }

        // 已审核的条目不再需要通知
        if ((int)$record->approved != 0) {
            return;
        }

        // 获取课程模块信息
        $cm = $DB->get_record('course_modules', ['id' => $event->contextinstanceid]);
        if (!$cm) {
            return;
        }

        // 解析审核人列表
        $reviewerids = self::resolve_reviewer_ids($cm, $record);
        if (empty($reviewerids)) {
            return;
        }

        // 获取提交人姓名
        $submitter = $DB->get_record('user', ['id' => $record->userid]);
        $submittername = $submitter ? fullname($submitter) : get_string('user');

        // 获取 Database 活动名称（用于通知标题和内容）
        $data = $DB->get_record('data', ['id' => $cm->instance]);
        $dataname = $data ? $data->name : 'Database';

        $context = \context_module::instance($cm->id);
        $url = (string)$event->get_url()->out();

        // 查找该条目所有待发送的通知记录（status=0）
        // 这些记录可能是初始通知（seq=1）或尚未发送的周期提醒（seq>=2）
        $pendingrecords = $DB->get_records('local_mention_notify_queue', [
            'component' => 'mod_data',
            'itemtype' => 'data_record',
            'itemid' => $record->id,
            'notiftype' => 'data_review',
            'status' => 0,
        ]);

        // 更新所有待发送记录的审核人列表和内容
        // 场景：用户修改了 channels 字段，导致审核人变更
        // 此时需要更新尚未发送的通知记录，确保发送给正确的审核人
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

        // 退休所有非待发送的活跃记录
        // 场景：条目被更新后，之前的通知记录可能已发送（status=1）或已取消（status=3）
        // 将它们的 seq 设为 -id，避免与新创建的 seq=1 记录产生唯一键冲突
        $DB->execute("UPDATE {local_mention_notify_queue} SET seq = -id WHERE component = ? AND itemtype = ? AND itemid = ? AND notiftype = ? AND seq >= 1 AND status != 0",
            ['mod_data', 'data_record', $record->id, 'data_review']);

        // 判断是否存在活跃的 seq=1 记录
        // 如果存在，说明初始通知已经在队列中（status=0），不需要重复创建
        // 如果不存在（已被退休或从未创建），则创建新的初始通知
        $hasactiveseq1 = false;
        foreach ($pendingrecords as $pending) {
            if ((int)$pending->seq === 1) {
                $hasactiveseq1 = true;
                break;
            }
        }

        // 没有活跃的 seq=1 记录，创建新的初始通知
        // 这会重置整个提醒周期（从 Day 0 重新计算）
        if (!$hasactiveseq1) {

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
                    'cmid' => (int)$cm->instance,
                    'recordid' => (int)$record->id,
                    'submitter' => $submittername,
                    'dataname' => $dataname,
                    'url' => $url,
                ]),
                'scheduledtime' => time(),
            ]);
        }
    }

    /**
     * 解析条目对应的审核人ID列表
     *
     * 匹配逻辑：
     * 1. 获取条目 channels 字段的值（格式："选项1##选项2##..."）
     *    - ## 是多选字段的分隔符
     * 2. 对每个选项进行标准化处理：
     *    - 统一斜杠为正斜杠（处理用户可能输入的反斜杠、全角斜杠）
     *    - 去除所有空格
     * 3. 查找用户自定义字段：[Database活动名称] + "审批"
     *    - 例如活动名称为"频道数据库"，则查找"频道数据库审批"字段
     * 4. 遍历所有用户的该自定义字段值
     *    - 按行分割（支持换行分隔的多值配置）
     *    - 与条目 channels 值进行精确匹配
     * 5. 过滤掉条目创建人（不通知自己）
     *
     * @param \stdClass $cm 课程模块记录
     * @param \stdClass $record 条目记录
     * @return array 审核人ID数组
     */
    private static function resolve_reviewer_ids(\stdClass $cm, \stdClass $record): array {
        global $DB;

        $reviewerids = [];

        // 获取 Database 活动信息
        $data = $DB->get_record('data', ['id' => $cm->instance]);
        if (!$data) {
            return self::get_fallback_reviewers();
        }

        // 查找 channels 字段（用于存储审核人分组的多选字段）
        $channelsfield = $DB->get_record('data_fields', [
            'dataid' => $cm->instance,
            'description' => 'channels',
        ]);
        if (!$channelsfield) {
            return self::get_fallback_reviewers();
        }

        // 获取条目的 channels 字段值
        $channelsvalue = $DB->get_field('data_content', 'content', [
            'recordid' => $record->id,
            'fieldid' => $channelsfield->id,
        ]);
        if (empty($channelsvalue)) {
            return self::get_fallback_reviewers();
        }

        // 解析 channels 值：按 ## 分割为数组
        // 例如："传灯##慈善中心" → ["传灯", "慈善中心"]
        $channelitems = array_values(array_filter(array_map('trim', preg_split('/##/', $channelsvalue))));

        // 标准化回调函数：统一斜杠、去除空格
        $normalizecb = function($v) {
            $v = str_replace(['\\', '／', '＼'], '/', $v);
            $v = preg_replace('/\s+/', '', $v);
            return $v;
        };
        $channelitems = array_values(array_map($normalizecb, $channelitems));
        if (empty($channelitems)) {
            return self::get_fallback_reviewers();
        }

        // 构建用户自定义字段名称：[活动名称] + "审批"
        // 例如：频道数据库审批
        $fieldname = $data->name . '审批';

        // 查找该自定义字段
        $userfield = $DB->get_record('user_info_field', ['name' => $fieldname]);
        if (!$userfield) {
            return self::get_fallback_reviewers();
        }

        // 获取所有用户在该字段上的配置数据
        $userdatas = $DB->get_records('user_info_data', ['fieldid' => $userfield->id]);
        foreach ($userdatas as $userdata) {
            $rawdata = $userdata->data;

            // 如果是 HTML 格式（dataformat=1），需要转换为纯文本
            if ((int)$userdata->dataformat === 1) {
                // 将 <br> 和块级标签转换为换行符
                $rawdata = preg_replace('/<br\s*\/?>/i', "\n", $rawdata);
                $rawdata = preg_replace('/<\/(p|div|li|h[1-6]|tr)>/i', "\n", $rawdata);
                // 移除所有 HTML 标签
                $rawdata = strip_tags($rawdata);
            }

            // 按行分割用户配置（每行一个分组路径）
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $rawdata)));
            $normalizedlines = array_values(array_map($normalizecb, $lines));

            // 精确匹配：用户配置的行值与条目的 channels 值有交集
            $matched = array_intersect($channelitems, $normalizedlines);
            if (!empty($matched)) {
                $reviewerids[] = (int)$userdata->userid;
            }
        }

        // 去重、过滤无效ID
        $reviewerids = array_values(array_unique(array_filter($reviewerids)));
        if (empty($reviewerids)) {
            return self::get_fallback_reviewers();
        }

        // 过滤掉条目创建人（不通知自己）
        $reviewerids = array_values(array_filter($reviewerids, function($id) use ($record) {
            return $id != $record->userid;
        }));

        return $reviewerids;
    }

    /**
     * 获取兜底审核人列表
     *
     * 当无法通过 channels 匹配到审核人时，使用系统管理员作为兜底
     * 这确保了即使配置不完善，通知也不会丢失
     *
     * @return array 管理员ID数组
     */
    private static function get_fallback_reviewers(): array {
        global $DB;

        $reviewerids = [];
        $admins = get_admins();
        foreach ($admins as $admin) {
            $reviewerids[] = (int)$admin->id;
        }

        return array_values(array_unique(array_filter($reviewerids)));
    }

    /**
     * 将通知记录加入队列
     *
     * 在插入前检查是否已存在相同的待发送记录
     * （防止并发事件导致的重复插入）
     *
     * @param array $data 通知记录数据
     */
    private static function enqueue_notification(array $data): void {
        global $DB;

        $data['timecreated'] = time();
        $data['timemodified'] = time();

        // 检查是否已存在相同的待发送记录
        // 唯一键：(component, itemtype, itemid, notiftype, seq, status)
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
            // 唯一键冲突：并发场景下已被其他请求插入，安全跳过
            if (strpos($e->getMessage(), 'uniq_pending') !== false) {
                return;
            }
            throw $e;
        }
    }
}