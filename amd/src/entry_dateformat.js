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
 * Re-format the ##timeadded## / ##timemodified## labels on the native mod_data
 * single-record view page.
 *
 * The core renders these tags as <span title="full date/time">short text</span>.
 * The title attribute (original userdate() output) is used as a stable match
 * anchor so we can replace the visible text with the configured format while
 * never modifying core templates.
 *
 * @module     local_mention/entry_dateformat
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {Array} items List of {title, text} pairs. "title" matches the span
 *     title attribute, "text" is the replacement visible label.
 */
export const init = (items) => {
    if (!Array.isArray(items) || items.length === 0) {
        return;
    }

    const container = document.querySelector('#data-singleview-content');
    if (!container) {
        return;
    }

    // Build a lookup map from title -> replacement text.
    const map = new Map();
    items.forEach((item) => {
        if (item && typeof item.title === 'string' && typeof item.text === 'string') {
            map.set(item.title, item.text);
        }
    });

    // Find every span carrying a title within the single-view container.
    container.querySelectorAll('span[title]').forEach((span) => {
        const target = map.get(span.getAttribute('title'));
        if (target !== undefined) {
            span.textContent = target;
        }
    });
};