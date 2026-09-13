<?php

namespace local_mention\observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Database 活动模块事件观察者
 *
 * 监听 mod_data\event\record_created 和 record_updated 事件，
 * 实现两大功能：
 *
 * 一、审核通知
 *   当学生提交/更新待审核条目时，自动通知相关审核人员。
 *   核心流程（handle_record_event）：
 *   1. 本地化条目中的外部图片（见功能二）
 *   2. 检查条目是否仍为待审核状态（approved=0）
 *   3. 解析审核人列表（resolve_reviewer_ids）
 *      - 根据条目的 channels 字段值，匹配用户自定义字段中的审核人配置
 *      - 匹配失败则使用系统管理员兜底
 *   4. 更新现有待发送记录（status=0）的审核人列表和内容
 *   5. 退休已发送/已取消的旧记录（seq 设为 -id）
 *   6. 如果没有活跃的 seq=1 记录，则创建新的初始通知
 *
 * 二、外部图片本地化
 *   条目保存时自动提取 textarea 字段 HTML 中的外部 <img> 图片 URL，
 *   下载到 Moodle 文件系统，替换为 @@PLUGINFILE@@ 本地引用。
 *   这样即使原始图片 URL 失效，条目中的图片也不会丢失。
 *   实现方法：localize_external_images()
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

        // 获取课程模块信息
        $cm = $DB->get_record('course_modules', ['id' => $event->contextinstanceid]);
        if (!$cm) {
            return;
        }

        // 本地化条目中的外部图片（所有条目都处理，不限于待审核状态）
        self::localize_external_images($cm, $record);

        // 已审核的条目不再需要通知
        if ((int)$record->approved != 0) {
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

        // 敏感词检查：扫描条目所有 textarea 字段的 HTML 内容
        $matchedwords = self::check_sensitive_words($record, $data);
        $sensitivewarning = '';
        if (!empty($matchedwords)) {
            $sensitivewarning = get_string('sensitive_warning', 'local_mention', implode(', ', $matchedwords));
        }

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
                ]) . $sensitivewarning,
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
                ]) . $sensitivewarning,
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
     * 本地化条目文本域中的外部图片
     *
     * 遍历条目中所有 textarea 类型字段的 HTML 内容，
     * 提取 <img> 标签中的外部图片 URL（排除本站域名），
     * 下载到 Moodle 文件系统，并替换为 @@PLUGINFILE@@ 本地引用。
     *
     * 这样即使原始图片 URL 失效，条目中的图片也不会丢失。
     *
     * @param \stdClass $cm 课程模块记录
     * @param \stdClass $record 条目记录
     */
    private static function localize_external_images(\stdClass $cm, \stdClass $record): void {
        global $DB, $CFG;

        // 获取本站域名，用于排除本站图片
        $sitehost = parse_url($CFG->wwwroot, PHP_URL_HOST);

        // 获取该 Database 活动中所有 textarea 类型的字段
        $fields = $DB->get_records('data_fields', [
            'dataid' => $cm->instance,
            'type' => 'textarea',
        ]);
        if (empty($fields)) {
            return;
        }

        $fieldids = array_keys($fields);

        // 获取该条目在这些字段上的所有内容
        [$insql, $inparams] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED);
        $contents = $DB->get_records_select('data_content',
            "recordid = :recordid AND fieldid $insql",
            array_merge(['recordid' => $record->id], $inparams)
        );
        if (empty($contents)) {
            return;
        }

        $fs = get_file_storage();
        $context = \context_module::instance($cm->id);

        foreach ($contents as $content) {
            if (empty($content->content)) {
                continue;
            }

            $html = $content->content;
            $modified = false;

            // 提取所有 <img> 标签的 src 属性
            // 匹配模式：<img ... src="url" ...>
            if (!preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                continue;
            }

            $urls = $matches[1];  // 所有 src 值
            $fulltags = $matches[0];  // 完整的 <img> 标签

            for ($i = 0; $i < count($urls); $i++) {
                $url = $urls[$i];
                $fulltag = $fulltags[$i];

                // 跳过已经是 @@PLUGINFILE@@ 的本地引用
                if (strpos($url, '@@PLUGINFILE@@') !== false) {
                    continue;
                }

                // 跳过本站域名下的图片
                $urlhost = parse_url($url, PHP_URL_HOST);
                if ($urlhost && strcasecmp($urlhost, $sitehost) === 0) {
                    continue;
                }

                // 跳过非 http/https 协议（data: URI 等）
                if (!preg_match('/^https?:\/\//i', $url)) {
                    continue;
                }

                // 提取文件名（从 URL 路径中获取，处理 query string）
                $urlpath = parse_url($url, PHP_URL_PATH);
                $originalname = $urlpath ? basename($urlpath) : 'image';
                // 确保文件名有合理的扩展名
                if (!preg_match('/\.(jpg|jpeg|png|gif|svg|webp|bmp|ico)$/i', $originalname)) {
                    $originalname .= '.jpg';
                }
                // 加 hash 前缀避免文件名冲突
                $filename = substr(md5($url), 0, 8) . '_' . $originalname;

                try {
                    $filerecord = [
                        'contextid' => $context->id,
                        'component' => 'mod_data',
                        'filearea' => 'content',
                        'itemid' => $content->id,
                        'filepath' => '/',
                        'filename' => $filename,
                    ];

                    $fs->create_file_from_url((object)$filerecord, $url);

                    // 替换 HTML 中的外部 URL 为 @@PLUGINFILE@@ 引用
                    $newtag = str_replace($url, '@@PLUGINFILE@@/' . rawurlencode($filename), $fulltag);
                    $html = str_replace($fulltag, $newtag, $html);
                    $modified = true;
                } catch (\Exception $e) {
                    // 下载失败时保留原始 URL，不中断流程
                    debugging("local_mention: 下载外部图片失败: $url, 错误: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            // 如果有修改，更新数据库
            if ($modified) {
                $DB->update_record('data_content', [
                    'id' => $content->id,
                    'content' => $html,
                ]);
            }
        }
    }

    /**
     * 检查条目内容中是否包含敏感词
     *
     * 读取插件配置中的敏感词列表（每行一个），
     * 遍历条目所有 textarea 字段的 HTML 内容，
     * 去除 HTML 标签后逐词匹配（不区分大小写）。
     *
     * @param \stdClass $record 条目记录
     * @param \stdClass $data Database 活动记录（需含 id）
     * @return array 命中的敏感词数组（无命中则返回空数组）
     */
    public static function check_sensitive_words(\stdClass $record, \stdClass $data): array {
        global $DB;

        // 读取敏感词配置
        $keywordconfig = get_config('local_mention', 'sensitive_keywords');
        if (empty($keywordconfig)) {
            return [];
        }

        // 按行分割，每行一个敏感词
        $keywords = preg_split('/[\r\n]+/', $keywordconfig);
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if (empty($keywords)) {
            return [];
        }

        // 获取该 Database 活动中所有 textarea 类型的字段
        $fields = $DB->get_records('data_fields', [
            'dataid' => $data->id,
            'type' => 'textarea',
        ]);
        if (empty($fields)) {
            return [];
        }

        // 获取该条目在这些字段上的所有内容
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($fields), SQL_PARAMS_NAMED);
        $contents = $DB->get_records_select('data_content',
            "recordid = :recordid AND fieldid $insql",
            array_merge(['recordid' => $record->id], $inparams)
        );
        if (empty($contents)) {
            return [];
        }

        // 拼接所有字段的纯文本
        $fulltext = '';
        foreach ($contents as $content) {
            if (!empty($content->content)) {
                // 将 <br> 等换行标签转换为空格，再去除 HTML 标签
                $text = preg_replace('/<br\s*\/?>/i', "\n", $content->content);
                $text = strip_tags($text);
                $fulltext .= "\n" . $text;
            }
        }

        // 逐词匹配（不区分大小写）
        $matched = [];
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && stripos($fulltext, $keyword) !== false) {
                $matched[] = $keyword;
            }
        }

        return $matched;
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