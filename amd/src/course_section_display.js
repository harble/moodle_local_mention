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
 * Controls the display of the default course section.
 *
 * On the course page, the first section whose name matches the configured
 * value (default "愿心加油站") is either folded or hidden entirely.
 *
 * - mode "fold": triggers the native section collapse toggler (state is
 *   remembered in the user preference by core, matches semantic A).
 * - mode "hide": hides the section via CSS (display: none).
 *
 * The module is passive - it only acts when a matching section exists.
 *
 * @module     local_mention/course_section_display
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * @param {string} mode Either "fold" or "hide".
 * @param {string} sectionName The target section name to match.
 */
export const init = (mode, sectionName) => {
    const target = String(sectionName || '').trim();
    if (!target) {
        return;
    }

    const isFold = (mode === 'fold');

    /**
     * Read the section name from a section root element.
     * Prefer the data-sectionname attribute, fall back to the title text.
     *
     * @param {Element} root The section root element.
     * @returns {string} The trimmed section name.
     */
    const getName = (root) => {
        let name = root.getAttribute('data-sectionname');
        if (!name) {
            const title = root.querySelector('[data-for="section_title"]');
            name = title ? title.textContent : '';
        }
        return String(name || '').trim();
    };

    /**
     * Find the collapse toggler inside a section.
     *
     * @param {Element} root The section root element.
     * @returns {Element} The toggler element, or null.
     */
    const getToggler = (root) =>
        root.querySelector('[data-bs-toggle="collapse"][data-for="sectiontoggler"]') ||
        root.querySelector('[data-bs-toggle="collapse"]');

    /**
     * Apply the configured behaviour to the first matching section.
     * Returns true when a matching main section was found and processed.
     *
     * @param {number} remaining Number of remaining retries.
     */
    const apply = (remaining) => {
        const mains = document.querySelectorAll('li.course-section[data-for="section"]');
        let handled = false;

        for (const section of mains) {
            if (getName(section) !== target) {
                continue;
            }
            if (isFold) {
                const toggler = getToggler(section);
                if (toggler && !toggler.classList.contains('collapsed')) {
                    toggler.click();
                }
            } else {
                section.style.display = 'none';
                // Keep the course index consistent with the hidden section.
                const indexes = document.querySelectorAll('.courseindex-section[data-for="section"]');
                for (const indexSection of indexes) {
                    if (getName(indexSection) === target) {
                        indexSection.style.display = 'none';
                        break;
                    }
                }
            }
            handled = true;
            break;
        }

        // Retry if the section is not rendered yet (async section layout).
        if (!handled && remaining > 0) {
            setTimeout(() => apply(remaining - 1), 300);
        }
    };

    // Start after the initial render attempt.
    setTimeout(() => apply(20), 200);
};