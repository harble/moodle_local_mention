define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    var KEY_UP = 38;
    var KEY_DOWN = 40;
    var KEY_ENTER = 13;
    var KEY_ESCAPE = 27;
    var BOUND_ATTR = 'data-local-mention-bound';
    var FORM_BOUND_ATTR = 'data-local-mention-submit-bound';
    var IGNORE_ATTR = 'data-local-mention-ignore-unresolved';

    var PLAIN_MENTION_PATTERN = /(^|[\s\(\[\{,;:>])@([^\s@,.;:!?，。；：！？、（）()\[\]{}<>《》「」『』【】"'“”‘’`~！￥…—]{1,100})/gu;

    var isNavigationKey = function(keyCode) {
        return keyCode === KEY_UP || keyCode === KEY_DOWN || keyCode === KEY_ENTER || keyCode === KEY_ESCAPE;
    };

    var getMinimumQueryLength = function(query) {
        if (/^[\u4E00-\u9FFF]/u.test(query)) {
            return 1;
        }

        return 2;
    };

    var createDropdown = function() {
        var menu = document.createElement('ul');
        menu.className = 'local-mention-menu';
        menu.style.display = 'none';
        document.body.appendChild(menu);
        return menu;
    };

    var positionDropdown = function(target, menu, state) {
        var rect = target.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        if (state && state.type === 'contenteditable' && state.textNode) {
            // For contenteditable, try to position based on cursor
            var selection = window.getSelection();
            if (selection && selection.rangeCount > 0) {
                var range = selection.getRangeAt(0);
                var cursorRect = range.getBoundingClientRect();
                if (cursorRect) {
                    menu.style.left = (cursorRect.left + scrollLeft) + 'px';
                    menu.style.top = (cursorRect.bottom + scrollTop + 2) + 'px';
                    menu.style.width = '280px';
                    return;
                }
            }
        }

        // For textarea or fallback
        menu.style.left = (rect.left + scrollLeft) + 'px';
        menu.style.top = (rect.bottom + scrollTop + 2) + 'px';
        menu.style.width = Math.max(280, rect.width * 0.7) + 'px';
    };

    var findMentionQuery = function(value, caretPos) {
        var left = value.substring(0, caretPos);
        var atPos = left.lastIndexOf('@');
        if (atPos < 0) {
            return null;
        }

        var query = left.substring(atPos + 1);
        if (!query || query.length > 100 || /\s|@/.test(query)) {
            return null;
        }

        // Stop mention lookup when punctuation appears after @ (full-width and half-width).
        if (/[,.;:!?，。；：！？、（）()\[\]{}<>《》「」『』【】"'“”‘’`~！￥…—]/.test(query)) {
            return null;
        }

        // Avoid triggering inside likely email local-part context, e.g. abc@domain.
        // Keep chained mentions working, e.g. "@学生1@张三".
        var localPartStart = atPos;
        while (localPartStart > 0 && /[^\s\(\[\{,;:>]/.test(left.charAt(localPartStart - 1))) {
            localPartStart--;
        }
        var beforeToken = left.substring(localPartStart, atPos);
        if (/^[A-Za-z0-9._%+-]+$/.test(beforeToken) && /[A-Za-z]/.test(beforeToken)) {
            return null;
        }

        return {
            query: query,
            start: atPos,
            end: caretPos
        };
    };

    var renderMenu = function(menu, items, state) {
        menu.innerHTML = '';
        if (!items.length) {
            menu.style.display = 'none';
            return;
        }

        items.forEach(function(item, idx) {
            var li = document.createElement('li');
            li.className = 'local-mention-item';
            li.textContent = item.display;
            li.dataset.index = String(idx);
            if (idx === state.activeIndex) {
                li.classList.add('active');
            }
            li.addEventListener('mousedown', function(e) {
                e.preventDefault();
                state.pick(idx);
            });
            menu.appendChild(li);
        });

        menu.style.display = 'block';
    };

    var replaceTextareaRange = function(textarea, start, end, replacement) {
        var value = textarea.value;
        textarea.value = value.substring(0, start) + replacement + value.substring(end);
        var newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    };

    var isContentEditable = function(node) {
        return !!node && node.nodeType === 1 && node.isContentEditable;
    };

    var getContenteditableState = function(node) {
        var selection = window.getSelection();
        if (!selection || !selection.rangeCount) {
            return null;
        }

        var range = selection.getRangeAt(0);
        if (!range.collapsed || !node.contains(range.startContainer)) {
            return null;
        }

        if (range.startContainer.nodeType !== 3) {
            return null;
        }

        var textnode = range.startContainer;
        var offset = range.startOffset;
        var mentionRange = findMentionQuery(textnode.textContent, offset);
        if (!mentionRange) {
            return null;
        }

        return {
            textNode: textnode,
            mentionRange: mentionRange
        };
    };

    var replaceContenteditableRange = function(node, state, replacement, userid) {
        var textnode = state.textNode;
        var mentionRange = state.mentionRange;
        var value = textnode.textContent;
        var before = value.substring(0, mentionRange.start);
        var after = value.substring(mentionRange.end);

        if (!textnode.parentNode) {
            return;
        }

        var beforeNode = document.createTextNode(before);
        var mentionNode = document.createElement('span');
        mentionNode.className = 'local-mention-token';
        mentionNode.setAttribute('contenteditable', 'false');
        mentionNode.setAttribute('data-mention-userid', String(userid));
        mentionNode.textContent = replacement.trim();
        var spaceNode = document.createTextNode(' ');
        var afterNode = document.createTextNode(after);

        textnode.parentNode.insertBefore(beforeNode, textnode);
        textnode.parentNode.insertBefore(mentionNode, textnode);
        textnode.parentNode.insertBefore(spaceNode, textnode);
        textnode.parentNode.insertBefore(afterNode, textnode);
        textnode.parentNode.removeChild(textnode);

        var selection = window.getSelection();
        if (!selection) {
            return;
        }

        var range = document.createRange();
        range.setStart(afterNode, 0);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        node.dispatchEvent(new Event('input', {bubbles: true}));
    };

    var getMentionState = function(target) {
        if (!target) {
            return null;
        }

        if (target.tagName && target.tagName.toLowerCase() === 'textarea') {
            var mentionRange = findMentionQuery(target.value, target.selectionStart);
            if (!mentionRange) {
                return null;
            }
            return {
                type: 'textarea',
                mentionRange: mentionRange
            };
        }

        if (isContentEditable(target)) {
            var state = getContenteditableState(target);
            if (!state) {
                return null;
            }
            return {
                type: 'contenteditable',
                mentionRange: state.mentionRange,
                textNode: state.textNode
            };
        }

        return null;
    };

    var fetchCandidates = function(config, query) {
        return Ajax.call([{
            methodname: 'local_mention_search_users',
            args: {
                query: query,
                contextid: config.contextid,
                courseid: config.courseid || 0,
                limit: 10,
                searchallusers: !!config.searchallusers
            }
        }])[0];
    };

    var extractPlainMentions = function(text) {
        if (!text) {
            return [];
        }

        var mentions = [];
        var seen = {};
        var match;

        while ((match = PLAIN_MENTION_PATTERN.exec(text)) !== null) {
            var token = '@' + String(match[2] || '').trim();
            if (!token || token === '@' || seen[token]) {
                continue;
            }
            seen[token] = true;
            mentions.push(token);
        }

        return mentions;
    };

    var getUnresolvedMentions = function(target) {
        if (!target) {
            return [];
        }

        if (target.tagName && target.tagName.toLowerCase() === 'textarea') {
            return extractPlainMentions(target.value || '');
        }

        if (!isContentEditable(target)) {
            return [];
        }

        var unresolved = [];
        var seen = {};
        var walker = document.createTreeWalker(target, window.NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                if (!node || !node.nodeValue || !node.nodeValue.trim()) {
                    return window.NodeFilter.FILTER_REJECT;
                }

                var parent = node.parentNode;
                if (parent && parent.nodeType === 1 && parent.hasAttribute('data-mention-userid')) {
                    return window.NodeFilter.FILTER_REJECT;
                }

                return window.NodeFilter.FILTER_ACCEPT;
            }
        });
        var current;

        while ((current = walker.nextNode())) {
            extractPlainMentions(current.nodeValue || '').forEach(function(token) {
                if (!seen[token]) {
                    seen[token] = true;
                    unresolved.push(token);
                }
            });
        }

        return unresolved;
    };

    var getFormForTarget = function(target) {
        if (!target || !target.closest) {
            return null;
        }

        return target.closest('form');
    };

    var buildUnresolvedMessage = function(strings, mentions) {
        var summary = mentions.slice(0, 5).join('、');
        if (mentions.length > 5) {
            summary += strings.moreSuffix.replace('{$a}', String(mentions.length - 5));
        }

        return strings.body
            .replace(/\\n/g, '\n')
            .replace('{$a}', summary);
    };

    var promptUnresolvedMentions = function(mentions, onReselect, onIgnore) {
        Str.get_strings([
            {key: 'unresolvedmentionsconfirmtitle', component: 'local_mention'},
            {key: 'unresolvedmentionsconfirmbody', component: 'local_mention'},
            {key: 'unresolvedmentionsconfirmmoresuffix', component: 'local_mention'}
        ]).then(function(results) {
            var strings = {
                title: results[0],
                body: results[1],
                moreSuffix: results[2]
            };
            var confirmed = window.confirm(strings.title + '\n\n' + buildUnresolvedMessage(strings, mentions));
            if (confirmed) {
                onReselect();
                return;
            }

            onIgnore();
        }).catch(Notification.exception);
    };

    var bindSubmitGuard = function(target) {
        var form = getFormForTarget(target);
        if (!form || form.getAttribute(FORM_BOUND_ATTR) === '1') {
            return;
        }

        form.setAttribute(FORM_BOUND_ATTR, '1');
        form.addEventListener('submit', function(e) {
            if (form.getAttribute(IGNORE_ATTR) === '1') {
                form.removeAttribute(IGNORE_ATTR);
                return;
            }

            var unresolved = [];
            var editors = form.querySelectorAll(
                'textarea[name="message[text]"], textarea[name="message"], #id_message, ' +
                '.editor_atto_content[contenteditable="true"], .hsuforum-textarea[contenteditable="true"]'
            );

            Array.prototype.slice.call(editors).forEach(function(editor) {
                getUnresolvedMentions(editor).forEach(function(token) {
                    if (unresolved.indexOf(token) === -1) {
                        unresolved.push(token);
                    }
                });
            });

            if (!unresolved.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            promptUnresolvedMentions(unresolved, function() {
                if (typeof target.focus === 'function') {
                    target.focus();
                }
            }, function() {
                form.setAttribute(IGNORE_ATTR, '1');
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });

        var resetIgnore = function() {
            form.removeAttribute(IGNORE_ATTR);
        };

        form.addEventListener('input', resetIgnore);
        form.addEventListener('change', resetIgnore);
    };

    var wireTarget = function(target, config) {
        if (!target || target.getAttribute(BOUND_ATTR) === '1') {
            return;
        }

        target.setAttribute(BOUND_ATTR, '1');
        bindSubmitGuard(target);
        var menu = createDropdown();
        var state = {
            activeIndex: 0,
            items: [],
            mentionRange: null,
            textNode: null,
            lastQuery: '',
            composing: false,
            refreshTimer: null,
            pick: function(index) {
                if (!state.items[index] || !state.mentionRange) {
                    return;
                }
                var item = state.items[index];

                if (state.type === 'textarea') {
                    replaceTextareaRange(target, state.mentionRange.start, state.mentionRange.end, '@' + item.fullname + ' ');
                } else if (state.type === 'contenteditable' && state.textNode) {
                    replaceContenteditableRange(target, {
                        textNode: state.textNode,
                        mentionRange: state.mentionRange
                    }, '@' + item.fullname + ' ', item.id);
                }

                state.items = [];
                state.mentionRange = null;
                state.textNode = null;
                state.lastQuery = '';
                menu.style.display = 'none';
            }
        };

        var refresh = function(triggerKeyCode) {
            if (state.composing) {
                return;
            }

            if (isNavigationKey(triggerKeyCode)) {
                return;
            }

            var mentionState = getMentionState(target);
            if (!mentionState) {
                state.items = [];
                state.mentionRange = null;
                state.textNode = null;
                state.lastQuery = '';
                menu.style.display = 'none';
                return;
            }

            state.type = mentionState.type;
            state.mentionRange = mentionState.mentionRange;
            state.textNode = mentionState.textNode || null;

            var currentQuery = mentionState.mentionRange.query;
            var minQueryLength = getMinimumQueryLength(currentQuery);
            if (currentQuery.length < minQueryLength) {
                state.items = [];
                state.mentionRange = null;
                state.textNode = null;
                state.lastQuery = '';
                menu.style.display = 'none';
                return;
            }

            var shouldResetActiveIndex = state.lastQuery !== currentQuery;

            fetchCandidates(config, currentQuery).then(function(items) {
                state.items = items || [];
                if (shouldResetActiveIndex) {
                    state.activeIndex = 0;
                } else if (state.items.length) {
                    state.activeIndex = Math.min(state.activeIndex, state.items.length - 1);
                }
                state.lastQuery = currentQuery;
                positionDropdown(target, menu, state);
                renderMenu(menu, state.items, state);
            }).catch(Notification.exception);
        };

        var scheduleRefresh = function(triggerKeyCode) {
            if (state.refreshTimer) {
                clearTimeout(state.refreshTimer);
            }

            state.refreshTimer = window.setTimeout(function() {
                state.refreshTimer = null;
                refresh(triggerKeyCode);
            }, 180);
        };

        target.addEventListener('input', function() {
            scheduleRefresh();
        });
        target.addEventListener('compositionstart', function() {
            state.composing = true;
        });
        target.addEventListener('compositionend', function() {
            state.composing = false;
            scheduleRefresh();
        });
        target.addEventListener('keydown', function(e) {
            if (menu.style.display === 'none' || !state.items.length) {
                return;
            }

            if (e.keyCode === KEY_DOWN) {
                e.preventDefault();
                state.activeIndex = (state.activeIndex + 1) % state.items.length;
                renderMenu(menu, state.items, state);
            } else if (e.keyCode === KEY_UP) {
                e.preventDefault();
                state.activeIndex = (state.activeIndex - 1 + state.items.length) % state.items.length;
                renderMenu(menu, state.items, state);
            } else if (e.keyCode === KEY_ENTER) {
                e.preventDefault();
                state.pick(state.activeIndex);
            } else if (e.keyCode === KEY_ESCAPE) {
                menu.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
    };

    var wireMatchingNodes = function(config, root) {
        var scope = root || document;
        var nodes = [];

        if (root && root.nodeType === 1 && root.matches && root.matches(config.selector)) {
            nodes.push(root);
        }

        if (scope.querySelectorAll) {
            nodes = nodes.concat(Array.prototype.slice.call(scope.querySelectorAll(config.selector)));
        }

        nodes.forEach(function(node) {
            if ((node.tagName && node.tagName.toLowerCase() === 'textarea') || isContentEditable(node)) {
                wireTarget(node, config);
            }
        });
    };

    var observeMutations = function(config) {
        if (!window.MutationObserver) {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                Array.prototype.slice.call(mutation.addedNodes || []).forEach(function(node) {
                    if (node.nodeType === 1) {
                        wireMatchingNodes(config, node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    };

    var init = function(config) {
        if (!config || !config.selector || !config.contextid) {
            return;
        }

        if (!document.body) {
            return;
        }

        wireMatchingNodes(config, document);
        observeMutations(config);
    };

    return {
        init: init
    };
});
