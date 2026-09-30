<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Local mention plugin upgrade steps
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_mention_upgrade($oldversion) {
    global $CFG, $DB;

    $result = true;

    // 从 2026082203 升级：添加提醒间隔和最大通知次数配置项的默认值
    if ($oldversion < 2026091501) {
        // 如果配置项尚未设置，写入默认值
        if (get_config('local_mention', 'reminder_interval') === false) {
            set_config('reminder_interval', 7 * DAYSECS, 'local_mention');
        }
        if (get_config('local_mention', 'max_notifications') === false) {
            set_config('max_notifications', 4, 'local_mention');
        }

        upgrade_plugin_savepoint($result, 2026091501, 'local', 'mention');
    }

    // 从 2026091602 升级：添加聚合浏览计数表 local_mention_entry_views
    if ($oldversion < 2026091603) {
        $table = new xmldb_table('local_mention_entry_views');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('recordid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('viewcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('seedcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('firstviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('recordid-userid', XMLDB_INDEX_UNIQUE, ['recordid', 'userid']);
        $table->add_index('recordid', XMLDB_INDEX_NOTUNIQUE, ['recordid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        $dbman = $DB->get_manager();
        $dbman->create_table($table);

        // 写入配置默认值
        if (get_config('local_mention', 'enablerecordview') === false) {
            set_config('enablerecordview', 1, 'local_mention');
        }
        if (get_config('local_mention', 'viewdedupwindow') === false) {
            set_config('viewdedupwindow', 300, 'local_mention');
        }

        upgrade_plugin_savepoint($result, 2026091603, 'local', 'mention');
    }

    // 从 2026091603 升级：添加显示浏览计数的默认配置
    if ($oldversion < 2026091604) {
        if (get_config('local_mention', 'enablerecordviewdisplay') === false) {
            set_config('enablerecordviewdisplay', 1, 'local_mention');
        }

        upgrade_plugin_savepoint($result, 2026091604, 'local', 'mention');
    }

    // 从 2026091604 升级：添加浏览计数徽章定位选择器配置（默认空 = 插入到记录容器顶部）
    if ($oldversion < 2026091605) {
        if (get_config('local_mention', 'entryviewcounttarget') === false) {
            set_config('entryviewcounttarget', '', 'local_mention');
        }

        upgrade_plugin_savepoint($result, 2026091605, 'local', 'mention');
    }

    return $result;
}