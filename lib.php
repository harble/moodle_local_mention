<?php
// This file is part of Moodle - http://moodle.org/

use local_mention\local\service\manager;

defined('MOODLE_INTERNAL') || die();

function local_mention_content_saved(array $payload): array {
    return manager::content_saved($payload);
}
