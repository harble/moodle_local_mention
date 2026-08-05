<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mention\external;

use context;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_mention\local\service\user_search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class search_users extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search query after @'),
            'contextid' => new external_value(PARAM_INT, 'Context id'),
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Max items, capped to 10', VALUE_DEFAULT, 10),
        ]);
    }

    public static function execute(string $query, int $contextid, int $courseid = 0, int $limit = 10): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'contextid' => $contextid,
            'courseid' => $courseid,
            'limit' => $limit,
        ]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);

        require_login();

        $users = user_search::search($params['query'], $context, $params['courseid'], $params['limit']);

        $response = [];
        foreach ($users as $user) {
            $response[] = [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'fullname' => (string)$user['fullname'],
                'email' => (string)$user['email'],
                'display' => (string)$user['fullname'] . ' (@' . (string)$user['username'] . ')',
            ];
        }

        return $response;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User id'),
                'username' => new external_value(PARAM_USERNAME, 'Username'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'email' => new external_value(PARAM_EMAIL, 'Email'),
                'display' => new external_value(PARAM_TEXT, 'Display label'),
            ])
        );
    }
}
