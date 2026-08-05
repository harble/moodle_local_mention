define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var KEY_UP = 38;
    var KEY_DOWN = 40;
    var KEY_ENTER = 13;
    var KEY_ESCAPE = 27;

    var createDropdown = function(textarea) {
        var menu = document.createElement('ul');
        menu.className = 'local-mention-menu';
        menu.style.position = 'absolute';
        menu.style.zIndex = '9999';
        menu.style.display = 'none';
        menu.style.listStyle = 'none';
        menu.style.padding = '4px';
        menu.style.margin = '0';
        menu.style.background = '#fff';
        menu.style.border = '1px solid #ddd';
        menu.style.maxHeight = '240px';
        menu.style.overflowY = 'auto';
        textarea.parentNode.style.position = 'relative';
        textarea.parentNode.appendChild(menu);
        return menu;
    };

    var positionDropdown = function(textarea, menu) {
        menu.style.left = '0px';
        menu.style.top = (textarea.offsetTop + textarea.offsetHeight + 2) + 'px';
        menu.style.width = Math.max(280, textarea.offsetWidth * 0.6) + 'px';
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
            li.textContent = item.display;
            li.style.padding = '4px 8px';
            li.style.cursor = 'pointer';
            li.dataset.index = String(idx);
            if (idx === state.activeIndex) {
                li.style.background = '#f3f3f3';
            }
            li.addEventListener('mousedown', function(e) {
                e.preventDefault();
                state.pick(idx);
            });
            menu.appendChild(li);
        });

        menu.style.display = 'block';
    };

    var replaceRange = function(textarea, start, end, replacement) {
        var value = textarea.value;
        textarea.value = value.substring(0, start) + replacement + value.substring(end);
        var newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
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

    var wireTextarea = function(textarea, config) {
        var menu = createDropdown(textarea);
        var state = {
            activeIndex: 0,
            items: [],
            mentionRange: null,
            pick: function(index) {
                if (!state.items[index] || !state.mentionRange) {
                    return;
                }
                var item = state.items[index];
                replaceRange(textarea, state.mentionRange.start, state.mentionRange.end, '@' + item.username + ' ');
                state.items = [];
                state.mentionRange = null;
                menu.style.display = 'none';
            }
        };

        var refresh = function() {
            var caret = textarea.selectionStart;
            var mentionRange = findMentionQuery(textarea.value, caret);
            if (!mentionRange) {
                state.items = [];
                state.mentionRange = null;
                menu.style.display = 'none';
                return;
            }

            state.mentionRange = mentionRange;
            fetchCandidates(config, mentionRange.query).then(function(items) {
                state.items = items || [];
                state.activeIndex = 0;
                positionDropdown(textarea, menu);
                renderMenu(menu, state.items, state);
            }).catch(Notification.exception);
        };

        textarea.addEventListener('input', refresh);
        textarea.addEventListener('keydown', function(e) {
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

    var init = function(config) {
        if (!config || !config.selector || !config.contextid) {
            return;
        }

        var nodes = document.querySelectorAll(config.selector);
        if (!nodes.length) {
            return;
        }

        nodes.forEach(function(node) {
            if (node.tagName && node.tagName.toLowerCase() === 'textarea') {
                wireTextarea(node, config);
            }
        });
    };

    return {
        init: init
    };
});
