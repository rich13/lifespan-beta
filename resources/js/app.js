import './bootstrap';
import 'bootstrap';
import './dropdown-debug';
import './debug';
import './routes';
import './tools-button-functions';
import SessionBridge from './session-bridge';

// Expose SessionBridge globally for use in templates
window.SessionBridge = SessionBridge;

// Import timeline manager
import './timeline/timeline-manager';

// Import shared components first
import './shared/user-switcher';
import './mobile-right-nav';

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
