import './bootstrap';
import 'bootstrap';
import './dropdown-debug';
import './debug';
import './routes';

// Import page-specific scripts
import './spans/show';
import './spans/index';
import './spans/edit';
import './layouts/user-dropdown';
import './home-search';

// Import component enhancements
import './components/responsive-button-groups';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
