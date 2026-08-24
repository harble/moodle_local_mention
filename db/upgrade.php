<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_local_mention_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026082201) {
        $table = new xmldb_table('local_mention_notify_queue');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('itemtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('userfrom', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('userto', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('notiftype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('seq', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('scheduledtime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('senttime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('retrycount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('scheduledtime', XMLDB_INDEX_NOTUNIQUE, ['scheduledtime']);
            $table->add_index('userto', XMLDB_INDEX_NOTUNIQUE, ['userto']);
            $table->add_index('compitem', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemtype', 'itemid']);
            $table->add_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'userto', 'notiftype', 'status']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082201, 'local', 'mention');
    }

    if ($oldversion < 2026082202) {
        $table = new xmldb_table('local_mention_notify_queue');

        if ($dbman->table_exists($table)) {
            $oldindex = new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'userto', 'notiftype', 'status']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }

            $usertoindex = new xmldb_index('userto', XMLDB_INDEX_NOTUNIQUE, ['userto']);
            if ($dbman->index_exists($table, $usertoindex)) {
                $dbman->drop_index($table, $usertoindex);
            }

            $field = new xmldb_field('userto', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_type($table, $field);

            $newindex = new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'notiftype', 'seq', 'status']);
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026082202, 'local', 'mention');
    }

    if ($oldversion < 2026082203) {
        $table = new xmldb_table('local_mention_notify_queue');

        if ($dbman->table_exists($table)) {
            $oldindexes = [
                new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'notiftype', 'seq', 'status']),
                new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'notiftype', 'seq']),
                new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'userto', 'notiftype', 'status']),
            ];
            foreach ($oldindexes as $oldindex) {
                if ($dbman->index_exists($table, $oldindex)) {
                    $dbman->drop_index($table, $oldindex);
                }
            }

            $usertoindex = new xmldb_index('userto', XMLDB_INDEX_NOTUNIQUE, ['userto']);
            if ($dbman->index_exists($table, $usertoindex)) {
                $dbman->drop_index($table, $usertoindex);
            }

            $duplicates = $DB->get_records_sql("SELECT id FROM {local_mention_notify_queue} WHERE id NOT IN (
                SELECT mx.maxid FROM (
                    SELECT MAX(id) AS maxid FROM {local_mention_notify_queue} GROUP BY component, itemtype, itemid, notiftype, seq
                ) AS mx
            )");
            foreach ($duplicates as $dup) {
                $DB->delete_records('local_mention_notify_queue', ['id' => $dup->id]);
            }

            $newindex = new xmldb_index('uniq_pending', XMLDB_INDEX_UNIQUE, ['component', 'itemtype', 'itemid', 'notiftype', 'seq']);
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026082203, 'local', 'mention');
    }

    return true;
}