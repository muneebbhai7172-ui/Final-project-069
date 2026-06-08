/**
 * Modern Sidebar JavaScript Component
 * Enhanced sidebar with collapsible menu, animations, and responsive design
 */

class ModernSidebar {
    constructor() {
        this.sidebar = null;
        this.mainContent = null;
        this.toggleBtn = null;
        this.overlay = null;
        this.isCollapsed = false;
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }

    init() {
        const sidebarCreated = this.createSidebar();
        if (sidebarCreated) {
            this.setupEventListeners();
            this.loadUserInfo();
            this.setActivePage();
            this.loadSidebarState();
        }
    }

    createSidebar() {
        // Get or create sidebar overlay for mobile
        this.overlay = document.getElementById('sidebar-overlay');
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.id = 'sidebar-overlay';
            this.overlay.className = 'sidebar-overlay';
            document.body.appendChild(this.overlay);
        }

        // Get sidebar element
        this.sidebar = document.querySelector('.modern-sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.toggleBtn = document.querySelector('.sidebar-toggle');

        if (!this.sidebar) {
            console.warn('Modern sidebar element not found - page may not have sidebar');
            return false;
        }
        
        return true;
    }

    setupEventListeners() {
        // Check if sidebar exists before setting up listeners
        if (!this.sidebar) {
            console.warn('Sidebar not found, skipping event listeners setup');
            return;
        }

        // Toggle button
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.toggle());
        }

        // Overlay click (mobile)
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMobile());
        }

        // Dropdown menus
        const dropdownToggles = this.sidebar.querySelectorAll('.nav-dropdown > .nav-link');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDropdown(toggle.parentElement);
            });
        });

        // Window resize
        window.addEventListener('resize', () => {
            this.isMobile = window.innerWidth <= 768;
            if (!this.isMobile && this.sidebar.classList.contains('mobile-open')) {
                this.closeMobile();
            }
        });

        // Escape key to close mobile menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && this.sidebar.classList.contains('mobile-open')) {
                this.closeMobile();
            }
        });
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
        this.sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);

        // Close all dropdowns when collapsing
        if (this.isCollapsed) {
            const openDropdowns = this.sidebar.querySelectorAll('.nav-dropdown.active');
            openDropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        }
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

    toggleDropdown(dropdownElement) {
        const isActive = dropdownElement.classList.contains('active');
        
        // Close all dropdowns
        const allDropdowns = this.sidebar.querySelectorAll('.nav-dropdown');
        allDropdowns.forEach(dropdown => {
            if (dropdown !== dropdownElement) {
                dropdown.classList.remove('active');
            }
        });

        // Toggle current dropdown
        dropdownElement.classList.toggle('active', !isActive);
    }

    setActivePage() {
        // Get current page
        const currentPage = window.location.pathname.split('/').pop().replace('.html', '') || 'dashboard';
        
        // Set active link
        const navLinks = this.sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.remove('active');
            
            const href = link.getAttribute('href');
            if (href) {
                const linkPage = href.split('/').pop().replace('.html', '');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                    
                    // Open parent dropdown if exists
                    const parentDropdown = link.closest('.nav-dropdown');
                    if (parentDropdown) {
                        parentDropdown.classList.add('active');
                    }
                }
            }
        });
    }

    loadUserInfo() {
        // Load user info from localStorage
        const userStr = localStorage.getItem('user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                this.updateUserDisplay(user);
            } catch (e) {
                console.error('Error parsing user data:', e);
            }
        }
    }

    updateUserDisplay(user) {
        const userName = this.sidebar.querySelector('.user-name');
        const userRole = this.sidebar.querySelector('.user-role');
        
        if (userName && user.username) {
            userName.textContent = user.username;
        }
        
        if (userRole && user.role) {
            userRole.textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
        }
    }

    loadSidebarState() {
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true' && !this.isMobile) {
            this.isCollapsed = true;
            this.sidebar.classList.add('collapsed');
        }
    }
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear local storage
        localStorage.removeItem('user');
        localStorage.removeItem('session_token');
        
        // Call logout API
        fetch('../api/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            window.location.href = '../login.html';
        })
        .catch(error => {
            console.error('Logout error:', error);
            window.location.href = '../login.html';
        });
    }
}

// Initialize on DOM load or when sidebar is ready
function initModernSidebar() {
    if (document.querySelector('.modern-sidebar')) {
        const modernSidebar = new ModernSidebar();
        // Make it globally accessible
        window.modernSidebar = modernSidebar;
        return true;
    }
    return false;
}

document.addEventListener('DOMContentLoaded', () => {
    // Try to initialize immediately
    if (!initModernSidebar()) {
        // If sidebar not found, wait a bit for template-loader to finish
        setTimeout(() => {
            if (!initModernSidebar()) {
                console.warn('Sidebar not loaded after delay - page may not have sidebar');
            }
        }, 100);
    }
});
