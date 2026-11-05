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
 * Hides page elements selected by configured CSS selectors.
 *
 * The selectors are computed server-side (local_mention\local\element_hider)
 * respecting the role based hiding rules, and passed to this module. A single
 * <style> element is injected into the document head hiding every matching
 * node, so the effect applies regardless of when the module is executed.
 *
 * @module     local_mention/element_hider
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

/**
 * Initialise the element hider.
 *
 * @param {string[]} selectors CSS selectors to hide.
 */
export const init = (selectors) => {
    if (!Array.isArray(selectors) || selectors.length === 0) {
        return;
    }

    // Filter out anything that is not a plausible CSS selector string.
    const safeSelectors = selectors
        .filter((s) => typeof s === 'string' && s.trim() !== '')
        .map((s) => s.trim());

    if (safeSelectors.length === 0) {
        return;
    }

    // Escape any style block terminators so the configuration cannot break out
    // of the generated CSS and inject arbitrary rules.
    const cssRules = safeSelectors
        .map((s) => s.replace(/<\/style/gi, '<\\/style'))
        .join(', ') + '{display: none !important;}';

    const style = $('<style type="text/css"></style>');
    style.text(cssRules);
    $('head').append(style);
};