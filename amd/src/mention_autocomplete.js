define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var KEY_UP = 38;
    var KEY_DOWN = 40;
    var KEY_ENTER = 13;
    var KEY_ESCAPE = 27;
    var BOUND_ATTR = 'data-local-mention-bound';

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
        var match = left.match(/(^|\s)@([A-Za-z0-9._-]{1,100})$/);
        if (!match) {
            return null;
        }
        return {
            query: match[2],
            start: caretPos - match[2].length - 1,
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

    var replaceContenteditableRange = function(node, state, replacement) {
        var textnode = state.textNode;
        var mentionRange = state.mentionRange;
        var value = textnode.textContent;
        textnode.textContent = value.substring(0, mentionRange.start) + replacement + value.substring(mentionRange.end);

        var selection = window.getSelection();
        if (!selection) {
            return;
        }

        var range = document.createRange();
        var newPos = mentionRange.start + replacement.length;
        range.setStart(textnode, Math.min(newPos, textnode.textContent.length));
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
                limit: 10
            }
        }])[0];
    };

    var wireTarget = function(target, config) {
        if (!target || target.getAttribute(BOUND_ATTR) === '1') {
            return;
        }

        target.setAttribute(BOUND_ATTR, '1');
        var menu = createDropdown();
        var state = {
            activeIndex: 0,
            items: [],
            mentionRange: null,
            textNode: null,
            pick: function(index) {
                if (!state.items[index] || !state.mentionRange) {
                    return;
                }
                var item = state.items[index];

                if (state.type === 'textarea') {
                    replaceTextareaRange(target, state.mentionRange.start, state.mentionRange.end, '@' + item.username + ' ');
                } else if (state.type === 'contenteditable' && state.textNode) {
                    replaceContenteditableRange(target, {
                        textNode: state.textNode,
                        mentionRange: state.mentionRange
                    }, '@' + item.username + ' ');
                }

                state.items = [];
                state.mentionRange = null;
                state.textNode = null;
                menu.style.display = 'none';
            }
        };

        var refresh = function() {
            var mentionState = getMentionState(target);
            if (!mentionState) {
                state.items = [];
                state.mentionRange = null;
                state.textNode = null;
                menu.style.display = 'none';
                return;
            }

            state.type = mentionState.type;
            state.mentionRange = mentionState.mentionRange;
            state.textNode = mentionState.textNode || null;

            fetchCandidates(config, mentionState.mentionRange.query).then(function(items) {
                state.items = items || [];
                state.activeIndex = 0;
                positionDropdown(target, menu, state);
                renderMenu(menu, state.items, state);
            }).catch(Notification.exception);
        };

        target.addEventListener('input', refresh);
        target.addEventListener('keyup', refresh);
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
