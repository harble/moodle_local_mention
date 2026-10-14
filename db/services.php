<?php
// This file is part of Moodle - http://moodle.org/

$functions = array(
    'local_mention_search_users' => array(
        'classname' => 'local_mention\\external\\search_users',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Search mention candidates in a context/course scope',
        'type' => 'read',
        'ajax' => true,
    ),
    'local_mention_log_view' => array(
        'classname' => 'local_mention\\external\\log_view',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Log a database record view for aggregated view counting',
        'type' => 'write',
        'ajax' => true,
    ),
    'local_mention_check_approval_permission' => array(
        'classname' => 'local_mention\\external\\approval_permissions',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Return the subset of database records the current user is allowed to approve',
        'type' => 'read',
        'ajax' => true,
    ),
);