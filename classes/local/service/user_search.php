<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\local\service;

use context;

defined('MOODLE_INTERNAL') || die();

class user_search {
    public static function search(string $query, context $context, int $courseid = 0, int $limit = 10,
            bool $searchallusers = false): array {
        global $DB;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));

        if ($searchallusers) {
            return self::search_all_users($query, $limit);
        }

        $scopecontext = self::resolve_scope_context($context, $courseid);
        if (!$scopecontext) {
            return [];
        }

        list($esql, $params) = get_enrolled_sql($scopecontext, '', 0, true);

        $params['q1'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q2'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q3'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q4'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q5'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q6'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q7'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['q8'] = '%' . $DB->sql_like_escape($query) . '%';

        $like = $DB->sql_like('u.username', ':q1', false, false)
            . ' OR ' . $DB->sql_like('u.firstname', ':q2', false, false)
            . ' OR ' . $DB->sql_like('u.lastname', ':q3', false, false)
            . ' OR ' . $DB->sql_like('u.email', ':q4', false, false)
            . ' OR ' . $DB->sql_like('u.middlename', ':q5', false, false)
            . ' OR ' . $DB->sql_like('u.alternatename', ':q6', false, false)
            . ' OR ' . $DB->sql_like($DB->sql_concat('u.lastname', "' '", 'u.firstname'), ':q7', false, false)
            . ' OR ' . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':q8', false, false);

        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {user} u
                  JOIN ($esql) je ON je.id = u.id
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND ($like)
              ORDER BY u.lastname ASC, u.firstname ASC, u.username ASC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        if (empty($records)) {
            return [];
        }

        $response = [];
        foreach ($records as $record) {
            $response[] = [
                'id' => (int)$record->id,
                'username' => (string)$record->username,
                'fullname' => fullname($record, true),
                'email' => (string)$record->email,
            ];
        }

        return $response;
    }

    private static function search_all_users(string $query, int $limit): array {
        global $DB;

        if (\core_text::strlen($query) < 2) {
            return [];
        }

        $fields = [
            'u.username',
            'u.firstname',
            'u.lastname',
            'u.email',
            'u.alternatename',
            'u.middlename',
        ];

        $records = self::run_staged_prefix_search($fields, $query, $limit);
        return self::format_user_records($records);
    }

    public static function resolve_users_by_usernames(array $usernames, context $context, int $courseid = 0,
            bool $searchallusers = false): array {
        global $DB;

        if (empty($usernames)) {
            return [];
        }

        $params = [];
        $joinsql = '';
        if (!$searchallusers) {
            $scopecontext = self::resolve_scope_context($context, $courseid);
            if (!$scopecontext) {
                return [];
            }

            list($esql, $params) = get_enrolled_sql($scopecontext, '', 0, true);
            $joinsql = "JOIN ($esql) je ON je.id = u.id";
        }

        $orparts = [];
        $index = 0;
        foreach ($usernames as $username) {
            $paramkey = 'uname' . $index;
            $orparts[] = $DB->sql_equal('u.username', ':' . $paramkey, false, true);
            $params[$paramkey] = $username;
            $index++;
        }

        if (empty($orparts)) {
            return [];
        }

        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {user} u
                                    $joinsql
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND (" . implode(' OR ', $orparts) . ')';

        $records = $DB->get_records_sql($sql, $params);
        if (empty($records)) {
            return [];
        }

        $mapped = [];
        foreach ($records as $record) {
            $mapped[\core_text::strtolower($record->username)] = $record;
        }

        return $mapped;
    }

    public static function resolve_users_by_ids(array $userids, context $context, int $courseid = 0,
            bool $searchallusers = false): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        $params = [];
        $joinsql = '';
        if (!$searchallusers) {
            $scopecontext = self::resolve_scope_context($context, $courseid);
            if (!$scopecontext) {
                return [];
            }

            list($esql, $params) = get_enrolled_sql($scopecontext, '', 0, true);
            $joinsql = "JOIN ($esql) je ON je.id = u.id";
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);

        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {user} u
                  $joinsql
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND u.id $insql";

        $records = $DB->get_records_sql($sql, $params);
        if (empty($records)) {
            return [];
        }

        $mapped = [];
        foreach ($records as $record) {
            $mapped[(int)$record->id] = $record;
        }

        return $mapped;
    }

    private static function run_staged_prefix_search(array $fields, string $query, int $limit): array {
        global $DB;

        $candidates = [];
        $maxperfield = max($limit * 2, 20);
        $prefix = $DB->sql_like_escape($query) . '%';

        foreach ($fields as $index => $field) {
            if (count($candidates) >= $limit) {
                break;
            }

            $paramkey = 'q' . $index;
            $params = [$paramkey => $prefix];
            $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                           u.middlename, u.alternatename, u.email
                      FROM {user} u
                     WHERE u.deleted = 0
                       AND u.suspended = 0
                       AND " . $DB->sql_like($field, ':' . $paramkey, false, false) . "
                  ORDER BY u.lastname ASC, u.firstname ASC, u.username ASC";

            $records = $DB->get_records_sql($sql, $params, 0, $maxperfield);
            foreach ($records as $record) {
                $candidates[(int)$record->id] = $record;
                if (count($candidates) >= $limit) {
                    break;
                }
            }
        }

        return array_slice(array_values($candidates), 0, $limit);
    }

    private static function format_user_records(array $records): array {
        $response = [];
        foreach ($records as $record) {
            $response[] = [
                'id' => (int)$record->id,
                'username' => (string)$record->username,
                'fullname' => fullname($record, true),
                'email' => (string)$record->email,
            ];
        }

        return $response;
    }

    private static function resolve_scope_context(context $context, int $courseid = 0): ?context {
        if ($courseid > 0) {
            return \context_course::instance($courseid, IGNORE_MISSING);
        }

        if ($context->contextlevel === CONTEXT_COURSE) {
            return $context;
        }

        if ($context->contextlevel === CONTEXT_MODULE) {
            global $DB;
            $courseid = $DB->get_field('course_modules', 'course', ['id' => $context->instanceid]);
            if ($courseid) {
                return \context_course::instance((int)$courseid, IGNORE_MISSING);
            }
        }

        return null;
    }
}
