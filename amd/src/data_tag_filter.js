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
 * Filter tag options in Database activity (mod_data) add/edit entry page
 * based on the user's mod/data:approve capability.
 *
 * Users with approve capability: hide tag options containing "draft".
 * Users without approve capability: only show tag options containing "draft".
 * Selected options (existing entry tags) are always preserved.
 *
 * @module     local_mention/data_tag_filter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {boolean} canApprove Whether the user has mod/data:approve capability.
 */
export const init = (canApprove) => {
    /**
     * Find the tag select element using multiple compatible selectors.
     *
     * @returns {HTMLSelectElement|null}
     */
    const findTagSelect = () => {
        const selectors = [
            '#tags',
            '#id_tags',
            'select[name="tags[]"]',
            'select[name="tags"]',
        ];

        for (const selector of selectors) {
            const el = document.querySelector(selector);
            if (el) {
                return el;
            }
        }

        return null;
    };

    /**
     * Find the autocomplete suggestions container for the tag select.
     *
     * @returns {HTMLElement|null}
     */
    const findSuggestionsContainer = () => {
        // The autocomplete creates suggestions with IDs like "form_autocomplete_suggestions-N".
        // We search for the container by data-region attribute.
        const containers = document.querySelectorAll(
            '[data-region="form_autocomplete-suggestions"]'
        );

        for (const container of containers) {
            // Check if this suggestions container is associated with our select.
            // The preceding sibling or parent structure may vary.
            const listbox = container.querySelector('[role="listbox"]');
            if (listbox) {
                return listbox;
            }
        }

        return null;
    };

    /**
     * Filter the options in the select element and autocomplete suggestions.
     */
    const filterTags = () => {
        const select = findTagSelect();
        if (!select) {
            return;
        }

        // Filter options in the original select element.
        const options = Array.from(select.querySelectorAll('option'));
        options.forEach((option) => {
            // Always preserve selected options (existing tags on the entry).
            if (option.selected) {
                return;
            }

            const tagName = option.textContent.trim();
            const hasDraft = tagName.toLowerCase().includes('draft');

            if (canApprove && hasDraft) {
                // User has approve permission: hide draft options.
                option.remove();
            } else if (!canApprove && !hasDraft) {
                // User does not have approve permission: only keep draft options.
                option.remove();
            }
        });

        // Also filter autocomplete suggestions if they're already rendered.
        const suggestionsContainer = findSuggestionsContainer();
        if (suggestionsContainer) {
            const items = Array.from(
                suggestionsContainer.querySelectorAll('[role="option"]')
            );
            items.forEach((item) => {
                const tagName = item.textContent.trim();
                const hasDraft = tagName.toLowerCase().includes('draft');

                if (canApprove && hasDraft) {
                    item.remove();
                } else if (!canApprove && !hasDraft) {
                    item.remove();
                }
            });
        }
    };

    // Execute immediately to catch the case where the autocomplete hasn't initialized yet.
    filterTags();

    // Execute with delays to handle cases where Moodle's form-autocomplete
    // initializes asynchronously after our module runs.
    // The retry intervals cover:
    // - 300ms: typical autocomplete initialization time.
    // - 800ms: slower network / page load scenarios.
    // - 1500ms: worst-case delayed initialization.
    setTimeout(filterTags, 300);
    setTimeout(filterTags, 800);
    setTimeout(filterTags, 1500);
};