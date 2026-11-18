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
 * Admin web page to batch-add the "Comments" block to courses.
 *
 * Only users with moodle/site:config at system level may access this page.
 * Mutating actions require a valid sesskey (CSRF protection).
 *
 * Access: Site administration > Plugins > Local plugins > Add comments block
 *
 * @package   local_mention
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/blocklib.php');

// PARAM_RAW keeps the empty value as '' (process all courses); PARAM_INT would
// coerce an empty submission to 0, which is then treated as a real course id.
// Multiple ids are allowed as a comma/space separated list, e.g. "5,9,12".
$courseid = optional_param('courseid', '', PARAM_RAW);
$courseid = trim($courseid);
$region   = optional_param('region', 'content-lower', PARAM_ALPHANUMEXT);
$weight   = optional_param('weight', 0, PARAM_INT);
$exclude  = optional_param('exclude', null, PARAM_RAW);
$pattern  = optional_param('pattern', 'course-view-*', PARAM_RAW);
$action   = optional_param('action', '', PARAM_ALPHA);

// First visit (no submitted value): prefill from the persisted setting so the
// latest used exclusion list is shown as the initial value.
if ($exclude === null) {
    $exclude = \local_mention\comments_block_helper::default_exclude();
}

admin_externalpage_setup('local_mention_addcommentsblock');

if ($action) {
    require_sesskey();
    if (!in_array($action, ['preview', 'apply'], true)) {
        throw new moodle_exception('invalidaction', 'local_mention');
    }
}

$formurl = new moodle_url('/local/mention/add_comments_block.php');
$formurl->param('sesskey', sesskey());

$result = null;
if ($action) {
    $dryrun = ($action === 'preview');
    $result = \local_mention\comments_block_helper::add_comments_blocks(
        $courseid,
        $region,
        $weight,
        $pattern,
        $dryrun,
        $exclude
    );
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('add_comments_block', 'local_mention'));

if ($result !== null) {
    if ($result['ok']) {
        $summary = (object)[
            'added' => $result['added'],
            'skipped' => $result['skipped'],
            'showcase' => $result['showcase'],
            'excluded' => $result['excluded'],
            'total' => $result['total'],
        ];
        if ($action === 'preview') {
            echo $OUTPUT->notification(
                get_string('comments_block_preview', 'local_mention', $summary),
                'notifywarning'
            );
        } else {
            echo $OUTPUT->notification(
                get_string('comments_block_done', 'local_mention', $summary),
                'notifysuccess'
            );
        }
        if (!empty($result['details'])) {
            $rows = array_map(function ($detail) {
                return [$detail];
            }, $result['details']);
            echo html_writer::table(new html_table([
                'data' => $rows,
                'attributes' => ['class' => 'generaltable'],
            ]));
        }
    } else {
        echo $OUTPUT->notification($result['message'], 'notifyerror');
    }
    echo html_writer::link(
        $formurl,
        get_string('comments_block_back', 'local_mention'),
        ['class' => 'btn btn-primary']
    );
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('add_comments_block_desc', 'local_mention'));
echo $OUTPUT->notification(get_string('comments_block_page_note', 'local_mention'), 'info');

// Prominent help block: opening the page never performs any bulk action.
$helpitems = [
    get_string('comments_block_help_1', 'local_mention'),
    get_string('comments_block_help_2', 'local_mention'),
    get_string('comments_block_help_3', 'local_mention'),
    get_string('comments_block_help_4', 'local_mention'),
    get_string('comments_block_help_5', 'local_mention'),
];
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('comments_block_help_title', 'local_mention'));
echo html_writer::alist($helpitems);
echo html_writer::end_div();
echo html_writer::end_div();

$formattrs = ['method' => 'post', 'action' => $formurl->out(false)];
echo html_writer::start_tag('form', $formattrs);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->attributes['class'] = 'generaltable';

$table->data = [
    [
        html_writer::label(get_string('comments_block_course', 'local_mention'), 'id-courseid'),
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'courseid',
            'id' => 'id-courseid',
            'value' => $courseid,
            'size' => 30,
        ]),
    ],
    [
        html_writer::label(get_string('comments_block_region', 'local_mention'), 'id-region'),
        html_writer::select(
            ['content-lower' => 'content-lower', 'content' => 'content', 'side-pre' => 'side-pre', 'side-post' => 'side-post'],
            'region',
            $region,
            null,
            ['id' => 'id-region']
        ),
    ],
    [
        html_writer::label(get_string('comments_block_weight', 'local_mention'), 'id-weight'),
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'weight',
            'id' => 'id-weight',
            'value' => $weight,
            'size' => 4,
        ]),
    ],
    [
        html_writer::label(get_string('comments_block_pattern', 'local_mention'), 'id-pattern'),
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'pattern',
            'id' => 'id-pattern',
            'value' => $pattern,
        ]),
    ],
    [
        html_writer::label(get_string('comments_block_exclude', 'local_mention'), 'id-exclude'),
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'exclude',
            'id' => 'id-exclude',
            'value' => $exclude,
            'size' => 30,
        ]),
    ],
];
echo html_writer::table($table);

echo html_writer::start_div('form-buttons');
echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'action', 'value' => 'preview',
    'class' => 'btn btn-secondary me-1']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'action', 'value' => 'apply',
    'class' => 'btn btn-primary']);
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $OUTPUT->footer();