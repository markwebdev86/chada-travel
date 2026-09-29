(function () {
    'use strict';

    function activateTab(tabList, tab) {
        var root = tabList.closest('[data-chada-travel-tabs]');
        if (!root) return;
        var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[role="tab"]'));
        tabs.forEach(function (item) {
            var selected = item === tab;
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.tabIndex = selected ? 0 : -1;
            var panel = document.getElementById(item.getAttribute('aria-controls'));
            if (panel) panel.hidden = !selected;
        });
        tab.focus();
    }

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('[role="tab"]');
        if (!tab) return;
        var tabList = tab.closest('[role="tablist"]');
        if (tabList) activateTab(tabList, tab);
    });

    document.addEventListener('keydown', function (event) {
        var tab = event.target.closest('[role="tab"]');
        if (!tab) return;
        var tabList = tab.closest('[role="tablist"]');
        if (!tabList) return;
        var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[role="tab"]'));
        var index = tabs.indexOf(tab);
        var nextIndex = index;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') nextIndex = (index + 1) % tabs.length;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') nextIndex = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') nextIndex = 0;
        if (event.key === 'End') nextIndex = tabs.length - 1;
        if (nextIndex !== index) {
            event.preventDefault();
            activateTab(tabList, tabs[nextIndex]);
        }
    });
}());
