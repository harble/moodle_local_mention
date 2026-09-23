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
 * Default the TinyMCE image dialog's "decorative" checkbox to checked.
 *
 * When inserting a new image via the Tiny editor, the "This image is decorative
 * only" checkbox defaults to unchecked, requiring the user to fill in alt text.
 * This module automatically checks it so the alt text field becomes optional.
 * Existing images being edited are NOT affected — only new image insertions.
 *
 * @module     local_mention/tiny_image_decorative
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const init = () => {
    let applied = false;

    /**
     * Check the decorative checkbox once and trigger the change event.
     * This causes ImageDetails.presentationChanged() to disable the alt input
     * and clear the validation error.
     * After the first successful application the observer is disconnected so
     * the user can freely toggle the checkbox themselves afterwards.
     */
    const checkDecorative = () => {
        if (applied) {
            return;
        }

        const checkbox = document.querySelector('.tiny_image_presentation');
        if (checkbox && !checkbox.checked) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', {bubbles: true}));
            applied = true;
            observer.disconnect();
        }
    };

    // Set up a MutationObserver to detect when the image details dialog
    // is dynamically added to the DOM (TinyMCE modals are loaded lazily).
    const observer = new MutationObserver(() => {
        checkDecorative();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    // Also check immediately in case the dialog is already open.
    checkDecorative();
};