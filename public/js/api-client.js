/**
 * API Client for Laravel Backend
 * Centralized CRUD operations for all entities
 * Updated: November 2024
 */

class APIClient {
    constructor() {
        this.baseURL = this.getBaseURL();
        this.csrfToken = this.getCSRFToken();
        console.log('APIClient initialized with baseURL:', this.baseURL);
    }

    /**
     * Get base URL for API requests
     */
    getBaseURL() {
        const loc = window.location;
        // If running on Laravel dev server (port 8000), use origin directly
        if (loc.port === '8000') {
            return loc.origin;
        }
        // If in XAMPP, add /waqar/public path
        if (loc.pathname.includes('/waqar/')) {
            return loc.origin + '/waqar/public';
        }
        // Default: just use origin
        return loc.origin;
    }

    /**
     * Get CSRF token from meta tag
     */
    getCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) {
            console.warn('CSRF token not found in meta tag');
        }
        return token || '';
    }

    /**
     * Generic fetch wrapper with error handling
     */
    async request(url, options = {}) {
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        };

        const config = {
            ...options,
            credentials: 'include', // CRITICAL: Send cookies with requests
            headers: {
                ...defaultHeaders,
                ...(options.headers || {})
            }
        };

        try {
            // Add request timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
            config.signal = controller.signal;

            const response = await fetch(this.baseURL + url, config);
            clearTimeout(timeoutId);

            // Handle non-JSON responses
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return { success: true, data: await response.text() };
            }

            const data = await response.json();

            // Handle HTTP errors
            if (!response.ok) {
                const error = new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                // Attach validation errors if present
                if (data.errors) {
                    error.errors = data.errors;
                }
                throw error;
            }

            return data;
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    // ==================== AUTHENTICATION ====================

    /**
     * Login user
     */
    async login(credentials) {
        // Map email to username for Laravel backend
        const payload = {
            username: credentials.email || credentials.username,
            password: credentials.password,
            rememberMe: credentials.rememberMe
        };
        return await this.request('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    }

    /**
     * Logout user
     */
    async logout() {
        return await this.request('/api/auth/logout', {
            method: 'POST'
        });
    }

    /**
     * Get current user
     */
    async getUser() {
        return await this.request('/api/auth/user');
    }

    /**
     * Check authentication status
     */
    async checkAuth() {
        return await this.request('/api/auth/check');
    }

    /**
     * Register new user
     */
    async register(userData) {
        // Transform form data to match Laravel backend expectations
        const payload = {
            username: userData.email || (userData.firstName + userData.lastName).toLowerCase().replace(/\s+/g, ''),
            email: userData.email,
            password: userData.password,
            role: userData.role === 'admin' ? 'Admin' : 'Cashier', // Capitalize role
            phone: userData.phone,
            firstName: userData.firstName,
            lastName: userData.lastName
        };
        return await this.request('/api/auth/register', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    }

    /**
     * Unlock account
     */
    async unlockAccount(data) {
        return await this.request('/api/auth/unlock-account', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    // ==================== MEDICINES ====================

    /**
     * Get all medicines with optional filters
     */
    async getMedicines(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/medicines' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single medicine by ID
     */
    async getMedicine(id) {
        return await this.request(`/api/medicines/${id}`);
    }

    /**
     * Create new medicine
     */
    async createMedicine(medicineData) {
        return await this.request('/api/medicines', {
            method: 'POST',
            body: JSON.stringify(medicineData)
        });
    }

    /**
     * Update medicine
     */
    async updateMedicine(id, medicineData) {
        return await this.request(`/api/medicines/${id}`, {
            method: 'PUT',
            body: JSON.stringify(medicineData)
        });
    }

    /**
     * Delete medicine
     */
    async deleteMedicine(id) {
        return await this.request(`/api/medicines/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Delete all medicines
     */
    async deleteAllMedicines() {
        return await this.request('/api/medicines/delete-all', {
            method: 'DELETE'
        });
    }

    /**
     * Search medicines
     */
    async searchMedicines(query) {
        return await this.request(`/api/medicines?search=${encodeURIComponent(query)}`);
    }

    /**
     * Get stock status
     */
    async getStockStatus() {
        return await this.request('/api/stock');
    }

    /**
     * Get low stock medicines
     */
    async getLowStock() {
        return await this.request('/api/medicines/low-stock/list');
    }

    /**
     * Get expired medicines
     */
    async getExpiredMedicines() {
        return await this.request('/api/medicines/expiring/list?status=expired');
    }

    /**
     * Get expiring soon medicines
     */
    async getExpiringSoon(days = 30) {
        return await this.request(`/api/medicines/expiring/list?days=${days}`);
    }

    /**
     * Bulk import medicines (batch processing)
     * @param {Array} medicines - Array of medicine objects
     * @param {boolean} skipDuplicateCheck - If true, skips checking for existing medicines (faster)
     */
    async bulkImportMedicines(medicines, skipDuplicateCheck = false) {
        return await this.request('/api/medicines/import', {
            method: 'POST',
            body: JSON.stringify({ medicines, skip_duplicate_check: skipDuplicateCheck })
        });
    }

    /**
     * Update medicine stock
     */
    async updateStock(id, quantity, operation = 'add') {
        return await this.request(`/api/stock/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ quantity, operation })
        });
    }

    // ==================== CUSTOMERS ====================

    /**
     * Get all customers
     */
    async getCustomers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/customers' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single customer
     */
    async getCustomer(id) {
        return await this.request(`/api/customers/${id}`);
    }

    /**
     * Create new customer
     */
    async createCustomer(customerData) {
        return await this.request('/api/customers', {
            method: 'POST',
            body: JSON.stringify(customerData)
        });
    }

    /**
     * Update customer
     */
    async updateCustomer(id, customerData) {
        return await this.request(`/api/customers/${id}`, {
            method: 'PUT',
            body: JSON.stringify(customerData)
        });
    }

    /**
     * Delete customer
     */
    async deleteCustomer(id) {
        return await this.request(`/api/customers/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Search customers
     */
    async searchCustomers(query) {
        return await this.request(`/api/customers?search=${encodeURIComponent(query)}`);
    }

    /**
     * Get customer purchase history
     */
    async getCustomerHistory(id) {
        return await this.request(`/api/customers/${id}/history`);
    }

    // ==================== ORDERS ====================

    /**
     * Get all orders
     */
    async getOrders(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/orders' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single order
     */
    async getOrder(id) {
        return await this.request(`/api/orders/${id}`);
    }

    /**
     * Create new order
     */
    async createOrder(orderData) {
        return await this.request('/api/orders', {
            method: 'POST',
            body: JSON.stringify(orderData)
        });
    }

    /**
     * Update order
     */
    async updateOrder(id, orderData) {
        return await this.request(`/api/orders/${id}`, {
            method: 'PUT',
            body: JSON.stringify(orderData)
        });
    }

    /**
     * Delete order
     */
    async deleteOrder(id) {
        return await this.request(`/api/orders/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Update order status
     */
    async updateOrderStatus(id, status) {
        return await this.request(`/api/orders/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    /**
     * Track order
     */
    async trackOrder(orderNumber) {
        return await this.request(`/api/orders/track/${orderNumber}`);
    }

    /**
     * Get pending orders
     */
    async getPendingOrders() {
        return await this.request('/api/orders?status=pending');
    }

    /**
     * Cancel order
     */
    async cancelOrder(id, reason = '') {
        return await this.request(`/api/orders/${id}/cancel`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
    }

    // ==================== BILLING ====================

    /**
     * Create new bill
     */
    async createBill(billData) {
        return await this.request('/api/bills', {
            method: 'POST',
            body: JSON.stringify(billData)
        });
    }

    /**
     * Get all bills
     */
    async getBills(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/bills' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single bill
     */
    async getBill(id) {
        return await this.request(`/api/bills/${id}`);
    }

    /**
     * Get today's bills
     */
    async getTodayBills() {
        return await this.request('/api/bills?filter=today');
    }

    /**
     * Get bill by number
     */
    async getBillByNumber(billNumber) {
        return await this.request(`/api/bills/search/${billNumber}`);
    }

    // ==================== DASHBOARD ====================

    /**
     * Get dashboard statistics
     */
    async getDashboardStats() {
        return await this.request('/api/dashboard/stats');
    }

    /**
     * Get sales summary (alias for sales chart)
     */
    async getSalesSummary(days = 7) {
        return await this.request(`/api/dashboard/sales-chart?days=${days}`);
    }

    /**
     * Get revenue chart data (sales chart)
     */
    async getRevenueChart(days = 7) {
        return await this.request(`/api/dashboard/sales-chart?days=${days}`);
    }

    /**
     * Get top selling medicines
     */
    async getTopSellingMedicines(limit = 10) {
        return await this.request(`/api/dashboard/top-medicines?limit=${limit}`);
    }

    /**
     * Get top medicines (alternative method name)
     */
    async getTopMedicines(limit = 5) {
        return await this.request(`/api/dashboard/top-medicines?limit=${limit}`);
    }

    /**
     * Get recent activities (recent sales)
     */
    async getRecentActivities(limit = 10) {
        return await this.request(`/api/dashboard/recent-sales?limit=${limit}`);
    }

    /**
     * Get recent sales
     */
    async getRecentSales(limit = 10) {
        return await this.request(`/api/dashboard/recent-sales?limit=${limit}`);
    }

    /**
     * Get low stock medicines
     */
    async getLowStockDashboard(threshold = 20) {
        return await this.request(`/api/dashboard/low-stock?threshold=${threshold}`);
    }

    // ==================== REPORTS ====================

    /**
     * Get sales report
     */
    async getSalesReport(startDate, endDate) {
        return await this.request(`/api/reports/sales?start=${startDate}&end=${endDate}`);
    }

    /**
     * Get stock report
     */
    async getStockReport() {
        return await this.request('/api/reports/stock');
    }

    /**
     * Get customer report
     */
    async getCustomerReport(startDate, endDate) {
        return await this.request(`/api/reports/customers?start=${startDate}&end=${endDate}`);
    }

    /**
     * Get financial report
     */
    async getFinancialReport(month, year) {
        return await this.request(`/api/reports/financial?month=${month}&year=${year}`);
    }

    /**
     * Get medicine wise sales
     */
    async getMedicineWiseSales(startDate, endDate) {
        return await this.request(`/api/reports/sales/medicine?start=${startDate}&end=${endDate}`);
    }

    /**
     * Get profit analysis
     */
    async getProfitAnalysis(startDate, endDate) {
        return await this.request(`/api/reports/profit?start=${startDate}&end=${endDate}`);
    }

    // ==================== RECORDS ====================

    /**
     * Get all records
     */
    async getRecords(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/records' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single record
     */
    async getRecord(id) {
        return await this.request(`/api/records/${id}`);
    }

    /**
     * Create new record
     */
    async createRecord(recordData) {
        return await this.request('/api/records', {
            method: 'POST',
            body: JSON.stringify(recordData)
        });
    }

    /**
     * Update record
     */
    async updateRecord(id, recordData) {
        return await this.request(`/api/records/${id}`, {
            method: 'PUT',
            body: JSON.stringify(recordData)
        });
    }

    /**
     * Delete record
     */
    async deleteRecord(id) {
        return await this.request(`/api/records/${id}`, {
            method: 'DELETE'
        });
    }

    // ==================== SETTINGS ====================

    /**
     * Get all settings
     */
    async getSettings() {
        return await this.request('/api/settings');
    }

    /**
     * Update settings
     */
    async updateSettings(settings) {
        return await this.request('/api/settings', {
            method: 'PUT',
            body: JSON.stringify(settings)
        });
    }

    /**
     * Get specific setting
     */
    async getSetting(key) {
        return await this.request(`/api/settings?key=${key}`);
    }

    /**
     * Update specific setting
     */
    async updateSetting(key, value) {
        return await this.request('/api/settings/bulk-update', {
            method: 'POST',
            body: JSON.stringify({ [key]: value })
        });
    }

    // ==================== CART (Frontend) ====================

    /**
     * Get cart items
     */
    async getCart() {
        return await this.request('/api/cart');
    }

    /**
     * Add item to cart
     */
    async addToCart(medicineId, quantity = 1) {
        return await this.request('/api/cart/add', {
            method: 'POST',
            body: JSON.stringify({ medicine_id: medicineId, quantity })
        });
    }

    /**
     * Update cart item quantity
     */
    async updateCartItem(itemId, quantity) {
        return await this.request(`/api/cart/${itemId}`, {
            method: 'PUT',
            body: JSON.stringify({ quantity })
        });
    }

    /**
     * Remove item from cart
     */
    async removeFromCart(itemId) {
        return await this.request(`/api/cart/${itemId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Clear cart
     */
    async clearCart() {
        return await this.request('/api/cart/clear', {
            method: 'POST'
        });
    }

    /**
     * Checkout cart
     */
    async checkout(checkoutData) {
        return await this.request('/api/cart/checkout', {
            method: 'POST',
            body: JSON.stringify(checkoutData)
        });
    }

    // ==================== REVIEWS ====================

    /**
     * Get medicine reviews
     */
    async getMedicineReviews(medicineId) {
        return await this.request(`/api/medicines/${medicineId}/reviews`);
    }

    /**
     * Add review
     */
    async addReview(medicineId, reviewData) {
        return await this.request(`/api/medicines/${medicineId}/reviews`, {
            method: 'POST',
            body: JSON.stringify(reviewData)
        });
    }

    /**
     * Update review
     */
    async updateReview(reviewId, reviewData) {
        return await this.request(`/api/reviews/${reviewId}`, {
            method: 'PUT',
            body: JSON.stringify(reviewData)
        });
    }

    /**
     * Delete review
     */
    async deleteReview(reviewId) {
        return await this.request(`/api/reviews/${reviewId}`, {
            method: 'DELETE'
        });
    }

    // ==================== USERS ====================

    /**
     * Get all users (Admin only)
     */
    async getUsers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = '/api/users' + (queryString ? '?' + queryString : '');
        return await this.request(url);
    }

    /**
     * Get single user
     */
    async getUserById(id) {
        return await this.request(`/api/users/${id}`);
    }

    /**
     * Create new user
     */
    async createUser(userData) {
        return await this.request('/api/users', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    /**
     * Update user
     */
    async updateUser(id, userData) {
        return await this.request(`/api/users/${id}`, {
            method: 'PUT',
            body: JSON.stringify(userData)
        });
    }

    /**
     * Delete user
     */
    async deleteUser(id) {
        return await this.request(`/api/users/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Change password
     */
    async changePassword(oldPassword, newPassword) {
        return await this.request('/api/users/change-password', {
            method: 'POST',
            body: JSON.stringify({ old_password: oldPassword, new_password: newPassword })
        });
    }

    // ==================== FILE UPLOAD ====================

    /**
     * Upload file
     */
    async uploadFile(file, type = 'general') {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);

        return await this.request('/api/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            body: formData
        });
    }

    /**
     * Upload medicine image
     */
    async uploadMedicineImage(medicineId, file) {
        const formData = new FormData();
        formData.append('image', file);

        return await this.request(`/api/medicines/${medicineId}/image`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            body: formData
        });
    }

    // ==================== BACKUP ====================

    /**
     * Create database backup
     */
    async createBackup() {
        return await this.request('/api/backup/create', {
            method: 'POST'
        });
    }

    /**
     * Get backup list
     */
    async getBackups() {
        return await this.request('/api/backup/list');
    }

    /**
     * Restore backup
     */
    async restoreBackup(filename) {
        return await this.request('/api/backup/restore', {
            method: 'POST',
            body: JSON.stringify({ filename })
        });
    }

    /**
     * Download backup
     */
    async downloadBackup(filename) {
        return await this.request(`/api/backup/download/${filename}`);
    }
}

// Create global instance
const api = new APIClient();

// Make it available globally
if (typeof window !== 'undefined') {
    window.api = api;
    window.APIClient = APIClient;
}

// Export for ES6 modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { api, APIClient };
}
