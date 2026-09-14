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

    return $result;
}