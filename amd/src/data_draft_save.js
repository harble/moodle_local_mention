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
 * Add a "Save draft" button to the mod/data add/edit sticky footer.
 *
 * On /mod/data/edit.php the page ships a sticky footer containing Cancel /
 * Save / Save and add another. This module injects a "Save draft" button
 * between Cancel and Save, and adjusts how the draft tag is submitted:
 *
 *   - Clicking "Save draft" saves the entry while ensuring the
 *     "草稿(DRAFT)" tag is included (adding it when absent), so the
 *     background "draft skips auto-approval" logic keeps the entry out of
 *     the review queue.
 *   - Clicking "Save" (or "Save and add another") behaves as a publish: it
 *     saves the entry while ensuring "草稿(DRAFT)" is removed, so an entry
 *     that previously carried a draft tag is handed back to the normal
 *     review path.
 *
 * The draft tag is toggled on the native #tags <select>. That element is the
 * authoritative source of the submitted tags[] values, even when it has been
 * enhanced by core/form-autocomplete into a custom widget.
 *
 * @module     local_mention/data_draft_save
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The tag name (raw, as displayed in the picker) that marks an entry as a draft.
 * @type {string}
 */
const DRAFT_TAG = '草稿(DRAFT)';

/**
 * Normalise a value for comparison (trim, lower-case).
 *
 * @param {string} value The raw value.
 * @returns {string}
 */
const norm = (value) => (typeof value === 'string' ? value.trim().toLowerCase() : '');

/**
 * Locate the native tags <select name="tags[]">.
 *
 * @returns {HTMLSelectElement|null}
 */
const getTagsSelect = () => {
    const el = document.getElementById('tags');
    return el instanceof HTMLSelectElement ? el : null;
};

/**
 * Set the draft tag selection on the native tags <select>.
 *
 * @param {boolean} include Whether the draft tag should be selected.
 */
const setDraftSelected = (include) => {
    const select = getTagsSelect();
    if (!select) {
        return;
    }

    // Find an existing option whose value is the draft tag (case-insensitive).
    let draftOption = Array.from(select.options).find(
        (option) => norm(option.value) === norm(DRAFT_TAG)
    );

    if (include) {
        // Ensure the option exists, then select it.
        if (!draftOption) {
            draftOption = document.createElement('option');
            draftOption.value = DRAFT_TAG;
            draftOption.textContent = DRAFT_TAG;
            select.add(draftOption);
        }
        draftOption.selected = true;
        draftOption.setAttribute('selected', 'selected');
    } else if (draftOption) {
        // Deselect an existing draft option (leaves the option itself in place).
        draftOption.selected = false;
        draftOption.removeAttribute('selected');
    }
};

/**
 * Initialise the module.
 *
 * @param {string} saveLabel Active "Save draft" label text.
 */
export const init = (saveLabel) => {
    // Any native Database edit form must be saveable, whatever the activity
    // settings or the entry state. Anchor on a submit button that carries a
    // name starting with "save" (Save / Save and view / Save and add another /
    // Save and approve...), falling back to any submit button. The draft button
    // itself (name="savedraft") is always excluded.
    const saveSelector = 'input[type="submit"][name^="save"]:not([name="savedraft"])';
    const firstSave = document.querySelector(saveSelector)
        || document.querySelector('input[type="submit"]:not([name="savedraft"])');
    if (!firstSave) {
        return;
    }
    const form = firstSave.closest('form');
    if (!form) {
        return;
    }

    // Publish buttons: every submit in the form except the draft button itself
    // acts as "publish". Clicking any of them drops a previously set draft tag
    // so the entry can re-enter the normal review flow.
    form.querySelectorAll('input[type="submit"]:not([name="savedraft"])').forEach((button) => {
        button.addEventListener('click', () => {
            setDraftSelected(false);
        });
    });

    // Skip injecting the extra button if one already exists (e.g. the hook is
    // ran more than once for the same page).
    if (form.querySelector('[name="savedraft"]')) {
        return;
    }

    // Use the same element type (input[type="submit"]) and class as the native
    // "Save" button so both render identically in every theme. Using a raw
    // <button> would look the same class-wise but can pick up different
    // element-specific styling from the theme.
    const draftButton = document.createElement('input');
    draftButton.type = 'submit';
    draftButton.name = 'savedraft';
    draftButton.className = firstSave.className;
    draftButton.value = saveLabel;

    draftButton.addEventListener('click', () => {
        setDraftSelected(true);
    });

    firstSave.parentNode?.insertBefore(draftButton, firstSave);
};