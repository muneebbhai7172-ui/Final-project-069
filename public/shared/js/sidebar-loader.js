/**
 * Sidebar Loader - Loads Advanced Sidebar on pages
 * This script loads the advanced sidebar HTML component and JS
 */

(function() {
    'use strict';

    console.log('📂 Sidebar Loader: Starting...');

    // Exclude auth pages
    const excludedPages = ['/login', '/register', '/auth/'];
    const currentPath = window.location.pathname.toLowerCase();
    const shouldLoadSidebar = !excludedPages.some(page => currentPath.includes(page));

    if (!shouldLoadSidebar) {
        console.log('ℹ️ Sidebar Loader: Skipped for auth page');
        return;
    }

    // Determine base path based on current location
    function getBasePath() {
        const path = window.location.pathname;
        // Handle both Laravel dev server and XAMPP paths
        if (path.includes('/modules/')) {
            // From /modules/stock/ we need ../../ to get to root
            return '../../';
        } else if (path.includes('/admin/')) {
            return '../';
        } else {
            return './';
        }
    }

    const basePath = getBasePath();
    console.log('📂 Sidebar Loader: basePath =', basePath);

    // Load Advanced Sidebar CSS if not already loaded
    if (!document.querySelector('link[href*="advanced-sidebar.css"]')) {
        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = basePath + 'shared/css/advanced-sidebar.css?v=' + Date.now();
        document.head.appendChild(cssLink);
        console.log('📂 Sidebar Loader: CSS loaded');
    }

    // Check if sidebar already exists in HTML
    const existingSidebar = document.getElementById('modernSidebar');
    if (existingSidebar) {
        // Sidebar already in HTML, just load the JS
        loadSidebarJS();
        return;
    }

    // Check for sidebar container
    const container = document.getElementById('sidebar-container');
    if (container) {
        // Load sidebar HTML into container
        const sidebarUrl = basePath + 'shared/components/advanced-sidebar.html?v=clean-light-v2';
        console.log('📂 Sidebar Loader: Fetching from', sidebarUrl);

        fetch(sidebarUrl)
            .then(response => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;

                // Fix navigation links - make them relative to modules folder
                fixNavLinks();

                // Also add mobile toggle and overlay if not present
                if (!document.getElementById('mobileSidebarToggle')) {
                    const mobileBtn = document.createElement('button');
                    mobileBtn.className = 'mobile-sidebar-toggle';
                    mobileBtn.id = 'mobileSidebarToggle';
                    mobileBtn.innerHTML = '<i class="fas fa-bars"></i>';
                    document.body.appendChild(mobileBtn);
                }

                if (!document.getElementById('sidebarOverlay')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.id = 'sidebarOverlay';
                    document.body.appendChild(overlay);
                }

                loadSidebarJS();
                console.log('✅ Sidebar Loader: Advanced sidebar loaded');
            })
            .catch(error => {
                console.error('❌ Sidebar Loader: Failed to load sidebar', error);
            });
    } else {
        // No container, create sidebar dynamically
        fetch(basePath + 'shared/components/advanced-sidebar.html?v=clean-light-v2')
            .then(response => response.text())
            .then(html => {
                // Insert at beginning of body
                document.body.insertAdjacentHTML('afterbegin', html);

                // Add mobile toggle
                if (!document.getElementById('mobileSidebarToggle')) {
                    const mobileBtn = document.createElement('button');
                    mobileBtn.className = 'mobile-sidebar-toggle';
                    mobileBtn.id = 'mobileSidebarToggle';
                    mobileBtn.innerHTML = '<i class="fas fa-bars"></i>';
                    document.body.insertAdjacentElement('afterbegin', mobileBtn);
                }

                // Add overlay
                if (!document.getElementById('sidebarOverlay')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.id = 'sidebarOverlay';
                    document.body.appendChild(overlay);
                }

                loadSidebarJS();
                console.log('✅ Sidebar Loader: Advanced sidebar injected');
            })
            .catch(error => {
                console.error('❌ Sidebar Loader: Failed to load sidebar', error);
            });
    }

    function loadSidebarJS() {
        // Load sidebar JS if not already loaded
        if (!document.querySelector('script[src*="advanced-sidebar.js"]')) {
            const script = document.createElement('script');
            script.src = basePath + 'shared/js/advanced-sidebar.js?v=' + Date.now();
            document.body.appendChild(script);
        }
    }

    function fixNavLinks() {
        // Fix navigation links to work from current page location
        const currentPath = window.location.pathname;
        const sidebar = document.getElementById('modernSidebar');
        if (!sidebar) return;

        const links = sidebar.querySelectorAll('a.nav-link');
        links.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('../')) {
                // Links like ../dashboard/index.html need to become modules/dashboard/index.html
                // when we're in /modules/stock/
                if (currentPath.includes('/modules/')) {
                    // Keep as is - relative links from one module to another
                    // ../dashboard/index.html from /modules/stock/ goes to /modules/dashboard/
                    // This is correct!
                }
            }
        });

        // Highlight current page
        const pageName = currentPath.split('/').filter(Boolean).slice(-2, -1)[0] || 'dashboard';
        links.forEach(link => {
            link.classList.remove('active');
            if (link.href.includes('/' + pageName + '/')) {
                link.classList.add('active');
            }
        });
    }
})();
