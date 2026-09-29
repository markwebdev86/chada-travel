/**
 * Progressive-enhancement behavior shared by Chada Travel - Agency Manager admin screens: accessible tab panels and
 * roving-tabindex keyboard navigation for every role="tablist" on the page.
 *
 * Every tab and its target already renders correctly from a direct URL with JavaScript disabled; this script
 * only avoids a full page reload when both the tab and its panel already exist in the current page.
 *
 * A tablist whose tabs address their panel via a URL hash (Instructions is the only one today - its href/
 * data-chada-travel-href are "#<slug>", matching each panel's own id) is additionally synced from
 * window.location.hash on load and on every "hashchange" (so a direct link to a specific tab, and the
 * browser's Back/Forward through the history entries activateTab() pushes on click, both land on the right
 * panel). This has no effect on a tablist whose hrefs are full admin URLs instead.
 */
(function () {
    'use strict';

    function panelToggleTablists() {
        return document.querySelectorAll('[data-chada-travel-tablist]');
    }

    function activateTab(tabs, panels, tab, updateUrl) {
        tabs.forEach(function (candidate) {
            var selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.setAttribute('tabindex', selected ? '0' : '-1');
        });
        panels.forEach(function (panel) {
            var matches = panel.id === tab.getAttribute('aria-controls');
            if (matches) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });
        if (updateUrl && tab.dataset.chadaTravelHref && window.history && window.history.pushState) {
            window.history.pushState({}, '', tab.dataset.chadaTravelHref);
        }
    }

    /**
     * Resolves each tab's own panel via its aria-controls id, scoped to just this tablist's tabs - never
     * document-wide - so a second tablist sharing the page (e.g. Booking Detail's per-application picker
     * nested inside its Documents tab) can't reach out and hide a sibling tablist's panels that merely
     * happen to also carry role="tabpanel".
     */
    function panelsFor(tabs) {
        return tabs.map(function (tab) {
            var id = tab.getAttribute('aria-controls');
            return id ? document.getElementById(id) : null;
        });
    }

    function tabsAndPanelsFor(list) {
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
        if (!tabs.length) {
            return null;
        }
        var panels = panelsFor(tabs);
        if (panels.indexOf(null) !== -1) {
            return null;
        }
        return {
            tabs: tabs,
            panels: panels
        };
    }

    /**
     * Idempotent: safe to call again on "hashchange" without re-attaching any listener. Falls back to
     * whichever tab the server already marked aria-selected="true" (the first tab) when the hash is empty or
     * does not match any tab in this list.
     */
    function activateFromHash(list) {
        var found = tabsAndPanelsFor(list);
        if (!found) {
            return;
        }
        var hash = window.location.hash ? window.location.hash.slice(1) : '';
        var target = hash && found.tabs.filter(function (tab) {
            return tab.getAttribute('aria-controls') === hash;
        })[0];
        if (!target) {
            target = found.tabs.filter(function (tab) {
                return tab.getAttribute('aria-selected') === 'true';
            })[0] || found.tabs[0];
        }
        activateTab(found.tabs, found.panels, target, false);
    }

    function wireTablist(list) {
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
        if (!tabs.length) {
            return;
        }
        var panels = panelsFor(tabs);
        var hasPanels = panels.indexOf(null) === -1;

        tabs.forEach(function (tab) {
            if (hasPanels) {
                tab.addEventListener('click', function (event) {
                    event.preventDefault();
                    activateTab(tabs, panels, tab, true);
                    tab.focus();
                });
            }
            tab.addEventListener('keydown', function (event) {
                var index = tabs.indexOf(tab);
                var target = null;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    target = tabs[(index + 1) % tabs.length];
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    target = tabs[(index - 1 + tabs.length) % tabs.length];
                } else if (event.key === 'Home') {
                    target = tabs[0];
                } else if (event.key === 'End') {
                    target = tabs[tabs.length - 1];
                }
                if (!target) {
                    return;
                }
                event.preventDefault();
                if (hasPanels) {
                    activateTab(tabs, panels, target, true);
                }
                target.focus();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tablists = Array.prototype.slice.call(panelToggleTablists());
        tablists.forEach(wireTablist);
        tablists.forEach(activateFromHash);
        window.addEventListener('hashchange', function () {
            tablists.forEach(activateFromHash);
        });

        // Confirmation prompts for irreversible-feeling but reversible admin actions (archive/restore/reject),
        // consistent with WordPress admin's native confirm() pattern; server-side validation remains authoritative.
        document.querySelectorAll('[data-chada-travel-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-chada-travel-confirm');
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        // Expand/collapse for sanitized audit-event metadata rows.
        document.querySelectorAll('[data-chada-travel-toggle-meta]').forEach(function (button) {
            button.addEventListener('click', function () {
                var row = document.getElementById(button.getAttribute('data-chada-travel-toggle-meta'));
                if (!row) {
                    return;
                }
                var isHidden = row.hasAttribute('hidden');
                if (isHidden) {
                    row.removeAttribute('hidden');
                } else {
                    row.setAttribute('hidden', 'hidden');
                }
                button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            });
        });

        // Shortcode copy controls use event delegation so the Instructions table remains safe if an admin
        // screen or builder-like wrapper clones its rows after this script has loaded.
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-chada-travel-copy-shortcode]');
            if (!button) {
                return;
            }
            event.preventDefault();
            copyShortcode(button, button.getAttribute('data-chada-travel-copy-shortcode') || '');
        });

        // Deposit Slip preview: the server-rendered <img> and its hidden fallback message already work with
        // JavaScript disabled; this only swaps which of the two is visible if the protected image fails to
        // load, without ever retrying the request or exposing the protected URL in the message.
        document.querySelectorAll('[data-chada-travel-deposit-slip-preview]').forEach(function (wrapper) {
            var image = wrapper.querySelector('[data-chada-travel-deposit-slip-preview-image]');
            var fallback = wrapper.querySelector('[data-chada-travel-deposit-slip-preview-fallback]');
            if (!image || !fallback) {
                return;
            }
            var showFallback = function () {
                image.hidden = true;
                fallback.hidden = false;
            };
            // A fast (e.g. 404) response can finish loading the image before this listener attaches, so the
            // native "error" event would otherwise never be observed; catch that already-settled case too.
            if (image.complete && image.naturalWidth === 0) {
                showFallback();
                return;
            }
            image.addEventListener('error', showFallback, { once: true });
        });
    });

    function copyShortcode(button, value) {
        if (!value) {
            return;
        }
        var feedback = button.parentElement.querySelector('.chada-travel-copy-feedback');
        var showFeedback = function (message) {
            if (!feedback) {
                return;
            }
            feedback.textContent = message;
            window.setTimeout(function () {
                feedback.textContent = '';
            }, 2000);
        };
        var fallbackCopy = function () {
            var textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            var copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }
            textarea.remove();
            return copied;
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                showFeedback('Copied');
            }).catch(function () {
                showFeedback(fallbackCopy() ? 'Copied' : 'Copy failed');
            });
            return;
        }
        showFeedback(fallbackCopy() ? 'Copied' : 'Copy failed');
    }

})();
