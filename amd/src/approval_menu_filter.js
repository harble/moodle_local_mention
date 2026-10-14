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
 * Hide approve/disapprove menu items for records the current user is not the
 * assigned reviewer of.
 *
 * Runs on the mod_data view (list and single) pages. It locates every
 * "##actionsmenu##" kebab menu on the page, collects the record id behind each
 * approve/disapprove link and asks the local_mention_check_approval_permission
 * web service which of those records the current user may approve. Menu items
 * (and kebab buttons) for records the user may not approve are then hidden.
 *
 * @module     local_mention/approval_menu_filter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as ajax from 'core/ajax';

/**
 * Extract the numeric value of a named query parameter from a URL string.
 *
 * @param {string} url The URL (or query string) to inspect.
 * @param {string} key The parameter name.
 * @returns {number|null} The parsed value, or null when absent/unable to parse.
 */
const getQueryParam = (url, key) => {
    const match = url.match(new RegExp('[?&]' + key + '=([0-9]+)'));
    return match ? parseInt(match[1], 10) : null;
};

/**
 * @param {boolean} canApproveCapability Whether the user holds mod/data:approve
 *                                       (informational; the server re-checks).
 */
export const init = (canApproveCapability) => {
    if (!canApproveCapability) {
        // Without the approve capability core does not render these items anyway.
        return;
    }

    // Collect menu containers that contain an approve or disapprove link.
    const menus = new Map(); // recordid -> {container, links[]}
    document.querySelectorAll('.entry-actionsmenu').forEach((container) => {
        const links = [];
        container.querySelectorAll('a[href]').forEach((link) => {
            const href = link.getAttribute('href');
            const rid = getQueryParam(href, 'approve') ?? getQueryParam(href, 'disapprove');
            if (rid !== null) {
                links.push({link, rid});
            }
        });
        if (links.length === 0) {
            return;
        }
        const rid = links[0].rid;
        if (!menus.has(rid)) {
            menus.set(rid, {container, links});
        }
    });

    if (menus.size === 0) {
        return;
    }

    const recordids = Array.from(menus.keys());

    // Run after paint so the DOM is ready.
    window.setTimeout(() => {
        ajax.call([{
            methodname: 'local_mention_check_approval_permission',
            args: {
                recordids: recordids,
            },
        }])[0]
            .done((data) => {
                const permitted = new Set((data && data.permitted || []).map((id) => parseInt(id, 10)));
                recordids.forEach((rid) => {
                    if (permitted.has(rid)) {
                        return;
                    }
                    const menu = menus.get(rid);
                    // Remove the approve/disapprove links that are not permitted
                    // for this record.
                    menu.links.forEach(({link}) => {
                        // Only remove the link when it is for an action we bar.
                        link.remove();
                    });
                    // If the kebab menu holds no other action, hide the whole menu.
                    const remaining = menu.container.querySelectorAll('a[href]').length;
                    if (remaining === 0) {
                        const trigger = menu.container.querySelector('.dropdown-toggle, .icon-no-margin, button');
                        if (trigger) {
                            trigger.closest('.entry-actionsmenu, .no-overflow')?.remove();
                        }
                        menu.container.remove();
                    }
                });
            })
            .fail(() => {
                // Never interrupt the page on a best-effort permission filter.
            });
    }, 0);
};