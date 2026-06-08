/**
 * Stock Management JavaScript - Complete Laravel Integration
 * Handles stock inventory, filtering, CRUD operations, and analytics
 * @version 2.0.0 - Updated 2025-12-03
 */

// Helper function to get base URL - must be defined before Stock object
function getBaseURL() {
    // Detect if running on Laravel dev server (127.0.0.1:8000) or XAMPP (localhost)
    const origin = window.location.origin;

    // If on Laravel dev server, no path prefix needed
    if (origin.includes('127.0.0.1:8000') || origin.includes('localhost:8000')) {
        return origin;
    }

    // If on XAMPP, use /waqar/public prefix
    return origin + '/waqar/public';
}

const Stock = {
    version: '2.0.0',
    stockData: [],
    filteredData: [],
    currentFilter: 'all',
    categoryFilter: '',
    searchTerm: '',
    sortBy: 'name',
    sortOrder: 'ASC',

    init() {
        console.log('═══════════════════════════════════════');
        console.log('Stock Management System v' + this.version);
        console.log('Page: modules/stock/index.html');
        console.log('Base URL:', getBaseURL());
        console.log('Initialized:', new Date().toLocaleString());
        console.log('═══════════════════════════════════════');
        this.loadStockData();
        this.setupEventListeners();
    },

    setupEventListeners() {
        // Search input with autocomplete suggestions
        const searchInput = document.getElementById('stock-search');
        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                this.searchTerm = e.target.value;
                if (this.searchTerm.length >= 2) {
                    this.showSearchSuggestions(this.searchTerm);
                } else {
                    this.hideSearchSuggestions();
                }
                this.filterStock();
            }, 300));

            // Hide suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target)) {
                    this.hideSearchSuggestions();
                }
            });
        }

        // Filter dropdown
        const stockFilter = document.getElementById('stock-filter');
        if (stockFilter) {
            stockFilter.addEventListener('change', (e) => {
                this.currentFilter = e.target.value;
                this.filterStock();
            });
        }

        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.categoryFilter = e.target.value;
                this.filterStock();
            });
        }

        // Export buttons
        const exportExcelBtn = document.getElementById('export-excel');
        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', () => this.exportToExcel());
        }

        const exportPdfBtn = document.getElementById('export-pdf');
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', () => this.exportToPDF());
        }

        const printBtn = document.getElementById('print-stock');
        if (printBtn) {
            printBtn.addEventListener('click', () => this.printStock());
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-stock-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadStockData());
        }

        // Sort functionality - add click handlers to table headers
        // Headers don't have data-sort attributes, so we'll skip this for now
        // Can be added later if needed
    },

    async loadStockData() {
        try {
            showLoading('Loading stock data...');

            const apiUrl = getBaseURL() + '/api/medicines/stock';
            console.log('Fetching from:', apiUrl);

            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            console.log('Response status:', response.status);
            console.log('Response OK:', response.ok);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Stock data response:', data);

            if (data.success && data.data) {
                this.stockData = data.data;
                console.log('✓ Stock data loaded:', this.stockData.length, 'items');
                this.filterStock();
                this.updateStockStats();
                showSuccess(`Loaded ${this.stockData.length} stock items`);
            } else {
                console.error('API returned success=false:', data);
                showError('Failed to load stock data: ' + (data.message || 'Unknown error'));
                this.stockData = [];
                this.displayStock();
            }
        } catch (error) {
            console.error('❌ Error loading stock data:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            showError('Failed to load stock data: ' + error.message);
            this.stockData = [];
            this.displayStock();
        } finally {
            hideLoading();
        }
    },

    async showSearchSuggestions(searchTerm) {
        // Filter current data for quick suggestions
        const suggestions = this.stockData
            .filter(item =>
                item.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.category?.toLowerCase().includes(searchTerm.toLowerCase())
            )
            .slice(0, 5);

        if (suggestions.length === 0) return;

        // Create or update suggestions dropdown
        let suggestionsDiv = document.getElementById('stock-search-suggestions');
        if (!suggestionsDiv) {
            suggestionsDiv = document.createElement('div');
            suggestionsDiv.id = 'stock-search-suggestions';
            suggestionsDiv.className = 'position-absolute bg-white border rounded shadow-sm w-100 mt-1';
            suggestionsDiv.style.zIndex = '1000';
            suggestionsDiv.style.maxHeight = '300px';
            suggestionsDiv.style.overflowY = 'auto';
            document.getElementById('stock-search').parentElement.style.position = 'relative';
            document.getElementById('stock-search').parentElement.appendChild(suggestionsDiv);
        }

        suggestionsDiv.innerHTML = suggestions.map(item => `
            <div class="p-2 border-bottom suggestion-item" style="cursor: pointer;"
                 onmouseover="this.style.backgroundColor='#f8f9fa'"
                 onmouseout="this.style.backgroundColor='white'"
                 onclick="Stock.selectSuggestion('${item.name.replace(/'/g, "\\'")}')">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${item.name}</strong>
                        <small class="text-muted d-block">${item.category || 'No category'}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge ${item.quantity > 20 ? 'bg-success' : item.quantity > 0 ? 'bg-warning' : 'bg-danger'}">
                            ${item.quantity} in stock
                        </span>
                    </div>
                </div>
            </div>
        `).join('');

        suggestionsDiv.style.display = 'block';
    },

    hideSearchSuggestions() {
        const suggestionsDiv = document.getElementById('stock-search-suggestions');
        if (suggestionsDiv) {
            suggestionsDiv.style.display = 'none';
        }
    },

    selectSuggestion(name) {
        document.getElementById('stock-search').value = name;
        this.searchTerm = name;
        this.hideSearchSuggestions();
        this.filterStock();
    },

    filterStock() {
        let filtered = [...this.stockData];

        // Apply status filter
        if (this.currentFilter !== 'all') {
            filtered = filtered.filter(item => {
                switch (this.currentFilter) {
                    case 'low-stock':
                        return item.quantity < 10 && item.quantity > 0;
                    case 'out-of-stock':
                        return item.quantity <= 0;
                    case 'in-stock':
                        return item.quantity > 0;
                    case 'expired':
                        return item.expiry_date && new Date(item.expiry_date) < new Date();
                    case 'near-expiry':
                        if (!item.expiry_date) return false;
                        const thirtyDaysFromNow = new Date();
                        thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);
                        return new Date(item.expiry_date) <= thirtyDaysFromNow &&
                               new Date(item.expiry_date) >= new Date();
                    default:
                        return true;
                }
            });
        }

        // Apply category filter
        if (this.categoryFilter) {
            filtered = filtered.filter(item =>
                item.category === this.categoryFilter
            );
        }

        // Apply search filter
        if (this.searchTerm) {
            const search = this.searchTerm.toLowerCase();
            filtered = filtered.filter(item =>
                item.name?.toLowerCase().includes(search) ||
                item.category?.toLowerCase().includes(search) ||
                item.manufacturer?.toLowerCase().includes(search) ||
                item.batch_number?.toLowerCase().includes(search)
            );
        }

        this.filteredData = filtered;
        console.log('Filtered data:', this.filteredData.length, 'items');
        this.sortStock();
    },

    sortStock() {
        this.filteredData.sort((a, b) => {
            let aVal = a[this.sortBy];
            let bVal = b[this.sortBy];

            // Handle null/undefined values
            if (aVal == null) aVal = '';
            if (bVal == null) bVal = '';

            // Numeric sorting
            if (this.sortBy === 'quantity' || this.sortBy === 'price') {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
            }

            // Date sorting
            if (this.sortBy === 'expiry_date') {
                aVal = aVal ? new Date(aVal) : new Date(0);
                bVal = bVal ? new Date(bVal) : new Date(0);
            }

            let comparison = 0;
            if (aVal > bVal) comparison = 1;
            if (aVal < bVal) comparison = -1;

            return this.sortOrder === 'ASC' ? comparison : -comparison;
        });

        this.displayStock();
        this.updateStockCount();
    },

    displayStock() {
        const tbody = document.getElementById('stock-table-body');
        console.log('displayStock called with', this.filteredData.length, 'items');
        if (!tbody) {
            console.error('Table body #stock-table-body not found!');
            return;
        }

        if (this.filteredData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted mb-0">No stock items found</p>
                        ${this.searchTerm ? '<small class="text-muted">Try adjusting your search or filters</small>' : ''}
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.filteredData.map(item => {
            const stockStatus = this.getStockStatus(item);
            const expiryStatus = this.getExpiryStatus(item);
            const daysToExpiry = this.calculateDaysToExpiry(item.expiry_date);
            const minStock = 20; // Default minimum stock level

            return `
                <tr>
                    <td><strong>#${item.id}</strong></td>
                    <td>
                        <strong>${sanitizeHTML(item.name)}</strong>
                        ${item.description ? `<br><small class="text-muted">${sanitizeHTML(item.description).substring(0, 50)}...</small>` : ''}
                    </td>
                    <td><span class="badge bg-secondary">${sanitizeHTML(item.category || '-')}</span></td>
                    <td>
                        <span class="badge ${stockStatus.class}">
                            ${item.quantity} units
                        </span>
                    </td>
                    <td><span class="text-muted">${minStock} units</span></td>
                    <td class="text-center">
                        <span class="badge ${stockStatus.class}">
                            ${stockStatus.text}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${expiryStatus.class}">
                            ${item.expiry_date ? formatDate(item.expiry_date) : 'N/A'}
                        </span>
                    </td>
                    <td>
                        ${daysToExpiry !== null ? `
                            <span class="badge ${daysToExpiry < 30 ? 'bg-danger' : daysToExpiry < 90 ? 'bg-warning' : 'bg-success'}">
                                ${daysToExpiry} days
                            </span>
                        ` : '<span class="text-muted">N/A</span>'}
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-info" onclick="Stock.viewDetails(${item.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-primary" onclick="Stock.updateQuantity(${item.id})" title="Update Quantity">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="Stock.deleteStock(${item.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getStockStatus(item) {
        if (item.quantity === 0) {
            return { class: 'bg-danger text-white', text: 'Out of Stock' };
        } else if (item.quantity < 20) {
            return { class: 'bg-warning text-dark', text: 'Low Stock' };
        } else {
            return { class: 'bg-success text-white', text: 'In Stock' };
        }
    },

    getExpiryStatus(item) {
        if (!item.expiry_date) {
            return { class: 'bg-secondary text-white', text: 'No Date' };
        }

        const expiryDate = new Date(item.expiry_date);
        const today = new Date();
        const thirtyDaysFromNow = new Date();
        thirtyDaysFromNow.setDate(today.getDate() + 30);

        if (expiryDate < today) {
            return { class: 'bg-danger text-white', text: 'Expired' };
        } else if (expiryDate <= thirtyDaysFromNow) {
            return { class: 'bg-warning text-dark', text: 'Expiring Soon' };
        } else {
            return { class: 'bg-success text-white', text: 'Valid' };
        }
    },

    calculateDaysToExpiry(expiryDate) {
        if (!expiryDate) return null;

        const expiry = new Date(expiryDate);
        const today = new Date();
        const diffTime = expiry - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        return diffDays;
    },

    updateStockStats() {
        // Calculate stats
        const totalInStock = this.stockData.filter(item => item.quantity > 0).length;
        const lowStock = this.stockData.filter(item => item.quantity < 10 && item.quantity > 0).length;
        const outOfStock = this.stockData.filter(item => item.quantity <= 0).length;
        const expired = this.stockData.filter(item =>
            item.expiry_date && new Date(item.expiry_date) < new Date()
        ).length;

        // Update stat cards
        const totalInStockEl = document.getElementById('total-in-stock');
        if (totalInStockEl) totalInStockEl.textContent = totalInStock;

        const lowStockEl = document.getElementById('low-stock-count');
        if (lowStockEl) lowStockEl.textContent = lowStock;

        const outOfStockEl = document.getElementById('out-of-stock-count');
        if (outOfStockEl) outOfStockEl.textContent = outOfStock;

        const expiredEl = document.getElementById('expired-count');
        if (expiredEl) expiredEl.textContent = expired;
    },    updateStockCount() {
        const badge = document.getElementById('stock-count-badge');
        if (badge) {
            badge.textContent = `Total: ${this.filteredData.length} items`;
        }
    },

    async viewDetails(id) {
        const item = this.stockData.find(i => i.id === id);
        if (!item) {
            showError('Medicine not found');
            return;
        }

        const stockStatus = this.getStockStatus(item);
        const expiryStatus = this.getExpiryStatus(item);

        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="stockDetailModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-pills me-2"></i>${sanitizeHTML(item.name)}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="text-primary"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">ID:</th>
                                            <td>${item.id}</td>
                                        </tr>
                                        <tr>
                                            <th>Name:</th>
                                            <td><strong>${sanitizeHTML(item.name)}</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Category:</th>
                                            <td>${sanitizeHTML(item.category || 'N/A')}</td>
                                        </tr>
                                        <tr>
                                            <th>Description:</th>
                                            <td>${sanitizeHTML(item.description || 'N/A')}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary"><i class="fas fa-industry me-2"></i>Manufacturer Details</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">Manufacturer:</th>
                                            <td>${sanitizeHTML(item.manufacturer || 'N/A')}</td>
                                        </tr>
                                        <tr>
                                            <th>Batch Number:</th>
                                            <td>${sanitizeHTML(item.batch_number || 'N/A')}</td>
                                        </tr>
                                        <tr>
                                            <th>Expiry Date:</th>
                                            <td>
                                                <span class="badge ${expiryStatus.class}">
                                                    ${item.expiry_date ? formatDate(item.expiry_date) : 'N/A'}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-12">
                                    <h6 class="text-primary"><i class="fas fa-chart-line me-2"></i>Stock & Pricing</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Current Stock</small>
                                                    <h4 class="mb-0">
                                                        <span class="badge ${stockStatus.class}">${item.quantity}</span>
                                                    </h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Reserved</small>
                                                    <h4 class="mb-0">${item.reserved_quantity || 0}</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Unit Price</small>
                                                    <h4 class="mb-0">${formatCurrency(item.price)}</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Total Value</small>
                                                    <h4 class="mb-0">${formatCurrency(item.quantity * item.price)}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <h6 class="text-primary"><i class="fas fa-clock me-2"></i>Timestamps</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="20%">Created:</th>
                                            <td>${item.created_at ? formatDate(item.created_at, 'datetime') : 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <th>Last Updated:</th>
                                            <td>${item.updated_at ? formatDate(item.updated_at, 'datetime') : 'N/A'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="Stock.updateQuantity(${item.id}); bootstrap.Modal.getInstance(document.getElementById('stockDetailModal')).hide();">
                                <i class="fas fa-edit me-2"></i>Update Quantity
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('stockDetailModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('stockDetailModal'));
        modal.show();

        // Clean up after modal is hidden
        document.getElementById('stockDetailModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    },

    async updateQuantity(id) {
        const item = this.stockData.find(i => i.id === id);
        if (!item) {
            showError('Medicine not found');
            return;
        }

        // Create update modal
        const modalHTML = `
            <div class="modal fade" id="updateQuantityModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i>Update Stock Quantity
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>${sanitizeHTML(item.name)}</strong><br>
                                Current Stock: <strong>${item.quantity}</strong> units
                            </div>
                            <form id="updateQuantityForm">
                                <div class="mb-3">
                                    <label for="newQuantity" class="form-label">New Quantity *</label>
                                    <input type="number" class="form-control" id="newQuantity" value="${item.quantity}" min="0" required>
                                    <small class="text-muted">Enter the new stock quantity</small>
                                </div>
                                <div class="mb-3">
                                    <label for="updatePrice" class="form-label">Price (PKR)</label>
                                    <input type="number" class="form-control" id="updatePrice" value="${item.price}" min="0" step="0.01">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="Stock.saveQuantityUpdate(${id})">
                                <i class="fas fa-save me-2"></i>Update Stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('updateQuantityModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('updateQuantityModal'));
        modal.show();

        // Clean up after modal is hidden
        document.getElementById('updateQuantityModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    },

    async saveQuantityUpdate(id) {
        const newQuantity = parseInt(document.getElementById('newQuantity').value);
        const newPrice = parseFloat(document.getElementById('updatePrice').value);

        if (isNaN(newQuantity) || newQuantity < 0) {
            showError('Please enter a valid quantity');
            return;
        }

        if (isNaN(newPrice) || newPrice < 0) {
            showError('Please enter a valid price');
            return;
        }

        try {
            showLoading('Updating stock...');

            const response = await fetch(getBaseURL() + `/api/medicines/${id}`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    quantity: newQuantity,
                    price: newPrice
                })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Stock updated successfully!');

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('updateQuantityModal'));
                if (modal) modal.hide();

                // Reload data
                await this.loadStockData();
            } else {
                showError('Failed to update stock: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating stock:', error);
            showError('Failed to update stock. Please try again.');
        } finally {
            hideLoading();
        }
    },

    async deleteStock(id) {
        const item = this.stockData.find(i => i.id === id);
        if (!item) {
            showError('Medicine not found');
            return;
        }

        if (!confirm(`Are you sure you want to delete "${item.name}"?\n\nThis action cannot be undone!`)) {
            return;
        }

        try {
            showLoading('Deleting medicine...');

            const response = await fetch(getBaseURL() + `/api/medicines/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                showSuccess(`"${item.name}" deleted successfully!`);
                await this.loadStockData();
            } else {
                showError('Failed to delete medicine: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting medicine:', error);
            showError('Failed to delete medicine. Please try again.');
        } finally {
            hideLoading();
        }
    },

    exportToExcel() {
        if (this.filteredData.length === 0) {
            showError('No data to export');
            return;
        }

        if (typeof XLSX === 'undefined') {
            showError('Excel library not loaded');
            return;
        }

        const exportData = this.filteredData.map(item => ({
            'ID': item.id,
            'Name': item.name,
            'Category': item.category || '',
            'Manufacturer': item.manufacturer || '',
            'Batch Number': item.batch_number || '',
            'Quantity': item.quantity,
            'Reserved': item.reserved_quantity || 0,
            'Price (PKR)': item.price,
            'Total Value (PKR)': item.quantity * item.price,
            'Expiry Date': item.expiry_date ? formatDate(item.expiry_date) : '',
            'Created At': item.created_at ? formatDate(item.created_at, 'datetime') : '',
            'Updated At': item.updated_at ? formatDate(item.updated_at, 'datetime') : ''
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Stock Data');

        const fileName = `stock_report_${new Date().toISOString().split('T')[0]}.xlsx`;
        XLSX.writeFile(wb, fileName);

        showSuccess('Excel file exported successfully!');
    },

    exportToPDF() {
        showInfo('PDF export feature coming soon!');
    },

    printStock() {
        window.print();
    }
};
