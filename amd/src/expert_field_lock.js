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
 * Lock Database (mod_data) entry-edit fields for expert users.
 *
 * On the /mod/data/edit.php page, when the current user is neither the record
 * author nor a site admin (an "expert"), fields whose wrapper <div> title is
 * NOT in the configured editable list are locked (read-only).
 *
 * Each data field in the add/edit template is rendered inside a wrapper <div>
 * whose title attribute identifies the field. The actual control varies by
 * field type:
 *   - plain inputs/selects/textareas carry the "mod-data-input" class;
 *   - file fields render a whole file manager inside a "mod-data-input" div;
 *   - textarea fields initialise a TinyMCE editor over the textarea.
 *
 * This module therefore locks the whole wrapper container rather than a single
 * control, so every field type is covered.
 *
 * @module     local_mention/expert_field_lock
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {string[]} allowedTitles Field titles (wrapper div title attribute)
 *                                  that the expert may keep editing.
 */
export const init = (allowedTitles) => {
    /**
     * Normalise a title for comparison (trim and lower-case).
     *
     * @param {string} value The raw title.
     * @returns {string}
     */
    const normalise = (value) => (typeof value === 'string' ? value.trim().toLowerCase() : '');

    const allowed = new Set((allowedTitles || []).map(normalise));

    /**
     * Lock a single field wrapper container: block pointer interaction, make
     * text-like controls read-only (value still submitted) and disable the
     * remaining controls (select, checkbox, radio, file manager buttons).
     *
     * @param {HTMLElement} container The field wrapper <div title="...">.
     */
    const lockContainer = (container) => {
        // Visually mark the field as locked.
        container.classList.add('local-mention-field-locked');

        // Block mouse interaction entirely (covers file managers and TinyMCE
        // overlay which cannot be "disabled" like a plain input).
        container.style.pointerEvents = 'none';

        // Text-like controls: use readonly so the current value is preserved
        // and still submitted with the form.
        container.querySelectorAll('input[type="text"], input[type="url"], input[type="email"], textarea').forEach((el) => {
            el.readOnly = true;
        });

        // All other interactive controls: disable them.
        container.querySelectorAll('input:not([type="hidden"]), select, button, [contenteditable="true"]').forEach((el) => {
            if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLButtonElement) {
                el.disabled = true;
            }
            if (el.hasAttribute('contenteditable')) {
                el.setAttribute('contenteditable', 'false');
            }
        });

        // Disable any TinyMCE editor instance that has already been
        // initialised inside this field (identified by the textarea id).
        container.querySelectorAll('textarea').forEach((field) => {
            if (field.id && window.tinymce && window.tinymce.get(field.id)) {
                window.tinymce.get(field.id).setDisabled(true);
            }
        });
    };

    /**
     * Run the locking pass. Only field wrappers that actually contain a
     * ".mod-data-input" control are considered, so unrelated elements that
     * happen to carry a title attribute elsewhere on the page are left alone.
     */
    const run = () => {
        document.querySelectorAll('div[title]').forEach((container) => {
            if (!container.querySelector('.mod-data-input')) {
                return;
            }
            const title = normalise(container.getAttribute('title'));
            if (!allowed.has(title)) {
                lockContainer(container);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            run();
            // Some controls (e.g. TinyMCE editors) are initialised asynchronously
            // after the DOM is ready. Re-run once their subtree appears so their
            // editors can be disabled too.
            const observer = new MutationObserver(run);
            observer.observe(document.body, {subtree: true, childList: true});
        });
    } else {
        run();
    }
};