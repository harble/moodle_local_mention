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
);
