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
        // Chinese variants (zh_cn, zh_tw, etc.) show "评分：", others show "Rating:".
        $lang = current_language();
        if (strpos($lang, 'zh') === 0) {
            $labeltext = '我来评分：';
        } else {
            $labeltext = 'Rating:';
        }

        // Load the AMD module with the replacement text.
        $PAGE->requires->js_call_amd('local_mention/rating_label', 'init', [$labeltext]);
    }
}