<?php
// This file is part of Moodle - http://moodle.org/

use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * 获取站点所有 Database 活动列表（用于自动内容审批配置项的下拉选项）
 *
 * @return array 以 data.id 为键、"课程名 / 活动名 (ID: x)" 为值的选项数组
 */
if (!function_exists('local_mention_get_database_activity_options')) {
function local_mention_get_database_activity_options(): array {
    global $DB;
    $options = [];
    $databases = $DB->get_records_sql(
        "SELECT d.id, d.name, c.fullname AS coursename
           FROM {data} d
           JOIN {course} c ON c.id = d.course
          ORDER BY c.fullname, d.name"
    );
    foreach ($databases as $db) {
        $displayname = $db->coursename . ' / ' . s($db->name) . ' (ID: ' . $db->id . ')';
        $options[$db->id] = $displayname;
    }
    return $options;
}
}

// Use the plugin's own capability so settings are visible to both super admins
// and system managers (Manager role), without requiring moodle/site:config.
if (has_capability('local/mention:manage', context_system::instance())) {
    // 将设置添加到"本地插件"分类下
    // 必须传入自定义权限作为第三个参数，否则 admin_settingpage 的 check_access()
    // 会默认检查 moodle/site:config，导致 Manager 角色在导航树中看不到此页面。
    $settings = new admin_settingpage('local_mention', get_string('pluginname', 'local_mention'), 'local/mention:manage');
    $ADMIN->add('localplugins', $settings);

    // 敏感词关键字配置
    // 每行一个关键字，不区分大小写
    $settings->add(new admin_setting_configtextarea(
        'local_mention/sensitive_keywords',
        get_string('sensitive_keywords', 'local_mention'),
        get_string('sensitive_keywords_desc', 'local_mention'),
        '',  // 默认值
        PARAM_RAW,
        '16',  // cols
        '15'   // rows（默认 8 行，调高到 15 行方便查看和编辑）
    ));

    // 自动内容审批配置
    // 选择需要自动内容审批的 Database 活动（多选）
    $settings->add(new admin_setting_configmultiselect(
        'local_mention/auto_approve_databases',
        get_string('auto_approve_databases', 'local_mention'),
        get_string('auto_approve_databases_desc', 'local_mention'),
        [],  // 默认值：空（不启用任何活动）
        local_mention_get_database_activity_options()
    ));

    // 审核提醒间隔
    // 配置审核人收到周期性催办提醒的间隔时间
    $settings->add(new admin_setting_configduration(
        'local_mention/reminder_interval',
        get_string('reminder_interval', 'local_mention'),
        get_string('reminder_interval_desc', 'local_mention'),
        7 * DAYSECS,  // 默认值：7天
        1  // 显示单位选项（1=天、小时、分钟）
    ));

    // 最大审核提醒次数
    // 单个待审核条目最多发送的通知次数（含初始通知）
    $settings->add(new admin_setting_configtext(
        'local_mention/max_notifications',
        get_string('max_notifications', 'local_mention'),
        get_string('max_notifications_desc', 'local_mention'),
        4,  // 默认值：4次
        PARAM_INT,  // 参数类型：整数
        2  // 文本框宽度（字符数）
    ));

    // =========================================================================
    // TinyMCE 图片压缩设置
    // =========================================================================

    // 启用/禁用图片压缩
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enableimagecompress',
        get_string('enableimagecompress', 'local_mention'),
        get_string('enableimagecompress_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 图片最大宽度（像素）
    $settings->add(new admin_setting_configtext(
        'local_mention/imagemaxwidth',
        get_string('imagemaxwidth', 'local_mention'),
        get_string('imagemaxwidth_desc', 'local_mention'),
        1080,   // 默认值
        PARAM_INT
    ));

    // 图片压缩质量
    $settings->add(new admin_setting_configtext(
        'local_mention/imagequality',
        get_string('imagequality', 'local_mention'),
        get_string('imagequality_desc', 'local_mention'),
        0.82,   // 默认值
        PARAM_FLOAT
    ));

    // CDN 域名白名单（每行一个域名）
    // 属于这些域名的外部图片跳过本地化处理，保留远程引用
    $settings->add(new admin_setting_configtextarea(
        'local_mention/cdn_domains',
        get_string('cdn_domains', 'local_mention'),
        get_string('cdn_domains_desc', 'local_mention'),
        '',  // 默认值：空（不设置 CDN 白名单）
        PARAM_RAW,
        '30',  // cols
        '6'    // rows
    ));

    // =========================================================================
    // 其它 hook 功能开关
    // =========================================================================

    // 启用/禁用 Database 条目标签过滤
    // 根据用户是否拥有 mod/data:approve 权限，过滤标签候选项（隐藏/只显示含 "draft" 的标签）
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enabletagfilter',
        get_string('enabletagfilter', 'local_mention'),
        get_string('enabletagfilter_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 启用/禁用评分标签文本替换
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enableratinglabel',
        get_string('enableratinglabel', 'local_mention'),
        get_string('enableratinglabel_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 评分标签中文替换文字
    $settings->add(new admin_setting_configtext(
        'local_mention/ratingchineselabel',
        get_string('ratingchineselabel', 'local_mention'),
        get_string('ratingchineselabel_desc', 'local_mention'),
        '我来评分：',  // 默认值
        PARAM_TEXT
    ));

    // 评分标签英文替换文字
    $settings->add(new admin_setting_configtext(
        'local_mention/ratingenglishlabel',
        get_string('ratingenglishlabel', 'local_mention'),
        get_string('ratingenglishlabel_desc', 'local_mention'),
        'Rating:',  // 默认值
        PARAM_TEXT
    ));

    // 启用/禁用 TinyMCE 图片"仅用于装饰"默认勾选
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enabledecorative',
        get_string('enabledecorative', 'local_mention'),
        get_string('enabledecorative_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 启用/禁用隐藏课程评分组件
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enablehiderating',
        get_string('enablehiderating', 'local_mention'),
        get_string('enablehiderating_desc', 'local_mention'),
        1  // 默认启用
    ));

    // =========================================================================
    // 浏览计数设置
    // =========================================================================

    // 启用/禁用 Database 条目浏览计数
    // 在原生 Database 单条查看页面记录聚合浏览计数
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enablerecordview',
        get_string('enablerecordview', 'local_mention'),
        get_string('enablerecordview_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 启用/禁用显示条目浏览计数
    // 在原生 Database 单条查看页面显示聚合浏览计数
    $settings->add(new admin_setting_configcheckbox(
        'local_mention/enablerecordviewdisplay',
        get_string('enablerecordviewdisplay', 'local_mention'),
        get_string('enablerecordviewdisplay_desc', 'local_mention'),
        1  // 默认启用
    ));

    // 浏览计数徽章插入位置
    // 指定计数徽章插入到哪一个元素之后（CSS 选择器），留空则插入到记录容器顶部
    $settings->add(new admin_setting_configtext(
        'local_mention/entryviewcounttarget',
        get_string('entryviewcounttarget', 'local_mention'),
        get_string('entryviewcounttarget_desc', 'local_mention'),
        '',  // 默认空，插入到容器顶部
        PARAM_RAW,
        60  // 输入框宽度
    ));

    // 浏览去重窗口
    // 同一用户在同一时间窗口内多次查看同一条目只计数一次
    $settings->add(new admin_setting_configduration(
        'local_mention/viewdedupwindow',
        get_string('viewdedupwindow', 'local_mention'),
        get_string('viewdedupwindow_desc', 'local_mention'),
        300,  // 默认值：5分钟
        1  // 显示单位选项（1=天、小时、分钟）
    ));
}