/**
 * CRUD Helper Functions
 * High-level wrapper functions for common CRUD operations
 * Makes it easier to perform database operations with one-liners
 */

// ==================== GENERIC CRUD HELPERS ====================

/**
 * Generic GET all items
 */
async function getAll(entity, params = {}) {
    try {
        showLoading(`Loading ${entity}...`);
        const response = await api.request(`/api/${entity}`, {
            method: 'GET',
            body: params ? '?' + new URLSearchParams(params) : ''
        });
        hideLoading();
        return response;
    } catch (error) {
        hideLoading();
        showError(`Failed to load ${entity}`);
        throw error;
    }
}

/**
 * Generic GET single item
 */
async function getOne(entity, id) {
    try {
        showLoading(`Loading ${entity}...`);
        const response = await api.request(`/api/${entity}/${id}`);
        hideLoading();
        return response;
    } catch (error) {
        hideLoading();
        showError(`Failed to load ${entity}`);
        throw error;
    }
}

/**
 * Generic CREATE item
 */
async function create(entity, data) {
    try {
        showLoading(`Creating ${entity}...`);
        const response = await api.request(`/api/${entity}`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        hideLoading();
        if (response.success) {
            showSuccess(`${entity} created successfully!`);
        }
        return response;
    } catch (error) {
        hideLoading();
        showError(`Failed to create ${entity}`);
        throw error;
    }
}

/**
 * Generic UPDATE item
 */
async function update(entity, id, data) {
    try {
        showLoading(`Updating ${entity}...`);
        const response = await api.request(`/api/${entity}/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
        hideLoading();
        if (response.success) {
            showSuccess(`${entity} updated successfully!`);
        }
        return response;
    } catch (error) {
        hideLoading();
        showError(`Failed to update ${entity}`);
        throw error;
    }
}

/**
 * Generic DELETE item
 */
async function remove(entity, id) {
    if (!confirm(`Are you sure you want to delete this ${entity}?`)) {
        return { success: false, cancelled: true };
    }

    try {
        showLoading(`Deleting ${entity}...`);
        const response = await api.request(`/api/${entity}/${id}`, {
            method: 'DELETE'
        });
        hideLoading();
        if (response.success) {
            showSuccess(`${entity} deleted successfully!`);
        }
        return response;
    } catch (error) {
        hideLoading();
        showError(`Failed to delete ${entity}`);
        throw error;
    }
}

// ==================== MEDICINE HELPERS ====================

const Medicine = {
    /**
     * Load all medicines
     */
    async loadAll(filters = {}) {
        return await api.getMedicines(filters);
    },

    /**
     * Load single medicine
     */
    async load(id) {
        return await api.getMedicine(id);
    },

    /**
     * Create new medicine
     */
    async create(data) {
        return await api.createMedicine(data);
    },

    /**
     * Update medicine
     */
    async update(id, data) {
        return await api.updateMedicine(id, data);
    },

    /**
     * Delete medicine
     */
    async delete(id) {
        if (!confirm('Are you sure you want to delete this medicine?')) {
            return { success: false, cancelled: true };
        }
        return await api.deleteMedicine(id);
    },

    /**
     * Search medicines
     */
    async search(query) {
        return await api.searchMedicines(query);
    },

    /**
     * Get low stock items
     */
    async getLowStock() {
        return await api.getLowStock();
    },

    /**
     * Get expired medicines
     */
    async getExpired() {
        return await api.getExpiredMedicines();
    },

    /**
     * Import medicines from Excel
     */
    async bulkImport(medicines) {
        return await api.bulkImportMedicines(medicines);
    },

    /**
     * Update stock quantity
     */
    async updateStock(id, quantity, operation = 'add') {
        return await api.updateStock(id, quantity, operation);
    }
};

// ==================== CUSTOMER HELPERS ====================

const Customer = {
    /**
     * Load all customers
     */
    async loadAll(filters = {}) {
        return await api.getCustomers(filters);
    },

    /**
     * Load single customer
     */
    async load(id) {
        return await api.getCustomer(id);
    },

    /**
     * Create new customer
     */
    async create(data) {
        return await api.createCustomer(data);
    },

    /**
     * Update customer
     */
    async update(id, data) {
        return await api.updateCustomer(id, data);
    },

    /**
     * Delete customer
     */
    async delete(id) {
        if (!confirm('Are you sure you want to delete this customer?')) {
            return { success: false, cancelled: true };
        }
        return await api.deleteCustomer(id);
    },

    /**
     * Search customers
     */
    async search(query) {
        return await api.searchCustomers(query);
    },

    /**
     * Get customer purchase history
     */
    async getHistory(id) {
        return await api.getCustomerHistory(id);
    }
};

// ==================== ORDER HELPERS ====================

const Order = {
    /**
     * Load all orders
     */
    async loadAll(filters = {}) {
        return await api.getOrders(filters);
    },

    /**
     * Load single order
     */
    async load(id) {
        return await api.getOrder(id);
    },

    /**
     * Create new order
     */
    async create(data) {
        return await api.createOrder(data);
    },

    /**
     * Update order
     */
    async update(id, data) {
        return await api.updateOrder(id, data);
    },

    /**
     * Delete order
     */
    async delete(id) {
        if (!confirm('Are you sure you want to delete this order?')) {
            return { success: false, cancelled: true };
        }
        return await api.deleteOrder(id);
    },

    /**
     * Update order status
     */
    async updateStatus(id, status) {
        return await api.updateOrderStatus(id, status);
    },

    /**
     * Track order by number
     */
    async track(orderNumber) {
        return await api.trackOrder(orderNumber);
    },

    /**
     * Get pending orders
     */
    async getPending() {
        return await api.getPendingOrders();
    },

    /**
     * Cancel order
     */
    async cancel(id, reason = '') {
        if (!confirm('Are you sure you want to cancel this order?')) {
            return { success: false, cancelled: true };
        }
        return await api.cancelOrder(id, reason);
    }
};

// ==================== BILLING HELPERS ====================

const Billing = {
    /**
     * Create new bill
     */
    async create(billData) {
        return await api.createBill(billData);
    },

    /**
     * Load all bills
     */
    async loadAll(filters = {}) {
        return await api.getBills(filters);
    },

    /**
     * Load single bill
     */
    async load(id) {
        return await api.getBill(id);
    },

    /**
     * Get today's bills
     */
    async getToday() {
        return await api.getTodayBills();
    },

    /**
     * Get bill by number
     */
    async getByNumber(billNumber) {
        return await api.getBillByNumber(billNumber);
    }
};

// ==================== DASHBOARD HELPERS ====================

const Dashboard = {
    /**
     * Load dashboard stats
     */
    async loadStats() {
        return await api.getDashboardStats();
    },

    /**
     * Get sales summary
     */
    async getSales(period = 'today') {
        return await api.getSalesSummary(period);
    },

    /**
     * Get revenue chart data
     */
    async getRevenue(days = 7) {
        return await api.getRevenueChart(days);
    },

    /**
     * Get top selling medicines
     */
    async getTopSelling(limit = 10) {
        return await api.getTopSellingMedicines(limit);
    },

    /**
     * Get recent activities
     */
    async getActivities(limit = 10) {
        return await api.getRecentActivities(limit);
    }
};

// ==================== REPORT HELPERS ====================

const Report = {
    /**
     * Get sales report
     */
    async sales(startDate, endDate) {
        return await api.getSalesReport(startDate, endDate);
    },

    /**
     * Get stock report
     */
    async stock() {
        return await api.getStockReport();
    },

    /**
     * Get customer report
     */
    async customers(startDate, endDate) {
        return await api.getCustomerReport(startDate, endDate);
    },

    /**
     * Get financial report
     */
    async financial(month, year) {
        return await api.getFinancialReport(month, year);
    },

    /**
     * Get medicine wise sales
     */
    async medicineSales(startDate, endDate) {
        return await api.getMedicineWiseSales(startDate, endDate);
    },

    /**
     * Get profit analysis
     */
    async profit(startDate, endDate) {
        return await api.getProfitAnalysis(startDate, endDate);
    }
};

// ==================== CART HELPERS (Frontend) ====================

const Cart = {
    /**
     * Get cart items
     */
    async get() {
        return await api.getCart();
    },

    /**
     * Add item to cart
     */
    async add(medicineId, quantity = 1) {
        const response = await api.addToCart(medicineId, quantity);
        if (response.success) {
            showSuccess('Item added to cart');
        }
        return response;
    },

    /**
     * Update cart item
     */
    async update(itemId, quantity) {
        return await api.updateCartItem(itemId, quantity);
    },

    /**
     * Remove item from cart
     */
    async remove(itemId) {
        const response = await api.removeFromCart(itemId);
        if (response.success) {
            showSuccess('Item removed from cart');
        }
        return response;
    },

    /**
     * Clear cart
     */
    async clear() {
        if (!confirm('Are you sure you want to clear the cart?')) {
            return { success: false, cancelled: true };
        }
        return await api.clearCart();
    },

    /**
     * Checkout
     */
    async checkout(checkoutData) {
        return await api.checkout(checkoutData);
    }
};

// ==================== FORM HELPERS ====================

/**
 * Submit form data to API
 */
async function submitForm(formId, apiMethod) {
    const form = document.getElementById(formId);
    if (!form) {
        showError('Form not found');
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await apiMethod(data);
        if (response.success) {
            form.reset();
        }
        return response;
    } catch (error) {
        console.error('Form submission error:', error);
        throw error;
    }
}

/**
 * Load data into form
 */
function loadFormData(formId, data) {
    const form = document.getElementById(formId);
    if (!form) return;

    Object.keys(data).forEach(key => {
        const field = form.elements[key];
        if (field) {
            if (field.type === 'checkbox') {
                field.checked = data[key];
            } else if (field.type === 'radio') {
                const radio = form.querySelector(`input[name="${key}"][value="${data[key]}"]`);
                if (radio) radio.checked = true;
            } else {
                field.value = data[key] || '';
            }
        }
    });
}

/**
 * Clear form
 */
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
    }
}

// ==================== TABLE HELPERS ====================

/**
 * Populate table with data
 */
function populateTable(tableBodyId, data, rowRenderer) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;

    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="100" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No data available</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = data.map(rowRenderer).join('');
}

/**
 * Add table row
 */
function addTableRow(tableBodyId, rowHtml, position = 'end') {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;

    if (position === 'start') {
        tbody.insertAdjacentHTML('afterbegin', rowHtml);
    } else {
        tbody.insertAdjacentHTML('beforeend', rowHtml);
    }
}

/**
 * Remove table row
 */
function removeTableRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
    }
}

/**
 * Update table row
 */
function updateTableRow(rowId, newRowHtml) {
    const row = document.getElementById(rowId);
    if (row) {
        row.outerHTML = newRowHtml;
    }
}

// ==================== PAGINATION HELPERS ====================

/**
 * Create pagination controls
 */
function createPagination(containerId, currentPage, totalPages, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let html = '<nav><ul class="pagination">';

    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
        </li>
    `;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
        </li>
    `;

    html += '</ul></nav>';
    container.innerHTML = html;

    // Add click handlers
    container.querySelectorAll('a.page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            if (page && page !== currentPage) {
                onPageChange(page);
            }
        });
    });
}

// Make helpers globally available
if (typeof window !== 'undefined') {
    window.Medicine = Medicine;
    window.Customer = Customer;
    window.Order = Order;
    window.Billing = Billing;
    window.Dashboard = Dashboard;
    window.Report = Report;
    window.Cart = Cart;
    window.submitForm = submitForm;
    window.loadFormData = loadFormData;
    window.clearForm = clearForm;
    window.populateTable = populateTable;
    window.addTableRow = addTableRow;
    window.removeTableRow = removeTableRow;
    window.updateTableRow = updateTableRow;
    window.createPagination = createPagination;
}
