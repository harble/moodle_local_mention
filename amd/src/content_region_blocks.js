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
 * Moves the "content" block region to the bottom of the main content card.
 *
 * The content block region is rendered through the
 * after_standard_main_region_html_generation hook, which Boost places *after*
 * the page footer (see theme/boost/templates/drawers.mustache). This module
 * relocates the whole <section id="block-region-content"> element into the
 * native #region-main container so the blocks render exactly at the bottom of
 * the main content area.
 *
 * The module is passive - it only acts when both the block region and the
 * target container exist on the current page.
 *
 * @module     local_mention/content_region_blocks
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const init = () => {
    const blockRegion = document.getElementById('block-region-content');
    if (!blockRegion) {
        return;
    }

    // Move the whole block region to the bottom of the main content card.
    const regionMain = document.getElementById('region-main');
    if (!regionMain || regionMain.contains(blockRegion)) {
        return;
    }
    regionMain.appendChild(blockRegion);
};