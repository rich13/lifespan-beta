import './bootstrap';
import 'bootstrap';
import './dropdown-debug';
import './debug';

// Import page-specific scripts
import './spans/show';
import './spans/index';
import './spans/edit';
import './layouts/user-dropdown';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
