<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_mention\task\queue_processor',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    ],
];