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
 * Limit how many options can be selected in Database "multi-select menu"
 * (multimenu) fields, matched by field description.
 *
 * The multimenu field renders (mod/data/field/multimenu/field.class.php) as:
 *
 *   <div title="<field description>">
 *     <label for="field_<id>"><legend>...name...</legend></label>
 *     <select name="field_<id>[]" id="field_<id>" multiple="multiple"
 *             class="mod-data-input form-control">
 *       <option ...>...</option>
 *     </select>
 *   </div>
 *
 * Only <select multiple> elements inside a <div[title]> whose title matches
 * one of the configured descriptions are constrained. Selections that already
 * exceed the limit are trimmed on page load (kept in DOM order).
 *
 * @module     local_mention/multimenu_limit
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Normalise a string for case-insensitive comparison.
 *
 * @param {string} value The raw value.
 * @returns {string}
 */
const norm = (value) => (typeof value === 'string' ? value.trim().toLowerCase() : '');

/**
 * Return the selected values of a select in DOM order.
 *
 * @param {HTMLSelectElement} select The select element.
 * @returns {string[]}
 */
const selectedValues = (select) => Array.from(select.selectedOptions).map((option) => option.value);

/**
 * Make exactly `wanted` values selected on the given select.
 *
 * @param {HTMLSelectElement} select The select element.
 * @param {string[]} wanted The values that should stay selected.
 */
const keepOnly = (select, wanted) => {
    const wantedSet = new Set(wanted);
    Array.from(select.options).forEach((option) => {
        option.selected = wantedSet.has(option.value);
    });
};

/**
 * Show a transient hint next to the select.
 *
 * @param {HTMLSelectElement} select The select element.
 * @param {string} message The message to show.
 */
const showHint = (select, message) => {
    const hint = document.createElement('div');
    hint.className = 'localmention-multimenu-hint text-danger small my-1';
    hint.textContent = message;
    if (select.parentNode) {
        select.parentNode.appendChild(hint);
    }
    window.setTimeout(() => hint.remove(), 3000);
};

/**
 * Bind the max-selection limit to a single select element.
 *
 * @param {HTMLSelectElement} select The select element.
 * @param {number} max The maximum number of selections.
 * @param {string} hintTemplate Message template containing a {max} placeholder.
 */
const bindLimit = (select, max, hintTemplate) => {
    const hint = () => hintTemplate.replace('{max}', String(max));

    // Trim an over-limit selection as soon as the page opens (in DOM order).
    let snapshot = selectedValues(select);
    if (snapshot.length > max) {
        keepOnly(select, snapshot.slice(0, max));
        showHint(select, hint());
        snapshot = selectedValues(select);
    }

    // A native <select multiple> fires "change" after every selection change,
    // so the snapshot taken after the previous event is enough.
    select.addEventListener('change', () => {
        const current = selectedValues(select);
        if (current.length <= max) {
            snapshot = current;
            return;
        }

        const oldSet = new Set(snapshot);
        const kept = [];

        // Keep previously selected values first (stable order).
        snapshot.forEach((value) => {
            if (kept.length >= max) {
                return;
            }
            if (oldSet.has(value) && current.includes(value)) {
                kept.push(value);
            }
        });

        // Fill any remaining quota with newly added values (DOM order).
        current.forEach((value) => {
            if (kept.length >= max) {
                return;
            }
            if (!oldSet.has(value)) {
                kept.push(value);
            }
        });

        keepOnly(select, kept.slice(0, max));
        snapshot = selectedValues(select);
        showHint(select, hint());
    });
};

/**
 * Initialise the module.
 *
 * @param {Array} rules List of [description, max] pairs, e.g. [["channels", 3]].
 * @param {string} hintTemplate Message template (contains {max}).
 */
export const init = (rules, hintTemplate) => {
    if (!rules || !rules.length || !hintTemplate) {
        return;
    }

    const matches = rules.map((rule) => ({
        description: norm(rule[0]),
        max: parseInt(rule[1], 10),
    })).filter((rule) => rule.description !== '' && rule.max > 0);

    if (!matches.length) {
        return;
    }

    /**
     * Apply limits to every matching select inside the entry form.
     *
     * @returns {boolean} Whether at least one select was bound.
     */
    const apply = () => {
        const form = document.getElementById('dataentryform')
            || document.querySelector('form[action*="edit.php"]');
        const selects = form
            ? Array.from(form.querySelectorAll('select[multiple]'))
            : Array.from(document.querySelectorAll('select[multiple]'));

        let bound = false;
        selects.forEach((select) => {
            if (select.dataset.lmMultimenuLimit) {
                return;
            }
            const wrapper = select.closest('div[title]');
            if (!wrapper) {
                return;
            }
            const description = norm(wrapper.getAttribute('title') || '');
            const matched = matches.find((rule) => rule.description === description);
            if (!matched) {
                return;
            }
            select.dataset.lmMultimenuLimit = '1';
            bindLimit(select, matched.max, hintTemplate);
            bound = true;
        });

        return bound;
    };

    // The footer hook runs before the page is fully painted in some setups,
    // so retry a few times. Bound selects are marked with a data attribute,
    // so repeated scans never double-bind.
    const attempts = [0, 300, 800, 1500];
    let attemptIndex = 0;
    const schedule = () => {
        if (apply()) {
            return;
        }
        attemptIndex += 1;
        if (attemptIndex < attempts.length) {
            window.setTimeout(schedule, attempts[attemptIndex]);
        }
    };
    schedule();
};