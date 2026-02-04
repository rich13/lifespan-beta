import './bootstrap';
import 'bootstrap';
import './dropdown-debug';
import './debug';
import './routes';
import './tools-button-functions';
// Session bridge disabled to isolate navigation B→A→B behaviour
// import SessionBridge from './session-bridge';
// window.SessionBridge = SessionBridge;
// No-op stub so layout and logout forms don't break
window.SessionBridge = {
    storeBridgeTokenFromServer: function() {},
    logout: function() {}
};

// Navigation debug: enable with localStorage.setItem('debug_nav', '1')
if (localStorage.getItem('debug_nav') === '1' && !window.__navDebugSetup) {
    window.__navDebugSetup = true;

    const navLog = (label, details = {}) => {
        const payload = {
            label,
            url: window.location.href,
            timestamp: new Date().toISOString(),
            ...details
        };
        // eslint-disable-next-line no-console
        console.warn('[nav-debug]', payload, new Error().stack);
    };

    window.addEventListener('beforeunload', () => navLog('beforeunload'));
    window.addEventListener('pagehide', (e) => navLog('pagehide', { persisted: e.persisted }));
    window.addEventListener('pageshow', (e) => navLog('pageshow', { persisted: e.persisted }));
    window.addEventListener('visibilitychange', () => navLog('visibilitychange', { state: document.visibilityState }));
    window.addEventListener('popstate', () => navLog('popstate'));

    const wrapMethod = (target, key, labelPrefix = '') => {
        if (!target || typeof target[key] !== 'function') {
            return;
        }

        const original = target[key];
        target[key] = function wrappedMethod(...args) {
            navLog(`${labelPrefix}${key}`, { args });
            return original.apply(this, args);
        };
    };

    wrapMethod(history, 'pushState');
    wrapMethod(history, 'replaceState');

    if (window.Location && window.Location.prototype) {
        const wrapLocation = (method) => {
            if (typeof window.Location.prototype[method] !== 'function') {
                return;
            }

            const original = window.Location.prototype[method];
            window.Location.prototype[method] = function wrappedLocationMethod(...args) {
                navLog(`location.${method}`, { args });
                return original.apply(this, args);
            };
        };

        ['assign', 'replace', 'reload'].forEach((method) => {
            try {
                wrapLocation(method);
            } catch (error) {
                navLog('location.wrap-error', { method, error: error.message });
            }
        });
    }
}

// Import timeline manager
import './timeline/timeline-manager';

// Import shared components first
import './shared/user-switcher';
import './mobile-right-nav';

// Import admin mode toggle
import './admin-mode-toggle';

// Import page-specific scripts
import './spans/show';
import './spans/index';
import './spans/edit';
import './layouts/user-dropdown';

// Import component enhancements
import './components/responsive-button-groups';

// Import modal functionality
import './add-connection-modal';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
