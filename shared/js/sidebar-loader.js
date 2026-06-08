/**
 * Sidebar Loader - Loads Advanced Sidebar on pages
 * This script loads the advanced sidebar HTML component and JS
 */

(function() {
    'use strict';

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
        if (path.includes('/modules/')) {
            return '../../';
        } else if (path.includes('/admin/')) {
            return '../';
        } else {
            return '';
        }
    }

    const basePath = getBasePath();

    // Load Advanced Sidebar CSS if not already loaded
    if (!document.querySelector('link[href*="advanced-sidebar.css"]')) {
        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = basePath + 'shared/css/advanced-sidebar.css?v=' + Date.now();
        document.head.appendChild(cssLink);
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
        fetch(basePath + 'shared/components/advanced-sidebar.html')
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;

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
        fetch(basePath + 'shared/components/advanced-sidebar.html')
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
})();
