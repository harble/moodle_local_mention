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
 * Replace the rating aggregate label on mod_data record view pages.
 *
 * By default Moodle displays "Average of ratings" / "平均分" based on the
 * aggregateavg language string. This module replaces it with the desired text
 * (e.g. "Rating:" / "评分：") without modifying Moodle core.
 *
 * @module     local_mention/rating_label
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {string} labelText The replacement text for the rating aggregate label.
 */
export const init = (labelText) => {
    /**
     * Find and replace rating aggregate label text.
     */
    const replaceLabel = () => {
        const labels = document.querySelectorAll('.rating-aggregate-label');
        if (!labels.length) {
            return;
        }

        labels.forEach((label) => {
            // Only replace if it contains the default aggregate label text.
            // This avoids replacing already-customized labels.
            label.textContent = labelText;
        });
    };

    // Execute immediately.
    replaceLabel();

    // Retry with delays in case the rating UI is lazy-loaded or rendered asynchronously.
    setTimeout(replaceLabel, 300);
    setTimeout(replaceLabel, 800);
};