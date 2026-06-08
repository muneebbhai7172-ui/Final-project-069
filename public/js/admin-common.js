/**
 * Admin Common JavaScript
 * Common utilities and functions for admin pages
 * Updated for Laravel backend with CSRF token support
 */

// Initialize common admin functionality
(function() {
    'use strict';

    // Check authentication on page load - disabled for development
    // checkAdminAuth();

    // Setup CSRF token for all AJAX requests
    setupCSRFToken();

    // Setup common event listeners
    setupCommonListeners();

    /**
     * Check if admin is authenticated
     */
    async function checkAdminAuth() {
        console.log('[Admin Common] Checking authentication...');

        // Check if we just logged in - skip auth check to avoid race condition
        const justLoggedIn = sessionStorage.getItem('just_logged_in');
        if (justLoggedIn === 'true') {
            sessionStorage.removeItem('just_logged_in'); // Clear flag
            const storedUser = sessionStorage.getItem('user');
            if (storedUser) {
                try {
                    const user = JSON.parse(storedUser);
                    console.log('[Admin Common] Just logged in, using stored user:', user.username);
                    localStorage.setItem('user', storedUser);
                    console.log('[Admin Common] User authenticated (fresh login)');
                    return; // Skip API check
                } catch (e) {
                    console.error('[Admin Common] Error parsing stored user:', e);
                }
            }
        }

        // Check if we have user data in sessionStorage
        const storedUser = sessionStorage.getItem('user');
        if (storedUser) {
            try {
                const user = JSON.parse(storedUser);
                console.log('[Admin Common] Found stored user:', user.username);
                localStorage.setItem('user', storedUser);
                console.log('[Admin Common] User is authenticated from session storage');
                return; // Don't make API call if we have valid session data
            } catch (e) {
                console.error('[Admin Common] Error parsing stored user:', e);
            }
        }

        try {
            const data = await apiFetch('/api/auth/check');

            console.log('[Admin Common] Auth check response:', data);

            if (!data.success || !data.authenticated) {
                console.warn('[Admin Common] Not authenticated, redirecting to login');
                redirectToLogin();
                return;
            }

            // Store user info if authenticated
            if (data.user) {
                console.log('[Admin Common] User authenticated:', data.user.username, 'Role:', data.user.role);
                sessionStorage.setItem('user', JSON.stringify(data.user));
                localStorage.setItem('user', JSON.stringify(data.user));

                // Optional: Show warning if not admin/manager but still allow access
                if (data.user.role !== 'admin' && data.user.role !== 'manager') {
                    console.log('User logged in with role:', data.user.role);
                }
            } else {
                console.error('[Admin Common] No user data in response');
                showError('Authentication failed. Please login again.');
                setTimeout(() => redirectToLogin(), 2000);
            }
        } catch (error) {
            console.error('[Admin Common] Auth check error:', error);
            // Only redirect if we don't have stored user data
            if (!sessionStorage.getItem('user')) {
                redirectToLogin();
            } else {
                console.log('[Admin Common] Auth check failed but using cached user data');
            }
        }
    }

    /**
     * Redirect to login page
     */
    function redirectToLogin() {
        window.location.href = '/modules/auth/login.html';
    }

    /**
     * Setup CSRF token for fetch requests
     */
    function setupCSRFToken() {
        // CSRF token is already handled by apiFetch in utils.js
        // This function is kept for compatibility
    }

    /**
     * Setup common event listeners
     */
    function setupCommonListeners() {
        // Logout button
        const logoutBtns = document.querySelectorAll('.logout-btn, [data-action="logout"]');
        logoutBtns.forEach(btn => {
            btn.addEventListener('click', handleLogout);
        });

        // Refresh button
        const refreshBtns = document.querySelectorAll('.refresh-btn, [data-action="refresh"]');
        refreshBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                location.reload();
            });
        });

        // Back button
        const backBtns = document.querySelectorAll('.back-btn, [data-action="back"]');
        backBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                window.history.back();
            });
        });
    }

    /**
     * Handle logout
     */
    async function handleLogout(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to logout?')) {
            return;
        }

        try {
            showLoading('Logging out...');

            const data = await apiFetch('/api/auth/logout', {
                method: 'POST'
            });

            // Clear local storage
            localStorage.removeItem('user');
            localStorage.removeItem('session_token');
            localStorage.clear();

            showSuccess('Logged out successfully');

            setTimeout(() => {
                window.location.href = '/modules/auth/login.html';
            }, 1000);
        } catch (error) {
            console.error('Logout error:', error);
            // Still redirect even on error
            localStorage.clear();
            window.location.href = '/modules/auth/login.html';
        } finally {
            hideLoading();
        }
    }

    // Make functions globally available
    window.adminCommon = {
        checkAdminAuth,
        redirectToLogin,
        handleLogout
    };

})();

/**
 * Format number with commas
 */
function formatNumber(num) {
    return parseFloat(num || 0).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
}

/**
 * Get user info from localStorage
 */
function getUserInfo() {
    const userStr = localStorage.getItem('user');
    if (userStr) {
        try {
            return JSON.parse(userStr);
        } catch (e) {
            console.error('Error parsing user info:', e);
            return null;
        }
    }
    return null;
}

/**
 * Update user display in UI
 */
function updateUserDisplay() {
    const user = getUserInfo();
    if (!user) return;

    // Update username displays
    const usernameEls = document.querySelectorAll('.user-name, [data-user="name"]');
    usernameEls.forEach(el => {
        el.textContent = user.username || 'User';
    });

    // Update role displays
    const roleEls = document.querySelectorAll('.user-role, [data-user="role"]');
    roleEls.forEach(el => {
        const role = user.role || 'user';
        el.textContent = role.charAt(0).toUpperCase() + role.slice(1);
    });

    // Update avatar/initials
    const avatarEls = document.querySelectorAll('.user-avatar, [data-user="avatar"]');
    avatarEls.forEach(el => {
        const initials = (user.username || 'U').substring(0, 2).toUpperCase();
        el.textContent = initials;
    });
}

/**
 * Confirm action with custom message
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

/**
 * Modal helper functions
 */
const ModalHelper = {
    show(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    },

    hide(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    },

    reset(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.reset();
        }
    }
};

/**
 * Table helper functions
 */
const TableHelper = {
    clearTable(tableBodyId) {
        const tbody = document.getElementById(tableBodyId);
        if (tbody) {
            tbody.innerHTML = '';
        }
    },

    showNoData(tableBodyId, colspan, message = 'No data available') {
        const tbody = document.getElementById(tableBodyId);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">${message}</p>
                    </td>
                </tr>
            `;
        }
    },

    showLoading(tableBodyId, colspan) {
        const tbody = document.getElementById(tableBodyId);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading data...</p>
                    </td>
                </tr>
            `;
        }
    }
};

/**
 * Date range picker helper
 */
function setupDateRangePicker(startId, endId, onChangeCallback) {
    const startInput = document.getElementById(startId);
    const endInput = document.getElementById(endId);

    if (startInput && endInput) {
        // Set default dates
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

        startInput.value = formatDateForInput(firstDayOfMonth);
        endInput.value = formatDateForInput(today);

        // Add change listeners
        if (typeof onChangeCallback === 'function') {
            startInput.addEventListener('change', onChangeCallback);
            endInput.addEventListener('change', onChangeCallback);
        }
    }
}

/**
 * Export current page data
 */
function exportCurrentPage(data, filename, format = 'excel') {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }

    if (format === 'excel') {
        exportToExcel(data, filename + '.xlsx');
    } else if (format === 'pdf') {
        showInfo('PDF export functionality coming soon');
    }
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

/**
 * Initialize popovers
 */
function initPopovers() {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        updateUserDisplay();
        initTooltips();
        initPopovers();
    });
} else {
    updateUserDisplay();
    initTooltips();
    initPopovers();
}
