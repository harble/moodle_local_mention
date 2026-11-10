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
 * Set (or clear) a single tag on the native tags <select>.
 *
 * @param {string} tagName The raw tag name to add/remove.
 * @param {boolean} include Whether the tag should be selected.
 */
const setTagSelected = (tagName, include) => {
    const select = getTagsSelect();
    if (!select) {
        return;
    }

    // Find an existing option whose value is the tag (case-insensitive).
    let option = Array.from(select.options).find(
        (candidate) => norm(candidate.value) === norm(tagName)
    );

    if (include) {
        // Ensure the option exists, then select it.
        if (!option) {
            option = document.createElement('option');
            option.value = tagName;
            option.textContent = tagName;
            select.add(option);
        }
        option.selected = true;
        option.setAttribute('selected', 'selected');
    } else if (option) {
        // Deselect an existing option (leaves the option itself in place).
        option.selected = false;
        option.removeAttribute('selected');
    }
};

/**
 * Set whether the draft tag is selected on the native tags <select>.
 *
 * @param {boolean} include Whether the draft tag should be selected.
 */
const setDraftSelected = (include) => setTagSelected(DRAFT_TAG, include);

/**
 * Whether a given tag is currently selected on the native tags <select>.
 *
 * @param {string} tagName The raw tag name to look up.
 * @returns {boolean}
 */
const isTagSelected = (tagName) => {
    const select = getTagsSelect();
    if (!select) {
        return false;
    }
    const option = Array.from(select.options).find(
        (candidate) => norm(candidate.value) === norm(tagName)
    );
    return !!option && option.selected;
};

/**
 * Initialise the module.
 *
 * @param {string} saveLabel Active "Save draft" label text.
 * @param {string} publishLabel Active label for the plain "Save" (publish) button.
 * @param {Object|null} pinPrime Config for the "pin"/"prime" checkboxes, or null
 *                              when they should not be rendered at all. Shape:
 *                              { scope: 'global'|'channel',
 *                                labels: {pin, prime},
 *                                tags: {pin, prime} }.
 */
export const init = (saveLabel, publishLabel, pinPrime) => {
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

    // Re-layout the toolbar and move the native "Cancel" link to the far right.
    // The sticky footer is usually NOT a flex container and Boost clips any
    // overflow (`.sticky-footer-content { overflow: hidden }`), so once the
    // extra checkboxes and the draft button are inserted the right-most
    // "Cancel" would be pushed off-screen and hidden. Making the row a flexible
    // one (wrapping when needed) and lifting the ancestor overflow keeps every
    // control visible, with Cancel last -> footer reads
    // "[pin/prime] Save draft / Publish / Save and add another / Cancel".
    const btnrow = firstSave.parentNode;
    if (btnrow) {
        btnrow.style.display = 'flex';
        btnrow.style.flexWrap = 'wrap';
        btnrow.style.alignItems = 'center';
        btnrow.style.gap = '0.5rem';

        const overflowAncestor = btnrow.closest('.sticky-footer-content');
        if (overflowAncestor) {
            overflowAncestor.style.overflow = 'visible';
        }

        const cancelLink = btnrow.querySelector('a.btn-secondary, a[role="button"]');
        if (cancelLink) {
            btnrow.appendChild(cancelLink);
        }
    }

    // Rebrand the plain "Save" button (which publishes the entry) as "Publish",
    // so the sticky footer reads "Cancel / Save draft / Publish / ...".
    const saveViewButton = form.querySelector('input[type="submit"][name="saveandview"]');
    if (saveViewButton && publishLabel) {
        saveViewButton.value = publishLabel;
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

    // Render the pin/prime checkboxes (if allowed) in front of the draft button.
    if (pinPrime) {
        renderPinPrimeCheckboxes(draftButton, pinPrime);
    }

    // On submit, forcibly reconcile every pin/prime checkbox against the tags
    // <select> using the checkbox state as the source of truth. This covers the
    // case where the entry was opened with a pin/prime tag already set (so the
    // checkbox is checked) but the user removed the tag manually without ever
    // toggling the checkbox: no "change" event fired, yet saving must restore
    // the tag because the checkbox is still checked. The same reconciliation
    // also removes a tag when the checkbox was unchecked.
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[type="checkbox"][data-lm-tag]').forEach((checkbox) => {
            setTagSelected(checkbox.dataset.lmTag, checkbox.checked);
        });
    });
};

/**
 * Build a single pin/prime inline checkbox and wire it to the tags select.
 *
 * @param {string} baseId Unique element id prefix.
 * @param {string} tagName The raw tag to toggle (e.g. "置顶[pin]").
 * @param {string} caption The visible label (e.g. "置顶(全局)").
 * @returns {HTMLLabelElement}
 */
const makeToggleLabel = (baseId, tagName, caption) => {
    const label = document.createElement('label');
    label.className = 'form-check-inline mx-1 mb-0';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'form-check-input me-1';
    checkbox.id = baseId;
    checkbox.dataset.lmTag = tagName;
    checkbox.checked = isTagSelected(tagName);

    // Toggling immediately reflects the chosen tag on the authoritative native
    // tags <select>, which is submitted when the form is saved.
    checkbox.addEventListener('change', () => {
        setTagSelected(tagName, checkbox.checked);
    });

    label.appendChild(checkbox);

    const text = document.createElement('span');
    text.textContent = caption;
    label.appendChild(text);

    return label;
};

/**
 * Insert the two "pin/prime" checkboxes in front of the given anchor button.
 *
 * @param {HTMLElement} anchor A button that already exists in the toolbar.
 * @param {Object} config The pin/prime configuration (labels + tags).
 */
const renderPinPrimeCheckboxes = (anchor, config) => {
    const scope = config.scope === 'global' ? 'global' : 'channel';
    const pinTag = config.tags && config.tags.pin ? config.tags.pin : '';
    const primeTag = config.tags && config.tags.prime ? config.tags.prime : '';
    const pinLabel = config.labels && config.labels.pin ? config.labels.pin : 'Pin';
    const primeLabel = config.labels && config.labels.prime ? config.labels.prime : 'Prime';

    const wrapper = document.createElement('div');
    wrapper.className = 'localmention-pinprime d-inline-flex align-items-center mx-3';
    wrapper.style.gap = '1rem';

    if (pinTag) {
        const idSuffix = Math.random().toString(36).slice(2, 8);
        wrapper.appendChild(makeToggleLabel('lm_pin_' + idSuffix, pinTag, pinLabel));
    }
    if (primeTag) {
        const idSuffix = Math.random().toString(36).slice(2, 8);
        wrapper.appendChild(makeToggleLabel('lm_prime_' + idSuffix, primeTag, primeLabel));
    }

    anchor.parentNode?.insertBefore(wrapper, anchor);
};