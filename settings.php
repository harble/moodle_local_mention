<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

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
}