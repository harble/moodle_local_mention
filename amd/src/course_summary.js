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
 * Moves the auto-injected course summary block into the course section list.
 *
 * The course summary is injected through the
 * after_standard_main_region_html_generation hook, which places it *after*
 * the main region content. This module relocates that block into the native
 * section list container ([data-for="course_sectionlist"]) as its very first
 * item, so it renders exactly like a real course section.
 *
 * When the native section list cannot be found (e.g. unusual course format),
 * the block is moved to the very top of #region-main instead.
 *
 * The module is passive - it only acts when both the block and a target
 * container exist on the current page.
 *
 * @module     local_mention/course_summary
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const init = () => {
    const block = document.querySelector('.course-summary-block');
    if (!block) {
        return;
    }

    // Prefer the native section list: insert the block as its first section.
    const sectionList = document.querySelector('[data-for="course_sectionlist"]');
    if (sectionList) {
        if (sectionList.firstElementChild !== block) {
            sectionList.insertBefore(block, sectionList.firstChild);
        }
        return;
    }

    // Fall back to the top of the main content region.
    const region = document.getElementById('region-main');
    if (region && region.firstElementChild !== block) {
        region.insertBefore(block, region.firstChild);
    }
};