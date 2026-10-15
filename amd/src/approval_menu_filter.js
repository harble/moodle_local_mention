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
 * assigned reviewer of, and add a "Reject" action for the ones they can action.
 *
 * Runs on the mod_data view (list and single) pages. It locates every
 * "##actionsmenu##" kebab menu on the page, collects the record ids behind each
 * approve/disapprove link and asks the local_mention_check_approval_permission
 * web service which of those records the current user may approve. For each
 * permitted record a "Reject" item is injected into the menu; opening it shows
 * a dialog where the reviewer can enter a reason (and see the past rejection
 * history), confirmations call local_mention_reject_record and then reload the
 * page.
 *
 * @module     local_mention/approval_menu_filter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';
import $ from 'jquery';

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
 * Escape a string for safe use inside HTML.
 *
 * @param {string} value The value to escape.
 * @returns {string} The escaped value.
 */
const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value === null ? '' : String(value);
    return div.innerHTML;
};

/**
 * Append the rejection reason into a query by reading the textarea.
 *
 * @param {HTMLElement} modalBody The modal body element.
 * @returns {string} The trimmed reason.
 */
const readReason = (modalBody) => {
    const field = modalBody.querySelector('.local-mention-reject-reason');
    return field ? field.value.trim() : '';
};

/**
 * Add a "Reject" link to a permitted menu container.
 *
 * @param {HTMLElement} container The ##actionsmenu## container.
 * @param {number} recordid The data_records id.
 */
const addRejectAction = (container, recordid) => {
    const dropdown = container.querySelector('.dropdown-menu, .dropdown-menu-end');
    if (!dropdown || dropdown.querySelector('.local-mention-reject')) {
        return;
    }
    getString('reject', 'local_mention').then((rejectStr) => {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item local-mention-reject';
        link.textContent = rejectStr;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openRejectDialog(recordid);
        });
        dropdown.appendChild(link);
    }).catch(() => {
        // never interrupt on a missing string
    });
};

/**
 * Build the reject dialog body (textarea + history container).
 *
 * @returns {Promise<{element: HTMLElement, history: HTMLElement}>}
 */
const buildRejectBody = async () => {
    const label = await getString('reject_reasonlabel', 'local_mention');
    const placeholder = await getString('reject_reasonplaceholder', 'local_mention');
    const historyTitle = await getString('reject_history', 'local_mention');
    const noReason = await getString('reject_noreason', 'local_mention');
    const loadFailed = await getString('reject_loadfailed', 'local_mention');

    const element = document.createElement('div');
    element.className = 'local-mention-reject-body';

    const labelEl = document.createElement('label');
    labelEl.className = 'form-label';
    labelEl.textContent = label;

    const textarea = document.createElement('textarea');
    textarea.className = 'form-control local-mention-reject-reason';
    textarea.rows = 4;
    textarea.placeholder = placeholder;

    element.appendChild(labelEl);
    element.appendChild(textarea);

    const history = document.createElement('div');
    history.className = 'local-mention-reject-history';

    const historyHeading = document.createElement('h6');
    historyHeading.className = 'mt-3';
    historyHeading.textContent = historyTitle;
    history.appendChild(historyHeading);

    element.appendChild(history);

    return {element, history, noReason, loadFailed};
};

/**
 * Render past rejections into the history container.
 *
 * @param {HTMLElement} history The history container.
 * @param {Array} rejections The rejection list.
 */
const renderHistory = (history, rejections) => {
    const list = document.createElement('ul');
    list.className = 'list-unstyled ml-3';
    rejections.forEach((item) => {
        const li = document.createElement('li');
        const stamp = new Date(item.timecreated * 1000).toLocaleString();
        li.innerHTML =
            `<strong>${escapeHtml(item.reviewer)}</strong> <small class="text-muted">${stamp}</small><br>` +
            escapeHtml(item.reason);
        list.appendChild(li);
    });
    history.appendChild(list);
};

/**
 * Open the reject dialog for a record.
 *
 * @param {number} recordid The data_records id.
 */
const openRejectDialog = async (recordid) => {
    try {
        const {element, history, noReason, loadFailed} = await buildRejectBody();
        const dialogTitle = await getString('reject_dialogtitle', 'local_mention');
        const submitText = await getString('reject_submit', 'local_mention');

        const modal = await ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: dialogTitle,
            body: $(element),
            show: false,
        });
        modal.setSaveButtonText(submitText);
        {
            // Load past rejections.
            ajax.call([{
                methodname: 'local_mention_get_rejections',
                args: {recordid: recordid},
            }])[0]
                .done((data) => {
                    const rejections = (data && data.rejections) || [];
                    if (rejections.length) {
                        renderHistory(history, rejections);
                    } else {
                        history.querySelector('h6').style.display = 'none';
                    }
                })
                .fail(() => {
                    history.querySelector('h6').style.display = 'none';
                });

            modal.getRoot().on(ModalEvents.save, (e) => {
                e.preventDefault();
                const reason = readReason(element);
                if (reason === '') {
                    window.alert(noReason);
                    return;
                }
                ajax.call([{
                    methodname: 'local_mention_reject_record',
                    args: {
                        recordid: recordid,
                        reason: reason,
                    },
                }])[0]
                    .done((data) => {
                        if (data && data.success) {
                            modal.hide();
                            window.location.reload();
                        } else {
                            window.alert((data && data.error) || 'Reject failed');
                        }
                    })
                    .fail(() => {
                        window.alert(loadFailed);
                    });
            });

            modal.show();
        }
    } catch (err) {
        // never interrupt on modal construction failure
        if (window.console && window.console.error) {
            window.console.error('local_mention reject dialog failed:', err);
        }
    }
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
                const keepmenu = new Set((data && data.keepmenu || []).map((id) => parseInt(id, 10)));
                recordids.forEach((rid) => {
                    const menu = menus.get(rid);
                    if (keepmenu.has(rid)) {
                        // Keep the whole action menu. If the user is also an
                        // approver of this record, inject the reject item.
                        if (permitted.has(rid)) {
                            addRejectAction(menu.container, rid);
                        }
                        return;
                    }
                    // Not the author nor an approver of this record. These
                    // roles hold no edit/delete/approve rights here, so the
                    // whole actions menu can be removed (it would otherwise
                    // only hold an inert approve item).
                    const trigger = menu.container.querySelector('.dropdown-toggle, .icon-no-margin, button');
                    if (trigger) {
                        trigger.closest('.entry-actionsmenu, .no-overflow')?.remove();
                    }
                    menu.container.remove();
                });
            })
            .fail(() => {
                // Never interrupt the page on a best-effort permission filter.
            });
    }, 0);
};