/**
 * Stock Management JavaScript
 * Handles stock inventory, filtering, and reporting
 * Updated for Laravel backend with CSRF token support
 */

const Stock = {
    stockData: [],
    filteredData: [],
    currentFilter: 'all',
    searchTerm: '',

    init() {
        this.loadStockData();
        this.setupEventListeners();
    },

    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('stock-search');
        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                this.searchTerm = e.target.value;
                this.filterStock();
            }, 300));
        }

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                this.setFilter(filter);
            });
        });

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
    },

    async loadStockData() {
        try {
            showLoading('Loading stock data...');

            const data = await apiFetch('/api/medicines/stock', {
                method: 'GET'
            });

            if (data.success) {
                this.stockData = data.data || [];
                this.filterStock();
                this.updateStockStats();
            } else {
                showError('Failed to load stock data: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading stock data:', error);
            showError('Failed to load stock data. Please try again.');
        } finally {
            hideLoading();
        }
    },

    setFilter(filter) {
        this.currentFilter = filter;

        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.filter === filter) {
                btn.classList.add('active');
            }
        });

        this.filterStock();
    },

    filterStock() {
        let filtered = [...this.stockData];

        // Apply status filter
        if (this.currentFilter !== 'all') {
            filtered = filtered.filter(item => {
                switch (this.currentFilter) {
                    case 'low':
                        return item.quantity <= item.min_stock_level;
                    case 'expired':
                        return new Date(item.expiry_date) < new Date();
                    case 'expiring':
                        const thirtyDaysFromNow = new Date();
                        thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);
                        return new Date(item.expiry_date) <= thirtyDaysFromNow &&
                               new Date(item.expiry_date) >= new Date();
                    default:
                        return true;
                }
            });
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
        this.displayStock();
    },

    displayStock() {
        const tbody = document.getElementById('stock-table-body');
        if (!tbody) return;

        if (this.filteredData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No stock items found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.filteredData.map(item => {
            const stockStatus = this.getStockStatus(item);
            const expiryStatus = this.getExpiryStatus(item);

            return `
                <tr>
                    <td>${sanitizeHTML(item.name)}</td>
                    <td>${sanitizeHTML(item.category || '-')}</td>
                    <td>${sanitizeHTML(item.manufacturer || '-')}</td>
                    <td>${sanitizeHTML(item.batch_number || '-')}</td>
                    <td>
                        <span class="badge ${stockStatus.class}">
                            ${item.quantity}
                        </span>
                    </td>
                    <td>${formatCurrency(item.price)}</td>
                    <td>
                        <span class="badge ${expiryStatus.class}">
                            ${formatDate(item.expiry_date)}
                        </span>
                    </td>
                    <td>${formatCurrency(item.quantity * item.price)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="Stock.editStock(${item.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="Stock.deleteStock(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getStockStatus(item) {
        const minLevel = item.min_stock_level || 10;
        if (item.quantity === 0) {
            return { class: 'bg-danger', text: 'Out of Stock' };
        } else if (item.quantity <= minLevel) {
            return { class: 'bg-warning', text: 'Low Stock' };
        } else {
            return { class: 'bg-success', text: 'In Stock' };
        }
    },

    getExpiryStatus(item) {
        const expiryDate = new Date(item.expiry_date);
        const today = new Date();
        const thirtyDaysFromNow = new Date();
        thirtyDaysFromNow.setDate(today.getDate() + 30);

        if (expiryDate < today) {
            return { class: 'bg-danger', text: 'Expired' };
        } else if (expiryDate <= thirtyDaysFromNow) {
            return { class: 'bg-warning', text: 'Expiring Soon' };
        } else {
            return { class: 'bg-success', text: 'Valid' };
        }
    },

    updateStockStats() {
        // Calculate stats
        const totalItems = this.stockData.length;
        const lowStock = this.stockData.filter(item =>
            item.quantity <= (item.min_stock_level || 10)
        ).length;
        const expired = this.stockData.filter(item =>
            new Date(item.expiry_date) < new Date()
        ).length;
        const totalValue = this.stockData.reduce((sum, item) =>
            sum + (item.quantity * item.price), 0
        );

        // Update stat cards
        const totalItemsEl = document.getElementById('total-items');
        if (totalItemsEl) totalItemsEl.textContent = totalItems;

        const lowStockEl = document.getElementById('low-stock-count');
        if (lowStockEl) lowStockEl.textContent = lowStock;

        const expiredEl = document.getElementById('expired-count');
        if (expiredEl) expiredEl.textContent = expired;

        const totalValueEl = document.getElementById('total-value');
        if (totalValueEl) totalValueEl.textContent = formatCurrency(totalValue);
    },

    async editStock(id) {
        const item = this.stockData.find(i => i.id === id);
        if (!item) return;

        // Implement edit modal logic here
        showInfo('Edit functionality coming soon');
    },

    async deleteStock(id) {
        if (!confirm('Are you sure you want to delete this stock item?')) {
            return;
        }

        try {
            showLoading('Deleting stock item...');

            const data = await apiFetch(`/api/medicines/${id}`, {
                method: 'DELETE'
            });

            if (data.success) {
                showSuccess('Stock item deleted successfully');
                this.loadStockData();
            } else {
                showError('Failed to delete stock item: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting stock:', error);
            showError('Failed to delete stock item. Please try again.');
        } finally {
            hideLoading();
        }
    },

    exportToExcel() {
        if (this.filteredData.length === 0) {
            showError('No data to export');
            return;
        }

        const exportData = this.filteredData.map(item => ({
            'Name': item.name,
            'Category': item.category || '',
            'Manufacturer': item.manufacturer || '',
            'Batch Number': item.batch_number || '',
            'Quantity': item.quantity,
            'Price': item.price,
            'Expiry Date': formatDate(item.expiry_date),
            'Total Value': item.quantity * item.price
        }));

        exportToExcel(exportData, 'stock_report.xlsx', 'Stock Data');
    },

    exportToPDF() {
        if (typeof jsPDF === 'undefined') {
            showError('PDF library not loaded');
            return;
        }

        const doc = new jsPDF();

        // Add title
        doc.setFontSize(18);
        doc.text('Stock Report', 14, 20);

        // Add date
        doc.setFontSize(10);
        doc.text(`Generated: ${formatDate(new Date(), 'datetime')}`, 14, 28);

        // Add table
        const tableData = this.filteredData.map(item => [
            item.name,
            item.category || '',
            item.quantity,
            formatCurrency(item.price),
            formatDate(item.expiry_date),
            formatCurrency(item.quantity * item.price)
        ]);

        doc.autoTable({
            head: [['Name', 'Category', 'Qty', 'Price', 'Expiry', 'Value']],
            body: tableData,
            startY: 35,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [74, 144, 226] }
        });

        doc.save('stock_report.pdf');
        showSuccess('PDF exported successfully');
    },

    printStock() {
        window.print();
    }
};

// Initialize when document is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Stock will be initialized by the page when needed
    });
}
