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
 * @param {boolean} showDraft Whether the "Save draft" button should be shown
 *                            for the current entry.
 */
export const init = (saveLabel, showDraft) => {
    // Only run on the Database add/edit page: the form must carry a #tags
    // element and a sticky footer with a Save button.
    const form = getTagsSelect()?.closest('form');
    if (!form) {
        return;
    }

    // Publish buttons: "Save" and "Save and add another". Clicking either of
    // them drops any draft tag so the entry re-enters the review flow.
    form.querySelectorAll('input[type="submit"][name="saveandview"], input[type="submit"][name="saveandadd"]').forEach((button) => {
        button.addEventListener('click', () => {
            setDraftSelected(false);
        });
    });

    // Do not inject the "Save draft" button for entries that may not be saved
    // as a draft (e.g. an approved entry the user cannot edit).
    if (!showDraft) {
        return;
    }

    // Skip injecting the extra button if one already exists (e.g. the hook is
    // ran more than once for the same page).
    if (form.querySelector('[name="savedraft"]')) {
        return;
    }

    // Insert the "Save draft" button right before the first "Save" button so
    // the final order is: Cancel / Save draft / Save / Save and add another.
    const firstSave = form.querySelector('input[type="submit"][name="saveandview"]');
    if (!firstSave) {
        return;
    }

    const draftButton = document.createElement('button');
    draftButton.type = 'submit';
    draftButton.name = 'savedraft';
    draftButton.className = 'btn btn-primary mx-1';
    draftButton.textContent = saveLabel;

    draftButton.addEventListener('click', () => {
        setDraftSelected(true);
    });

    firstSave.parentNode?.insertBefore(draftButton, firstSave);
};