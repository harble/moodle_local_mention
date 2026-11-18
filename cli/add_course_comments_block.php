<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Batch-adds the "Comments" block (block_comments) to every course.
 *
 * The script is idempotent: courses that already have a comments block
 * instance for the given pagetype pattern are skipped. The actual logic lives
 * in \local_mention\comments_block_helper, shared with the admin web page
 * (/local/mention/add_comments_block.php).
 *
 * Usage:
 *   php local/mention/cli/add_course_comments_block.php
 *   php local/mention/cli/add_course_comments_block.php --courseid=5
 *   php local/mention/cli/add_course_comments_block.php --courseid=5,9,12
 *   php local/mention/cli/add_course_comments_block.php --region=side-post
 *   php local/mention/cli/add_course_comments_block.php --dryrun
 *
 * @package   local_mention
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/blocklib.php');

list($options, $unrecognized) = cli_get_params([
    'courseid' => null,
    'region' => 'content-lower',
    'weight' => 0,
    'pattern' => 'course-view-*',
    'exclude' => null,
    'dryrun' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Batch-adds the Comments block to every course.

Options:
  --courseid=ID     Add the block to the given course(s); comma separated ids are accepted,
                    e.g. --courseid=5,9,12 (useful for testing).
  --region=REGION   Target block region (side-pre, side-post, content-lower...). Default: content-lower.
  --weight=N        Block weight/ordering inside the region. Default: 0.
  --pattern=STR     Pagetype pattern the block applies to. Default: course-view-*.
  --exclude=IDS     Comma separated course ids to always skip.
                    Default: read from the saved setting (local_mention/comments_exclude), i.e. 33,35,36,654.
  --dryrun          Only report what would be done, without writing anything.
  -h, --help        Print this help.

Runs from the web/CLI: php local/mention/cli/add_course_comments_block.php [options]
Example: php local/mention/cli/add_course_comments_block.php --courseid=5
EOT;
    echo $help;
    exit(0);
}

$result = \local_mention\comments_block_helper::add_comments_blocks(
    $options['courseid'],
    $options['region'],
    (int)$options['weight'],
    $options['pattern'],
    !empty($options['dryrun']),
    $options['exclude']
);

if (!$result['ok']) {
    cli_error($result['message']);
}

foreach ($result['details'] as $detail) {
    mtrace('  ' . $detail);
}

mtrace('');
mtrace("Done: {$result['added']} added, {$result['skipped']} already present, "
    . "{$result['showcase']} skipped (Showcase), {$result['excluded']} excluded, "
    . "{$result['total']} courses scanned.");

exit(0);