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

namespace local_mention;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper used by both the CLI script and the admin web page to add the
 * "Comments" block to courses.
 *
 * @package   local_mention
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comments_block_helper {

    /**
     * Return a single Course object (or all real courses) to process.
     *
     * Accepts a single id, an array of ids, or a comma/whitespace separated
     * list like "5,9,12". Non-existent ids cause an error; SITEID is always
     * skipped when mixed with other ids (or reported when it is the only one).
     *
     * @param string|int|array|null $courseid Optional course id or list.
     * @return array{ok: bool, message: string, courses: int[]}
     */
    public static function resolve_courses($courseid = null): array {
        global $DB;

        if ($courseid !== null && $courseid !== '') {
            // Normalise to a flat list of ints, supporting comma separated input.
            $ids = [];
            if (is_array($courseid)) {
                $ids = array_map('intval', $courseid);
            } else {
                foreach (preg_split('/[\s,]+/', (string)$courseid, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (is_numeric($part)) {
                        $ids[] = (int)$part;
                    }
                }
            }
            if (!$ids) {
                return ['ok' => false, 'message' => get_string('course_not_found', 'local_mention', $courseid), 'courses' => []];
            }

            // Validate each requested id and drop the site home from the list.
            $valid = [];
            foreach (array_unique($ids) as $id) {
                $course = $DB->get_record('course', ['id' => $id], 'id');
                if (!$course) {
                    return ['ok' => false, 'message' => get_string('course_not_found', 'local_mention', (string)$id),
                        'courses' => []];
                }
                if ((int)$course->id === SITEID) {
                    continue; // Site home is not a real course; skip silently in multi id lists.
                }
                $valid[] = $id;
            }
            if (!$valid) {
                return ['ok' => false, 'message' => get_string('sitehome_not_course', 'local_mention'), 'courses' => []];
            }
            return ['ok' => true, 'message' => '', 'courses' => $valid];
        }

        $courses = [];
        $rs = $DB->get_recordset('course', null, '', 'id');
        foreach ($rs as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $courses[] = (int)$course->id;
        }
        $rs->close();
        return ['ok' => true, 'message' => '', 'courses' => $courses];
    }

    /**
     * Check whether the given course has at least one Comments block instance
     * placed in the "content" region.
     *
     * @param int $courseid
     * @return bool
     */
    public static function has_content_region_comments(int $courseid): bool {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        return (bool) $DB->get_record('block_instances', [
            'blockname' => 'comments',
            'parentcontextid' => $coursecontext->id,
            'defaultregion' => 'content',
        ]);
    }

    /**
     * Check whether the given course already has at least one "Showcase" block
     * (block_vitrina or its derivative block_vitrinadb).
     *
     * Courses with a Showcase block are skipped by the batch process so that the
     * Comments block is not injected into pages that expose their own showcase
     * layout.
     *
     * @param int $courseid
     * @return bool
     */
    public static function has_showcase_block(int $courseid): bool {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        list($insql, $inparams) = $DB->get_in_or_equal(['vitrina', 'vitrinadb'], SQL_PARAMS_NAMED);
        return (bool) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_instances}
              WHERE parentcontextid = :ctxid AND blockname {$insql}",
            array_merge(['ctxid' => $coursecontext->id], $inparams)
        );
    }

    /**
     * The persisted default exclusion list.
     *
     * Falls back to the built-in defaults (33,35,36,654) when the setting has
     * never been saved; an explicitly saved empty string stays empty (meaning
     * "exclude nothing").
     *
     * @return string
     */
    public static function default_exclude(): string {
        $value = get_config('local_mention', 'comments_exclude');
        // get_config() returns false when the setting was never written.
        if ($value === false) {
            return '33,35,36,654';
        }
        return (string)$value;
    }

    /**
     * Parse a comma separated (or array) list of course ids into a clean array
     * of ints, ignoring empty/garbage entries.
     *
     * @param string|array $ids
     * @return int[]
     */
    public static function parse_course_ids($ids): array {
        if (is_array($ids)) {
            $ids = implode(',', $ids);
        }
        $result = [];
        foreach (explode(',', (string)$ids) as $id) {
            $id = trim($id);
            if ($id === '' || !ctype_digit($id)) {
                continue;
            }
            $result[] = (int)$id;
        }
        return array_values(array_unique($result));
    }

    /**
     * Add the Comments block to the given course(s).
     *
     * @param string|int|null $courseid Single course id, or null for all courses.
     * @param string $region Block region.
     * @param int $weight Block ordering weight.
     * @param string $pattern Pagetype pattern.
     * @param bool $dryrun Only simulate.
     * @param string|array|null $exclude Course ids to always skip (comma separated or array).
     *                                   Null uses/persists the stored default.
     * @return array{ok: bool, message: string, added: int, skipped: int, showcase: int, excluded: int,
     *               total: int, details: string[]}
     */
    public static function add_comments_blocks($courseid = null, $region = 'content-lower', $weight = 0,
            $pattern = 'course-view-*', $dryrun = false, $exclude = null): array {

        global $DB;

        // Sanity checks on the Comments block plugin.
        $blockrecord = $DB->get_record('block', ['name' => 'comments']);
        if (!$blockrecord) {
            return ['ok' => false, 'message' => get_string('comments_block_missing', 'local_mention'), 'added' => 0,
                'skipped' => 0, 'showcase' => 0, 'excluded' => 0, 'total' => 0, 'details' => []];
        }
        if (empty($blockrecord->visible)) {
            return ['ok' => false, 'message' => get_string('comments_block_disabled', 'local_mention'), 'added' => 0,
                'skipped' => 0, 'showcase' => 0, 'excluded' => 0, 'total' => 0, 'details' => []];
        }

        $resolved = self::resolve_courses($courseid);
        if (!$resolved['ok']) {
            return ['ok' => false, 'message' => $resolved['message'], 'added' => 0, 'skipped' => 0, 'showcase' => 0,
                'excluded' => 0, 'total' => 0, 'details' => []];
        }

        // When no explicit value was supplied, use the stored default. Explicit
        // empty/whitespace values mean "exclude nothing" and are persisted as-is
        // so that the next visit keeps the same behaviour.
        if ($exclude === null) {
            $exclude = self::default_exclude();
        }
        // Persist the latest exclusion list so future visits use it as the initial value.
        set_config('comments_exclude', $exclude, 'local_mention');

        // Normalise the exclusion list once up front.
        $excludedids = self::parse_course_ids($exclude);

        $added = 0;
        $skipped = 0;
        $showcase = 0;
        $excluded = 0;
        $details = [];

        foreach ($resolved['courses'] as $id) {
            $coursecontext = \context_course::instance($id);

            // Skip courses listed in the exclusion parameter.
            if (in_array($id, $excludedids, true)) {
                $excluded++;
                $details[] = get_string('comments_block_skipped_excluded', 'local_mention', $id);
                continue;
            }

            // Skip courses that already expose a Showcase block so that the
            // Comments block never fights with the showcase layout.
            if (self::has_showcase_block($id)) {
                $showcase++;
                $details[] = get_string('comments_block_skipped_showcase', 'local_mention', $id);
                continue;
            }

            $existing = $DB->count_records('block_instances', [
                'blockname' => 'comments',
                'parentcontextid' => $coursecontext->id,
                'pagetypepattern' => $pattern,
            ]);
            if ($existing > 0) {
                $skipped++;
                $details[] = get_string('comments_block_skipped', 'local_mention', $id);
                continue;
            }

            if ($dryrun) {
                $added++;
                $details[] = get_string('comments_block_dryrun', 'local_mention', $id);
                continue;
            }

            $page = new \moodle_page();
            $page->set_context($coursecontext);
            $page->blocks->add_region($region);
            $page->blocks->add_block('comments', $region, $weight, false, $pattern);
            $added++;
            $details[] = get_string('comments_block_added', 'local_mention', $id);
        }

        return [
            'ok' => true,
            'message' => '',
            'added' => $added,
            'skipped' => $skipped,
            'showcase' => $showcase,
            'excluded' => $excluded,
            'total' => count($resolved['courses']),
            'details' => $details,
        ];
    }
}