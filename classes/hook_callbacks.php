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

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for local_mention.
 *
 * @package    local_mention
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Callback for before_footer_html_generation hook.
     *
     * Loads the data_tag_filter AMD module on Database activity add/edit entry pages
     * to filter tag options based on the user's mod/data:approve capability.
     *
     * @param before_footer_html_generation $hook The hook instance.
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $USER;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enabletagfilter');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        // Only process module contexts.
        if ($PAGE->context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        // Ensure we have a course module.
        if (!$PAGE->cm) {
            return;
        }

        // Only apply to Database activity (mod_data).
        if ($PAGE->cm->modname !== 'data') {
            return;
        }

        // Determine if the user has approve capability using context-based permission check.
        // This is the authoritative check - no role ID, role name, or DOM guessing.
        $canapprove = has_capability('mod/data:approve', $PAGE->context);

        // Load the AMD module with the capability flag.
        $PAGE->requires->js_call_amd('local_mention/data_tag_filter', 'init', [$canapprove]);
    }

    /**
     * Callback for before_footer_html_generation hook.
     *
     * Replaces the rating aggregate label on Database activity record view pages.
     * Changes "Average of ratings"/"平均分" to "Rating"/"评分" without modifying core.
     *
     * @param before_footer_html_generation $hook The hook instance.
     */
    public static function before_footer_html_generation_rating_label(before_footer_html_generation $hook): void {
        global $PAGE;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enableratinglabel');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        // Only process module contexts.
        if ($PAGE->context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        // Ensure we have a course module.
        if (!$PAGE->cm) {
            return;
        }

        // Only apply to Database activity (mod_data).
        if ($PAGE->cm->modname !== 'data') {
            return;
        }

        // Determine the desired label text based on current language.
        // Chinese variants (zh_cn, zh_tw, etc.) use the Chinese label, others use the English label.
        $lang = current_language();
        if (strpos($lang, 'zh') === 0) {
            $labeltext = trim((string)(get_config('local_mention', 'ratingchineselabel') ?: '我来评分：'));
        } else {
            $labeltext = trim((string)(get_config('local_mention', 'ratingenglishlabel') ?: 'Rating:'));
        }

        // Load the AMD module with the replacement text.
        $PAGE->requires->js_call_amd('local_mention/rating_label', 'init', [$labeltext]);
    }

    /**
     * Callback for before_footer_html_generation hook.
     *
     * Defaults the TinyMCE image dialog's "decorative" checkbox to checked,
     * so users don't have to fill in alt text when inserting images.
     * Loaded on all pages — the MutationObserver is passive when no dialog exists.
     *
     * @param before_footer_html_generation $hook The hook instance.
     */
    public static function before_footer_html_generation_tiny_image_decorative(
        before_footer_html_generation $hook
    ): void {
        global $PAGE;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enabledecorative');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        $PAGE->requires->js_call_amd('local_mention/tiny_image_decorative', 'init');
    }

    /**
     * Callback for before_footer_html_generation hook.
     *
     * Loads the TinyMCE image compression AMD module on all pages that
     * include a TinyMCE editor. The module intercepts image uploads,
     * drag-and-drop, and paste at the browser level, compressing
     * JPEG/PNG/WebP images before they reach the Moodle draft area.
     *
     * @param before_footer_html_generation $hook The hook instance.
     */
    public static function before_footer_html_generation_tiny_image_compress(
        before_footer_html_generation $hook
    ): void {
        global $PAGE;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enableimagecompress');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        $maxWidth = (int)(get_config('local_mention', 'imagemaxwidth') ?: 1080);
        $quality  = (float)(get_config('local_mention', 'imagequality') ?: 0.82);

        $PAGE->requires->js_call_amd(
            'local_mention/tiny_image_compress',
            'init',
            [$maxWidth, $quality]
        );
    }

    /**
     * Callback for before_standard_head_html_generation hook.
     *
     * Hides the tool_courserating rating widget on courses that contain a
     * Database activity (mod_data). This prevents the course rating block
     * from appearing when the course uses Database for its own rating system.
     *
     * @param before_standard_head_html_generation $hook The hook instance.
     */
    public static function before_standard_head_html_generation(
        before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enablehiderating');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        // Must have a course context (not module, not system).
        if (!$PAGE->course || $PAGE->course->id <= 0) {
            return;
        }

        // Skip the site front page.
        if ($PAGE->course->id == SITEID) {
            return;
        }

        // Use cached course module info to efficiently check for Database activities.
        // get_fast_modinfo() uses the module cache, so this is not a DB query.
        $modinfo = get_fast_modinfo($PAGE->course->id);
        $datainstances = $modinfo->get_instances_of('data');

        // Only hide the course rating widget when the course has exactly one
        // Database activity. This covers the typical "use Database as a rating
        // tool" scenario while keeping the widget visible on courses with
        // multiple activities or zero data instances.
        if (count($datainstances) === 1) {
            // Hide the course rating widget via CSS injected in <head>.
            // This runs before the widget is rendered, so no FOUC/flash.
            $hook->add_html(
                '<style>.tool_courserating-widget { display: none !important; }</style>'
            );
        }
    }

    /**
     * Callback for before_standard_head_html_generation hook.
     *
     * Loads the entry_view AMD module on native Database activity single-record
     * view pages (/mod/data/view.php?rid=X). The module logs a view against the
     * aggregated counter table so browsing across all entry points is counted.
     *
     * @param before_standard_head_html_generation $hook The hook instance.
     */
    public static function before_standard_head_html_generation_entry_view(
        before_standard_head_html_generation $hook
    ): void {
        global $PAGE, $DB;

        // Respect the admin setting.
        $enable = get_config('local_mention', 'enablerecordview');
        if ($enable === false || (bool)$enable === false) {
            return;
        }

        // Only operate on the native Database activity view page.
        if (strpos($PAGE->url->out(), '/mod/data/view.php') === false) {
            return;
        }

        // A single-record view always carries a rid parameter.
        $rid = optional_param('rid', 0, PARAM_INT);
        if ($rid <= 0) {
            return;
        }

        // Keep the record info columns (user/added, last edited, actions) on a
        // single row on small screens (<768px). The default template stacks the
        // "actions" column below once the middle column is widened, so we switch
        // the flex container to nowrap and let the time-info column take up the
        // remaining space. This overrides the core Bootstrap grid via CSS only.
        $hook->add_html(
            '<style>
                @media (max-width: 767.98px) {
                    #data-singleview-content .row.h-100 {
                        flex-wrap: nowrap !important;
                    }
                    #data-singleview-content .row.h-100 > .col-auto,
                    #data-singleview-content .row.h-100 > .col-3,
                    #data-singleview-content .row.h-100 > .col-4.col-md-3.ms-auto {
                        flex: 0 1 auto !important;
                        max-width: none !important;
                        min-width: 0 !important;
                    }
                    #data-singleview-content .row.h-100 > .col-4.col-md-6.text-end.align-self-center.data-timeinfo {
                        flex: 1 1 55% !important;
                        max-width: none !important;
                        min-width: 0 !important;
                        white-space: nowrap !important;
                        text-align: right !important;
                        justify-content: flex-end !important;
                    }
                }
            </style>'
        );

        // Register the view asynchronously.
        $PAGE->requires->js_call_amd('local_mention/entry_view', 'init', [$rid]);

        // Show the aggregated view counter on the single-record view.
        $enabledisplay = get_config('local_mention', 'enablerecordviewdisplay');
        if ($enabledisplay === false || (bool)$enabledisplay === true) {
            $count = 0;
            $total = $DB->get_record_sql(
                "SELECT SUM(viewcount + seedcount) AS total
                   FROM {local_mention_entry_views}
                  WHERE recordid = ?",
                [$rid]
            );
            if ($total) {
                $count = (int)$total->total;
            }

            if ($count > 0) {
                $label = get_string('viewcount', 'local_mention', $count);
                $target = get_config('local_mention', 'entryviewcounttarget');
                $PAGE->requires->js_call_amd('local_mention/entry_view_count', 'init', [$label, $target]);
            }
        }

        // Re-format the ##timeadded## / ##timemodified## labels on the
        // single-record view using the configured date format (no core change).
        $dateformat = (string)get_config('local_mention', 'entryviewdateformat');
        if ($dateformat !== '') {
            $record = $DB->get_record('data_records', ['id' => $rid], 'id,timecreated,timemodified');
            if ($record) {
                // Build a list of {title, text} pairs. The title attribute
                // keeps the original full date/time produced by userdate(),
                // which acts as a stable match anchor in the DOM, while "text"
                // is the target formatted label we want to display.
                $items = [];
                $timecreated = (int)$record->timecreated;
                $timemodified = !empty($record->timemodified) ? (int)$record->timemodified : 0;

                if ($timecreated > 0) {
                    $items[] = [
                        'title' => userdate($timecreated),
                        'text' => userdate($timecreated, $dateformat),
                    ];
                }
                if ($timemodified > 0) {
                    $items[] = [
                        'title' => userdate($timemodified),
                        'text' => userdate($timemodified, $dateformat),
                    ];
                }

                if (!empty($items)) {
                    $PAGE->requires->js_call_amd('local_mention/entry_dateformat', 'init', [$items]);
                }
            }
        }
    }
}