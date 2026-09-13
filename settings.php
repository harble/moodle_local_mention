<?php
// This file is part of Moodle - http://moodle.org/

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

if ($hassiteconfig) {
    // 将设置添加到"本地插件"分类下
    $settings = new admin_settingpage('local_mention', get_string('pluginname', 'local_mention'));
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
}