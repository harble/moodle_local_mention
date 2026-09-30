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
 * Display the aggregated view counter on the native mod_data single-record page.
 *
 * The counter value is computed server side and passed in via init(). This
 * module injects a small badge at the top of the #data-singleview-content
 * container so it is visible without modifying any core template.
 *
 * @module     local_mention/entry_view_count
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {string} label The already-localised counter label, e.g. "123 次浏览".
 * @param {string} targetSelector Optional CSS selector of the element after which
 *     the badge should be inserted. When empty, the badge is prepended to the
 *     top of the #data-singleview-content container.
 */
export const init = (label, targetSelector) => {
    if (!label) {
        return;
    }

    const container = document.querySelector('#data-singleview-content');
    if (!container) {
        return;
    }

    // Avoid duplicating the badge if init is somehow called more than once.
    if (container.querySelector('.local-mention-entry-view-count')) {
        return;
    }

    const badge = document.createElement('div');
    badge.className = 'local-mention-entry-view-count';
    badge.textContent = label;
    badge.setAttribute('role', 'status');
    badge.setAttribute('aria-live', 'polite');

    let anchor = null;
    if (targetSelector) {
        anchor = container.querySelector(targetSelector) || document.querySelector(targetSelector);
    }

    if (anchor) {
        anchor.insertAdjacentElement('afterend', badge);
    } else {
        // Fallback: prepend to the top of the entry container.
        container.prepend(badge);
    }
};