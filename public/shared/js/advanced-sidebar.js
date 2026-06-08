/**
 * Advanced Sidebar JavaScript
 * Handles toggle, collapse, mobile menu, and user info
 */

(function() {
    'use strict';

    class AdvancedSidebar {
        constructor() {
            this.sidebar = document.getElementById('modernSidebar');
            this.toggleBtn = document.getElementById('sidebarToggle');
            this.mobileToggle = document.getElementById('mobileSidebarToggle');
            this.overlay = document.getElementById('sidebarOverlay');
            this.isCollapsed = false;
            this.isMobile = window.innerWidth <= 992;

            if (this.sidebar) {
                this.init();
            }
        }

        init() {
            this.loadState();
            this.setupEventListeners();
            this.setActivePage();
            this.loadUserInfo();
            this.addTooltips();
        }

        setupEventListeners() {
            // Desktop toggle
            if (this.toggleBtn) {
                this.toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggle();
                });
            }

            // Mobile toggle
            if (this.mobileToggle) {
                this.mobileToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleMobile();
                });
            }

            // Overlay click
            if (this.overlay) {
                this.overlay.addEventListener('click', () => this.closeMobile());
            }

            // Resize handler
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.isMobile = window.innerWidth <= 992;
                    if (!this.isMobile) {
                        this.closeMobile();
                    }
                }, 100);
            });

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.isMobile && this.sidebar.classList.contains('mobile-open')) {
                        this.closeMobile();
                    }
                }
            });

            // Logout handler
            const logoutBtn = document.getElementById('sidebarLogout');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.logout();
                });
            }
        }

        toggle() {
            if (this.isMobile) {
                this.toggleMobile();
            } else {
                this.toggleCollapse();
            }
        }

        toggleCollapse() {
            this.isCollapsed = !this.isCollapsed;
            this.sidebar.classList.toggle('collapsed', this.isCollapsed);
            document.body.classList.toggle('sidebar-collapsed', this.isCollapsed);
            localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        }

        toggleMobile() {
            const isOpen = this.sidebar.classList.toggle('mobile-open');
            this.overlay.classList.toggle('active', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        closeMobile() {
            this.sidebar.classList.remove('mobile-open');
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        loadState() {
            const saved = localStorage.getItem('sidebarCollapsed');
            if (saved === 'true' && !this.isMobile) {
                this.isCollapsed = true;
                this.sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
        }

        setActivePage() {
            const currentPath = window.location.pathname;
            const navLinks = this.sidebar.querySelectorAll('.nav-link');

            navLinks.forEach(link => {
                link.classList.remove('active');
                const href = link.getAttribute('href');
                if (href) {
                    // Check if current path contains the link's target
                    const linkPage = href.split('/').pop().replace('.html', '');
                    const currentPage = currentPath.split('/').pop().replace('.html', '') || 'index';

                    if (linkPage === currentPage ||
                        (linkPage === 'index' && currentPath.includes('/dashboard/')) ||
                        currentPath.includes(href.replace('../', '/modules/').replace('.html', ''))) {
                        link.classList.add('active');
                    }
                }
            });
        }

        addTooltips() {
            const navLinks = this.sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const text = link.querySelector('.nav-text');
                if (text) {
                    link.setAttribute('data-tooltip', text.textContent.trim());
                }
            });
        }

        loadUserInfo() {
            // Try localStorage first
            const userStr = localStorage.getItem('user');
            if (userStr) {
                try {
                    const user = JSON.parse(userStr);
                    this.updateUserDisplay(user);
                } catch (e) {
                    console.error('Error parsing user data:', e);
                }
            }

            // Also try API
            fetch('/api/auth/user', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.user) {
                    this.updateUserDisplay(data.user);
                    localStorage.setItem('user', JSON.stringify(data.user));
                }
            })
            .catch(() => {});
        }

        updateUserDisplay(user) {
            const nameEl = document.getElementById('sidebarUserName');
            const roleEl = document.getElementById('sidebarUserRole');
            const avatarEl = document.getElementById('sidebarUserAvatar');

            if (nameEl) nameEl.textContent = user.name || user.username || 'User';
            if (roleEl) roleEl.textContent = user.role || 'Staff';
            if (avatarEl) {
                const name = encodeURIComponent(user.name || user.username || 'User');
                avatarEl.src = `https://ui-avatars.com/api/?name=${name}&background=0d9488&color=fff&size=100`;
            }
        }

        async logout() {
            try {
                await fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
            } catch (e) {}

            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
            window.location.href = '/modules/auth/login.html';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new AdvancedSidebar());
    } else {
        new AdvancedSidebar();
    }
})();
