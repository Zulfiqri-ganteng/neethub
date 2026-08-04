

// Import Bootstrap JS
import * as bootstrap from 'bootstrap';
import './custom.js';

// Vite bundles this as an ES module, so `bootstrap` is a local module
// binding, not a global — unlike the old CDN <script> version. Our own
// page scripts (customers.php, packages.php, etc.) call `bootstrap.Modal`
// directly, so it has to be exposed on window explicitly.
window.bootstrap = bootstrap;


// Import SCSS
import '../scss/style.scss';