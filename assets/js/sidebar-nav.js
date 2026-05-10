/**
 * Sidebar nav: collapsible groups + localStorage persistence.
 * Adds <html class="js"> when this file runs (and layout uses the same inline hook). Without JS, groups stay expanded.
 */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    var STORAGE_KEY = 'komodo.sidebar.navGroups.v1';

    function readState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return {};
            }
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : {};
        } catch (e) {
            return {};
        }
    }

    function writeState(obj) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
        } catch (e) {
            /* quota / private mode */
        }
    }

    function setExpanded(section, btn, panel, expanded) {
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (expanded) {
            panel.removeAttribute('hidden');
            section.classList.remove('sidebar-nav-section--collapsed');
        } else {
            panel.setAttribute('hidden', '');
            section.classList.add('sidebar-nav-section--collapsed');
        }
    }

    function init() {
        var nav = document.querySelector('.sidebar-nav');
        if (!nav) {
            return;
        }

        var activeGroup = nav.getAttribute('data-sidebar-active-group') || '';
        var sections = nav.querySelectorAll('.sidebar-nav-section[data-sidebar-group]');
        var state = readState();

        sections.forEach(function (section) {
            var groupId = section.getAttribute('data-sidebar-group') || '';
            var btn = section.querySelector('.sidebar-nav-section-toggle');
            var panel = section.querySelector('.sidebar-nav-panel');
            if (!btn || !panel) {
                return;
            }

            var isActiveGroup = activeGroup !== '' && groupId === activeGroup;
            var stored = state[groupId];
            var expanded = isActiveGroup ? true : stored !== false;

            setExpanded(section, btn, panel, expanded);
            btn.removeAttribute('tabindex');

            btn.addEventListener('click', function () {
                var open = btn.getAttribute('aria-expanded') === 'true';
                if (open && groupId === activeGroup) {
                    return;
                }
                var next = !open;
                setExpanded(section, btn, panel, next);
                state[groupId] = next;
                writeState(state);
            });
        });

        /* Reserved: compact sidebar density (future) */
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
