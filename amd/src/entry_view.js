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
 * Log a database record view on the native mod_data single-record page.
 *
 * The page calls the local_mention_log_view web service asynchronously after
 * the entry has had a chance to be rendered/read. Failures are silent so the
 * user experience is never interrupted.
 *
 * @module     local_mention/entry_view
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as ajax from 'core/ajax';

/**
 * @param {number} recordId The data_records id of the entry being viewed.
 */
export const init = (recordId) => {
    // Delay the call so it does not compete with first-paint rendering.
    window.setTimeout(() => {
        ajax.call([{
            methodname: 'local_mention_log_view',
            args: {
                recordid: recordId,
            },
        }])[0].fail(() => {
            // Silent failure: view counting is best-effort and should never
            // surface an error to the user.
        });
    }, 1000);
};