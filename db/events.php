<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_data\event\record_created',
        'callback' => '\local_mention\observer\database_observer::record_created',
        'priority' => 1000,
    ],
    [
        'eventname' => '\mod_data\event\record_updated',
        'callback' => '\local_mention\observer\database_observer::record_updated',
        'priority' => 1000,
    ],
];