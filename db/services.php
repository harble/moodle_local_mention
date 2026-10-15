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
    'local_mention_get_rejections' => array(
        'classname' => 'local_mention\\external\\approval_reject',
        'methodname' => 'get_rejections',
        'classpath' => '',
        'description' => 'Return the rejection history for a database record',
        'type' => 'read',
        'ajax' => true,
    ),
    'local_mention_reject_record' => array(
        'classname' => 'local_mention\\external\\approval_reject',
        'methodname' => 'reject_record',
        'classpath' => '',
        'description' => 'Reject a database record and notify its author',
        'type' => 'write',
        'ajax' => true,
    ),
);