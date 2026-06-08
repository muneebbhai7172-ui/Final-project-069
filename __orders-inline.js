
// Define minimal global placeholder before full function declarations
window.switchView = function() {
    console.log('switchView not ready yet');
};

console.log('Orders script starting...');

// Keep state available before any async initialization callbacks run
let allOrders = []; // Store all orders for filtering
let currentView = 'table'; // Current view mode
const BILLING_PREFILL_KEY = 'billingPrefillOrder';

function normalizeOrderResponse(response) {
    if (!response) return null;

    if (response.success) {
        return response.data || response.order || null;
    }

    return response.data || response.order || response;
}

function normalizeOrderItem(item) {
    const quantity = Number.parseInt(item?.quantity ?? item?.qty ?? 0, 10) || 0;
    const price = Number.parseFloat(item?.price ?? item?.unit_price ?? 0) || 0;
    const subtotal = Number.parseFloat(item?.subtotal ?? item?.total ?? (quantity * price)) || 0;

    return {
        ...item,
        medicine_id: item?.medicine_id || item?.id || item?.medicine?.id || null,
        medicine_name: item?.medicine_name || item?.medicine?.name || item?.name || 'N/A',
        quantity,
        price,
        subtotal
    };
}

function normalizeOrderPayload(order) {
    const normalizedOrder = order || {};
    const items = Array.isArray(normalizedOrder.items)
        ? normalizedOrder.items.map(normalizeOrderItem)
        : [];

    return {
        ...normalizedOrder,
        items
    };
}

async function fetchOrderDetails(orderId) {
    const response = await api.getOrder(orderId);
    const order = normalizeOrderResponse(response);

    if (!order || order.error) {
        throw new Error('Order details not found');
    }

    return normalizeOrderPayload(order);
}

function buildBillingPayloadFromOrder(orderData) {
    const orderCode = orderData.order_id || orderData.order_number || String(orderData.id || '');
    const cleanOrderCode = String(orderCode).replace(/[^a-zA-Z0-9_-]/g, '');
    const normalizedItems = (orderData.items || []).map(normalizeOrderItem);
    const computedSubtotal = normalizedItems.reduce((sum, item) => sum + item.subtotal, 0);

    return {
        source: 'online-order',
        orderId: orderData.id || null,
        orderCode,
        invoiceNumber: `INV-${cleanOrderCode || Date.now()}`,
        status: orderData.status || 'Pending',
        createdAt: orderData.created_at || orderData.order_date || null,
        notes: orderData.notes || '',
        payment: {
            method: orderData.payment_method || '',
            transaction_id: orderData.transaction_id || ''
        },
        customer: {
            name: orderData.customer_name || '',
            phone: orderData.phone || '',
            email: orderData.email || '',
            address: orderData.address || ''
        },
        items: normalizedItems.map(item => ({
            id: item.medicine_id,
            name: item.medicine_name,
            quantity: item.quantity,
            price: item.price,
            total: item.subtotal
        })),
        totals: {
            subtotal: computedSubtotal,
            total: Number.parseFloat(orderData.total_amount || computedSubtotal) || computedSubtotal
        }
    };
}

function sendOrderToBilling(orderData, options = {}) {
    const autoPrint = options.autoPrint === true;

    try {
        const billingPayload = buildBillingPayloadFromOrder(orderData);
        sessionStorage.setItem(BILLING_PREFILL_KEY, JSON.stringify(billingPayload));

        const params = new URLSearchParams();
        params.set('source', 'online-order');
        if (billingPayload.orderId) {
            params.set('orderId', String(billingPayload.orderId));
        }
        if (autoPrint) {
            params.set('autoprint', '1');
        }

        window.location.href = `../billing/index.html?${params.toString()}`;
    } catch (error) {
        console.error('Failed to send order to billing:', error);
        showNotification('Unable to send order to Billing. Please try again.', 'error');
    }
}

async function sendOrderToBillingById(orderId, autoPrint = false) {
    try {
        const orderData = await fetchOrderDetails(orderId);
        window.currentOrderData = orderData;
        sendOrderToBilling(orderData, { autoPrint });
    } catch (error) {
        console.error('Error preparing billing payload:', error);
        showNotification('Unable to load online order details for billing', 'error');
    }
}

// Test API connectivity
async function testAPIConnectivity() {
    console.log('Testing API connectivity (with timeout)...');
    
    // Just test the current API baseURL with a 3-second timeout
    if (typeof api === 'undefined' || !api.baseURL) {
        console.warn('API not defined, skipping connectivity test');
        return false;
    }

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000); // 3 second timeout
        
        const response = await fetch(api.baseURL + '/api/orders', { 
            credentials: 'include',
            signal: controller.signal 
        });
        clearTimeout(timeoutId);
        
        if (response.ok) {
            console.log('✅ API accessible at:', api.baseURL);
            return true;
        }
    } catch (error) {
        console.log('⚠️ API test timed out or failed:', error.message);
    }
    
    return false;
}

// Define functions immediately at global scope to ensure availability
async function loadOrders() {
    console.log('loadOrders() called');
    console.log('API object type:', typeof api);
    console.log('API object:', api);
    
    const tbody = document.getElementById('orders-table-body');
    
    try {
        if (typeof api === 'undefined') {
            throw new Error('API client not loaded - window.api is undefined');
        }

        console.log('Calling api.getOrders()...');
        const data = await api.getOrders();
        console.log('API response received:', data);

        // Handle both response formats
        let orders;
        if (data.success && data.data) {
            orders = data.data;
        } else if (Array.isArray(data)) {
            orders = data;
        } else if (data.data && Array.isArray(data.data)) {
            orders = data.data;
        } else {
            throw new Error('Invalid response format: ' + JSON.stringify(data));
        }

        console.log('Processing', orders.length, 'orders');
        allOrders = orders; // Store for filtering
        displayOrders(orders);
    } catch (error) {
        console.error('Error loading orders:', error);
        console.error('Error stack:', error.stack);
        
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <p><strong>Error loading orders:</strong></p>
                        <p>${error.message}</p>
                        <button class="btn btn-primary mt-2" onclick="location.reload()">Reload Page</button>
                    </td>
                </tr>
            `;
        }
    }
}

// Make loadOrders globally accessible immediately
window.loadOrders = loadOrders;

window.switchView = function(view) {
    console.log('Global switchView called with:', view);
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    const tableBtn = document.getElementById('tableViewBtn');
    const cardBtn = document.getElementById('cardViewBtn');

    if (tableView && cardView) {
        if (view === 'table') {
            tableView.style.display = 'block';
            cardView.style.display = 'none';
            if (tableBtn) tableBtn.classList.add('active');
            if (cardBtn) cardBtn.classList.remove('active');
        } else {
            tableView.style.display = 'none';
            cardView.style.display = 'flex';
            if (tableBtn) tableBtn.classList.remove('active');
            if (cardBtn) cardBtn.classList.add('active');
        }
    }
};

console.log('Global functions defined');

// Helper functions for the global loadOrders
window.getStatusClass = function(status) {
    switch(status) {
        case 'Pending': return 'status-pending';
        case 'Dispatched': return 'status-dispatched';
        case 'Completed': return 'status-completed';
        case 'Cancelled': return 'status-cancelled';
        default: return '';
    }
};

window.formatDate = function(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const dateStr = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
    const timeStr = date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
    return `${dateStr} at ${timeStr}`;
};

console.log('Helper functions defined');

// Test function availability immediately
console.log('Testing function availability:');
console.log('- window.loadOrders:', typeof window.loadOrders);
console.log('- window.switchView:', typeof window.switchView);

// Check authentication (commented out for testing)
// if (!sessionStorage.getItem('user_role')) {
//     window.location.href = '../auth/login.html';
// }

console.log('Variables initialized...');

// Switch between table and card view
function switchView(view) {
    console.log('switchView called with:', view);
    currentView = view;
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    const tableBtn = document.getElementById('tableViewBtn');
    const cardBtn = document.getElementById('cardViewBtn');

    if (view === 'table') {
        tableView.style.display = 'block';
        cardView.style.display = 'none';
        tableBtn.classList.add('active');
        cardBtn.classList.remove('active');
    } else {
        tableView.style.display = 'none';
        cardView.style.display = 'flex';
        tableBtn.classList.remove('active');
        cardBtn.classList.add('active');
        renderCardView(allOrders);
    }
}

// Make functions globally accessible
window.switchView = switchView;
console.log('switchView assigned to window:', typeof window.switchView);

// Make functions globally accessible
window.switchView = switchView;
console.log('switchView assigned to window:', typeof window.switchView);

// Render card view
function renderCardView(orders) {
    const cardView = document.getElementById('cardView');

    if (!orders || orders.length === 0) {
        cardView.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No orders found</p>
            </div>
        `;
        return;
    }

    cardView.innerHTML = '';
    orders.forEach(order => {
        const statusClass = getStatusClass(order.status);
        const card = `
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0">${order.order_id || order.id}</h5>
                            <span class="badge ${statusClass}">${order.status}</span>
                        </div>
                        <p class="card-text mb-2">
                            <i class="fas fa-user text-muted me-2"></i>
                            <strong>${order.customer_name || 'N/A'}</strong>
                        </p>
                        <p class="card-text mb-2">
                            <i class="fas fa-phone text-muted me-2"></i>
                            ${order.phone || 'N/A'}
                        </p>
                        <p class="card-text mb-2">
                            <i class="fas fa-rupee-sign text-muted me-2"></i>
                            <strong class="text-primary">Rs. ${parseFloat(order.total_amount || 0).toFixed(2)}</strong>
                        </p>
                        <p class="card-text mb-3">
                            <i class="fas fa-calendar text-muted me-2"></i>
                            <small class="text-muted">${formatDate(order.created_at || order.order_date)}</small>
                        </p>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm flex-grow-1 ${statusClass}"
                                    onchange="updateOrderStatus(${order.id}, this.value)">
                                <option value="Pending" ${order.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Dispatched" ${order.status === 'Dispatched' ? 'selected' : ''}>Dispatched</option>
                                <option value="Completed" ${order.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                <option value="Cancelled" ${order.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                            <button class="btn btn-sm btn-primary" onclick="viewOrderDetails(${order.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        cardView.innerHTML += card;
    });
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('orderSearch');
    const suggestionsBox = document.getElementById('searchSuggestions');

    if (searchInput) {
        // Show suggestions on input
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();

            if (searchTerm.length === 0) {
                displayOrders(allOrders);
                hideSuggestions();
                return;
            }

            // Show suggestions if search term is at least 1 character
            if (searchTerm.length >= 1) {
                showSuggestions(searchTerm);
            }

            // Filter orders
            filterOrders(searchTerm);
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                hideSuggestions();
            }
        });

        // Show suggestions on focus if there's text
        searchInput.addEventListener('focus', function(e) {
            if (e.target.value.trim().length > 0) {
                showSuggestions(e.target.value.trim());
            }
        });
    }

    // Load orders after all functions are defined
    console.log('DOM loaded, checking if loadOrders exists:', typeof loadOrders);
    console.log('API object:', typeof api);

    // Ensure API is globally accessible
    if (typeof api !== 'undefined') {
        window.api = api;
        console.log('API assigned to window');
        console.log('API baseURL:', api.baseURL);
    } else {
        console.error('API client not loaded');
    }

    // Test API connectivity first, then load orders
    async function initializeOrders() {
        // Skip connectivity test - just load orders directly
        console.log('Initializing orders, calling loadOrders directly...');
        if (typeof window.loadOrders === 'function') {
            try {
                await window.loadOrders();
            } catch (error) {
                console.error('Error loading orders:', error);
            }
        } else {
            console.error('Global loadOrders function not found');
        }
    }

    // Initialize orders immediately - don't wait for anything
    initializeOrders();

    // Final check - log all function availability
    console.log('Function availability check:');
    console.log('- loadOrders:', typeof window.loadOrders);
    console.log('- switchView:', typeof window.switchView);
    console.log('- api:', typeof window.api);
});

// Emergency global function definitions (fallback)
if (typeof window.loadOrders === 'undefined') {
    window.loadOrders = async function() {
        console.log('Fallback loadOrders called');
        try {
            const data = await api.getOrders();
            if (data.success && data.data) {
                console.log('Orders loaded via fallback:', data.data.length);
                if (typeof displayOrders === 'function') {
                    displayOrders(data.data);
                }
            }
        } catch (error) {
            console.error('Fallback loadOrders error:', error);
        }
    };
}

// Force load orders after a delay to ensure everything is initialized
setTimeout(function() {
    console.log('Force-load timer triggered');
    if (typeof window.loadOrders === 'function') {
        console.log('Calling loadOrders from force-load');
        window.loadOrders().catch(err => console.error('Force-load error:', err));
    }
}, 500);

if (typeof window.switchView === 'undefined') {
    window.switchView = function(view) {
        console.log('Fallback switchView called with:', view);
        const tableView = document.getElementById('tableView');
        const cardView = document.getElementById('cardView');
        if (tableView && cardView) {
            if (view === 'table') {
                tableView.style.display = 'block';
                cardView.style.display = 'none';
            } else {
                tableView.style.display = 'none';
                cardView.style.display = 'flex';
            }
        }
    };
}

// Filter orders based on search term
function filterOrders(searchTerm) {
    const lowerSearch = searchTerm.toLowerCase();

    const filtered = allOrders.filter(order => {
        const orderId = (order.order_id || order.id || '').toString().toLowerCase();
        const customerName = (order.customer_name || '').toLowerCase();
        const phone = (order.phone || '').toString().toLowerCase();

        return orderId.includes(lowerSearch) ||
               customerName.includes(lowerSearch) ||
               phone.includes(lowerSearch);
    });

    displayOrders(filtered);
}

// Show search suggestions
function showSuggestions(searchTerm) {
    const suggestionsBox = document.getElementById('searchSuggestions');
    const lowerSearch = searchTerm.toLowerCase();

    // Find matching orders
    const matches = allOrders.filter(order => {
        const orderId = (order.order_id || order.id || '').toString().toLowerCase();
        const customerName = (order.customer_name || '').toLowerCase();
        const phone = (order.phone || '').toString().toLowerCase();

        return orderId.includes(lowerSearch) ||
               customerName.includes(lowerSearch) ||
               phone.includes(lowerSearch);
    }).slice(0, 5); // Limit to 5 suggestions

    if (matches.length === 0) {
        suggestionsBox.innerHTML = '<div class="no-suggestions"><i class="fas fa-search me-2"></i>No matching orders found</div>';
        suggestionsBox.style.display = 'block';
        suggestionsBox.classList.add('show');
        return;
    }

    let html = '';
    matches.forEach(order => {
        const orderId = order.order_id || order.id;
        const statusClass = getStatusClass(order.status);

        html += `
            <div class="suggestion-item" onclick="selectSuggestion('${orderId}', '${(order.customer_name || '').replace(/'/g, "\\'")}')">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="suggestion-label">Order ID</span>
                        <div class="suggestion-value">${orderId}</div>
                        <div class="suggestion-details">
                            <i class="fas fa-user me-1"></i>${order.customer_name || 'N/A'}
                            <i class="fas fa-phone ms-2 me-1"></i>${order.phone || 'N/A'}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge ${statusClass}">${order.status}</span>
                        <div class="suggestion-details mt-1">Rs. ${parseFloat(order.total_amount || 0).toFixed(2)}</div>
                    </div>
                </div>
            </div>
        `;
    });

    suggestionsBox.innerHTML = html;
    suggestionsBox.style.display = 'block';
    suggestionsBox.classList.add('show');
}

// Hide suggestions
function hideSuggestions() {
    const suggestionsBox = document.getElementById('searchSuggestions');
    if (suggestionsBox) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.classList.remove('show');
    }
}

// Select a suggestion
function selectSuggestion(orderId, customerName) {
    const searchInput = document.getElementById('orderSearch');
    searchInput.value = orderId;
    hideSuggestions();
    filterOrders(orderId);
}

// Display orders based on current view
function displayOrders(orders) {
    console.log('displayOrders called with:', orders);
    console.log('Current view:', currentView);
    console.log('Orders count:', orders ? orders.length : 'null/undefined');

    // Update stats cards
    updateOrderStats(orders);

    if (currentView === 'table') {
        renderTableView(orders);
    } else {
        renderCardView(orders);
    }
}

// Update order statistics cards
function updateOrderStats(orders) {
    if (!orders) return;

    const total = orders.length;
    const pending = orders.filter(o => o.status === 'Pending').length;
    const completed = orders.filter(o => o.status === 'Completed' || o.status === 'Delivered').length;
    const cancelled = orders.filter(o => o.status === 'Cancelled').length;

    animateValue('totalOrdersCount', total);
    animateValue('pendingOrdersCount', pending);
    animateValue('completedOrdersCount', completed);
    animateValue('cancelledOrdersCount', cancelled);
}

// Animate number counting
function animateValue(elementId, endValue) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const duration = 1000;
    const startValue = parseInt(element.textContent) || 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(startValue + (endValue - startValue) * easeOut);
        element.textContent = current;

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    requestAnimationFrame(update);
}

// Render table view
function renderTableView(orders) {
    console.log('renderTableView called with:', orders);
    const tbody = document.getElementById('orders-table-body');
    console.log('Table body element found:', !!tbody);

    if (!orders || orders.length === 0) {
        console.log('No orders to display');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                    <p class="text-muted mb-0">No orders found</p>
                </td>
            </tr>
        `;
        return;
    }

    console.log('Rendering', orders.length, 'orders');
    tbody.innerHTML = '';
    orders.forEach((order, index) => {
        console.log(`Processing order ${index + 1}:`, order);
        const statusClass = getStatusClass(order.status);
        const row = `
            <tr style="animation: fadeInUp 0.3s ease forwards; animation-delay: ${index * 0.05}s; opacity: 0;">
                <td>
                    <input type="checkbox" class="form-check-input order-checkbox" value="${order.id}" onchange="updateSelectAllState()">
                </td>
                <td><span class="order-id-badge">#${order.order_id || order.id}</span></td>
                <td style="font-weight: 600; color: #1e293b;">${order.customer_name || 'Walk-in'}</td>
                <td style="color: #64748b;">${order.phone || 'N/A'}</td>
                <td style="font-weight: 700; color: #10b981;">Rs. ${parseFloat(order.total_amount || 0).toLocaleString()}</td>
                <td>
                    <span class="status-badge ${statusClass}" style="cursor: pointer;" onclick="showStatusChangeModal(${order.id}, '${order.status}', '${order.order_id || order.id}')">
                        ${order.status}
                    </span>
                </td>
                <td style="color: #64748b; font-size: 13px;">${formatDate(order.created_at || order.order_date)}</td>
                <td>
                    <button class="btn-action-premium view" onclick="viewOrderDetails(${order.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action-premium print" onclick="printOrder(${order.id})" title="Print">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="btn-action-premium delete" onclick="showDeleteOrderModal(${order.id}, '${order.order_id || order.id}', '${(order.customer_name || 'N/A').replace(/'/g, "\\'")}', ${order.total_amount || 0})" title="Delete Order">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
    console.log('Table rendered successfully');
}

// Get status class
function getStatusClass(status) {
    switch(status) {
        case 'Pending': return 'status-pending';
        case 'Dispatched': return 'status-dispatched';
        case 'Completed': return 'status-completed';
        case 'Cancelled': return 'status-cancelled';
        default: return '';
    }
}

// Format date with time
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);

    const dateStr = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });

    const timeStr = date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });

    return `${dateStr} at ${timeStr}`;
}

// Toggle select all checkboxes
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

// Update select all state
function updateSelectAllState() {
    const selectAll = document.getElementById('selectAllOrders');
    const checkboxes = document.querySelectorAll('.order-checkbox');
    const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');

    if (checkboxes.length === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    } else if (checkedBoxes.length === checkboxes.length) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
    } else if (checkedBoxes.length > 0) {
        selectAll.checked = false;
        selectAll.indeterminate = true;
    } else {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }
}

// Show status change modal
let currentStatusChangeOrderId = null;
let currentStatusChangeStatus = null;

function showStatusChangeModal(orderId, currentStatus, orderIdDisplay) {
    currentStatusChangeOrderId = orderId;
    currentStatusChangeStatus = currentStatus;

    // Update modal content
    document.getElementById('modalOrderId').textContent = orderIdDisplay;
    document.getElementById('modalCurrentStatus').textContent = currentStatus;
    document.getElementById('modalCurrentStatus').className = 'status-badge ' + getStatusClass(currentStatus);

    // Set select value
    document.getElementById('newStatusSelect').value = currentStatus;
    document.getElementById('statusNotes').value = '';

    // Update info based on current status
    updateStatusInfo(currentStatus);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('statusChangeModal'));
    modal.show();

    // Add event listener for status select change
    document.getElementById('newStatusSelect').onchange = function() {
        updateStatusWarning(currentStatus, this.value);
    };
}

// Update status info text
function updateStatusInfo(currentStatus) {
    const infoText = document.getElementById('statusInfoText');
    switch(currentStatus) {
        case 'Pending':
            infoText.textContent = 'This order is awaiting dispatch. Select a new status to update the order.';
            break;
        case 'Dispatched':
            infoText.textContent = 'This order has been dispatched. Stock has been deducted.';
            break;
        case 'Completed':
            infoText.textContent = 'This order has been completed and delivered.';
            break;
        case 'Cancelled':
            infoText.textContent = 'This order has been cancelled.';
            break;
    }
}

// Update status warning
function updateStatusWarning(currentStatus, newStatus) {
    const warningBox = document.getElementById('statusWarningBox');
    const warningText = document.getElementById('statusWarningText');

    warningBox.style.display = 'none';

    if (newStatus === 'Dispatched' && currentStatus !== 'Dispatched') {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-info';
        warningText.innerHTML = `
            <strong>Dispatch Actions:</strong><br>
            • Stock will be deducted from inventory<br>
            • Receipt/bill will be generated<br>
            • Customer records will be updated<br>
            • Order becomes trackable by customer
        `;
    } else if (newStatus === 'Cancelled' && currentStatus === 'Dispatched') {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-warning';
        warningText.innerHTML = `
            <strong>Warning:</strong> This order was already dispatched!<br>
            • Stock will be RESTORED to inventory<br>
            • This action cannot be undone
        `;
    } else if (newStatus === 'Cancelled') {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-warning';
        warningText.innerHTML = `
            <strong>Warning:</strong> Order will be cancelled.<br>
            This action cannot be undone.
        `;
    } else if (newStatus === 'Completed') {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-success';
        warningText.innerHTML = `
            <strong>Complete Order:</strong><br>
            • Mark order as delivered<br>
            • Finalize transaction
        `;
    }
}

// Confirm status change from modal
function confirmStatusChange() {
    const newStatus = document.getElementById('newStatusSelect').value;
    const notes = document.getElementById('statusNotes').value.trim();

    if (currentStatusChangeOrderId && currentStatusChangeStatus !== newStatus) {
        // Store if order details modal was open
        const detailsModal = document.getElementById('orderDetailsModal');
        const isDetailsOpen = detailsModal && detailsModal.classList.contains('show');

        // Close status change modal
        bootstrap.Modal.getInstance(document.getElementById('statusChangeModal')).hide();

        // Call update API function
        updateOrderStatusAPI(currentStatusChangeOrderId, newStatus, notes);

        // If order details was open, refresh it after a short delay
        if (isDetailsOpen) {
            setTimeout(() => {
                viewOrderDetails(currentStatusChangeOrderId);
            }, 1000);
        }
    } else if (currentStatusChangeStatus === newStatus) {
        showNotification('⚠️ Please select a different status', 'warning');
    }
}

// Show delete order modal
let currentDeleteOrderId = null;

function showDeleteOrderModal(orderId, orderIdDisplay, customerName, amount) {
    currentDeleteOrderId = orderId;

    document.getElementById('deleteOrderId').textContent = orderIdDisplay;
    document.getElementById('deleteCustomerName').textContent = customerName;
    document.getElementById('deleteOrderAmount').textContent = 'Rs. ' + parseFloat(amount).toFixed(2);

    const modal = new bootstrap.Modal(document.getElementById('deleteOrderModal'));
    modal.show();
}

// Confirm delete order
async function confirmDeleteOrder() {
    if (!currentDeleteOrderId) return;

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('deleteOrderModal')).hide();

    // Show loading notification
    showNotification('Deleting order...', 'info');

    // Call delete API
    try {
        const result = await api.deleteOrder(currentDeleteOrderId);
        if (result && result.success) {
            showNotification('✅ Order deleted successfully!', 'success');
            loadOrders();
        } else {
            showNotification('❌ Error: ' + (result.message || 'Failed to delete order'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Network error. Please try again.', 'error');
    }

    currentDeleteOrderId = null;
}

// Show delete all orders modal
function showDeleteAllModal() {
    document.getElementById('totalOrdersCount').textContent = allOrders.length;
    document.getElementById('confirmDeleteAll').checked = false;
    document.getElementById('deleteAllBtn').disabled = true;

    // Enable delete button only when checkbox is checked
    document.getElementById('confirmDeleteAll').onchange = function() {
        document.getElementById('deleteAllBtn').disabled = !this.checked;
    };

    const modal = new bootstrap.Modal(document.getElementById('deleteAllOrdersModal'));
    modal.show();
}

// Confirm delete all orders
async function confirmDeleteAllOrders() {
    if (!document.getElementById('confirmDeleteAll').checked) {
        return;
    }

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('deleteAllOrdersModal')).hide();

    // Show loading notification
    showNotification('Deleting all orders...', 'info');

    // Call delete all API
    try {
        const result = await api.deleteAllOrders();
        if (result && result.success) {
            showNotification('✅ All orders deleted successfully!', 'success');
            loadOrders();
        } else {
            showNotification('❌ Error: ' + (result.message || 'Failed to delete orders'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Network error. Please try again.', 'error');
    }
}

// Update order status API call
async function updateOrderStatusAPI(orderId, newStatus, notes = '') {
    // Get current status from the order
    const currentOrder = allOrders.find(o => o.id === orderId);
    const currentStatus = currentOrder ? currentOrder.status : '';

    // Show loading state
    const statusSelects = document.querySelectorAll(`select[data-id="${orderId}"]`);
    statusSelects.forEach(select => {
        select.disabled = true;
        select.style.opacity = '0.6';
    });

    try {
        const data = await api.updateOrderStatus(orderId, newStatus, notes || `Status changed from ${currentStatus} to ${newStatus} by admin`);

        if (data.success) {
            // Show success message based on status
            let successMsg = '';
            let successTitle = '';

            if (newStatus === 'Dispatched') {
                successTitle = '✅ ORDER DISPATCHED SUCCESSFULLY!';
                successMsg = '\n\n' +
                      '✓ Stock inventory updated\n' +
                      '✓ Receipt/bill generated\n' +
                      '✓ Customer records updated\n' +
                      '✓ Customer can now track order\n\n' +
                      'You can print/download the bill from order details.';

                // Show success notification
                showNotification(successTitle + successMsg, 'success');

                // Reload and show bill option
                loadOrders();
                setTimeout(() => {
                    viewOrderDetails(orderId);
                }, 500);

            } else if (newStatus === 'Completed') {
                successTitle = '✅ ORDER COMPLETED!';
                successMsg = '\n\nOrder has been marked as delivered.\nCustomer transaction is finalized.';
                showNotification(successTitle + successMsg, 'success');
                loadOrders();

            } else if (newStatus === 'Cancelled') {
                successTitle = '❌ ORDER CANCELLED';
                successMsg = '\n\nOrder has been cancelled.';
                if (currentStatus === 'Dispatched') {
                    successMsg += '\nStock has been restored.';
                }
                showNotification(successTitle + successMsg, 'warning');
                loadOrders();

            } else {
                showNotification('Order status updated to: ' + newStatus, 'success');
                loadOrders();
            }
        } else {
            showNotification('❌ Error: ' + (data.message || 'Failed to update order status'), 'error');
            loadOrders();
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Network error. Please check your connection.', 'error');
        loadOrders();
    } finally {
        // Reset loading state
        statusSelects.forEach(select => {
            select.disabled = false;
            select.style.opacity = '1';
        });
    }
}

// Update order status (modified to use advanced modal dialog)
function updateOrderStatus(orderId, newStatus, notes = '') {
    // Get current status from the order
    const currentOrder = allOrders.find(o => o.id === orderId);
    const currentStatus = currentOrder ? currentOrder.status : '';

    // Store the current order information for the modal
    currentStatusChangeOrderId = orderId;
    currentStatusChangeStatus = currentStatus;

    // Set modal content
    document.getElementById('modalOrderId').textContent = orderId;
    document.getElementById('modalCurrentStatus').textContent = currentStatus;
    document.getElementById('modalCurrentStatus').className = `status-badge status-${currentStatus.toLowerCase()}`;
    document.getElementById('newStatusSelect').value = newStatus;
    document.getElementById('statusNotes').value = notes;

    // Update status info and warnings
    updateStatusInfo(currentStatus);
    updateStatusWarning(currentStatus, newStatus);

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('statusChangeModal'));
    modal.show();
}

// Show notification toast
function showNotification(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toast = document.createElement('div');
    const bgColors = {
        success: 'linear-gradient(135deg, #10b981, #059669)',
        error: 'linear-gradient(135deg, #ef4444, #dc2626)',
        warning: 'linear-gradient(135deg, #f59e0b, #d97706)',
        info: 'linear-gradient(135deg, #3b82f6, #2563eb)'
    };

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.style.cssText = `
        background: ${bgColors[type] || bgColors.info};
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: start;
        gap: 15px;
        animation: slideInRight 0.3s ease;
        max-width: 100%;
        word-wrap: break-word;
    `;

    toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info}" style="font-size: 24px; margin-top: 2px;"></i>
        <div style="flex: 1; white-space: pre-line; line-height: 1.6;">${message}</div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0; margin-left: 10px; opacity: 0.8; transition: opacity 0.2s;">
            <i class="fas fa-times"></i>
        </button>
    `;

    // Add animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    if (!document.getElementById('toastAnimations')) {
        style.id = 'toastAnimations';
        document.head.appendChild(style);
    }

    toastContainer.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// View order details
async function viewOrderDetails(orderId) {
    try {
        const data = await fetchOrderDetails(orderId);

        if (!data || data.error) {
            showNotification('Error loading order details', 'error');
            return;
        }

        const statusClass = getStatusClass(data.status);
        const orderCode = data.order_id || data.order_number || data.id;
        const statusArg = JSON.stringify(String(data.status || ''));
        const orderCodeArg = JSON.stringify(String(orderCode || ''));
        const isDispatched = data.status === 'Dispatched' || data.status === 'Completed' || data.status === 'Delivered';
        const paymentMethod = data.payment_method || 'N/A';
        const orderLogs = Array.isArray(data.logs) ? data.logs : [];

        let itemsHtml = '';
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${item.medicine_name || 'N/A'}</td>
                        <td>${item.quantity || 0}</td>
                        <td>Rs. ${parseFloat(item.price || 0).toFixed(2)}</td>
                        <td><strong>Rs. ${parseFloat(item.subtotal || 0).toFixed(2)}</strong></td>
                    </tr>
                `;
            });
        } else {
            itemsHtml = '<tr><td colspan="4" class="text-center">No items found</td></tr>';
        }

        let logsHtml = '';
        if (orderLogs.length > 0) {
            orderLogs.slice(0, 8).forEach(log => {
                logsHtml += `
                    <tr>
                        <td>${formatDate(log.created_at)}</td>
                        <td>${log.status || 'N/A'}</td>
                        <td>${log.notes || 'No notes'}</td>
                    </tr>
                `;
            });
        } else {
            logsHtml = '<tr><td colspan="3" class="text-center">No status history found</td></tr>';
        }

        const notesHtml = data.notes
            ? `<div class="mb-3"><p><strong>Notes:</strong></p><p class="ms-3">${data.notes}</p></div>`
            : '';

        const readyHtml = isDispatched
            ? `
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Order Ready!</strong> Send to Billing for receipt print and billing output.
                </div>
            `
            : '';

        let actionButtonsHtml = `
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button class="btn btn-primary" onclick='sendOrderToBillingById(${data.id}, false)'>
                    <i class="fas fa-file-invoice-dollar me-2"></i>Send To Billing
                </button>
                <button class="btn btn-success" onclick='sendOrderToBillingById(${data.id}, true)'>
                    <i class="fas fa-print me-2"></i>Send & Print Bill
                </button>
        `;

        if (isDispatched) {
            actionButtonsHtml += `
                <button class="btn btn-outline-success" onclick='downloadBill(${orderCodeArg})'>
                    <i class="fas fa-download me-2"></i>Download PDF
                </button>
                <button class="btn btn-outline-primary" onclick='printBill(${orderCodeArg})'>
                    <i class="fas fa-bolt me-2"></i>Quick Print
                </button>
            `;
        }

        actionButtonsHtml += '</div>';

        const modalContent = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>Order ID:</strong> ${orderCode}</p>
                    <p><strong>Order Number:</strong> ${data.order_number || 'N/A'}</p>
                    <p><strong>Customer:</strong> ${data.customer_name || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Status:</strong>
                        <span class="status-badge ${statusClass}">${data.status}</span>
                        <button class="btn btn-sm btn-outline-primary ms-2" onclick='showStatusChangeModal(${data.id}, ${statusArg}, ${orderCodeArg})' title="Change Status">
                            <i class="fas fa-edit"></i> Change
                        </button>
                    </p>
                    <p><strong>Order Date:</strong> ${formatDate(data.created_at || data.order_date)}</p>
                    <p><strong>Payment Method:</strong> ${paymentMethod}</p>
                    <p><strong>Transaction ID:</strong> <span class="badge bg-info">${data.transaction_id || 'N/A'}</span></p>
                    <p><strong>Preferred Delivery:</strong> ${data.preferred_time || 'N/A'}</p>
                </div>
            </div>

            <div class="mb-3">
                <p><strong>Delivery Address:</strong></p>
                <p class="ms-3">${data.address || 'N/A'}</p>
            </div>

            ${notesHtml}
            ${readyHtml}

            <h6 class="fw-bold mt-4 mb-3">Order Items</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th>Rs. ${parseFloat(data.total_amount || 0).toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h6 class="fw-bold mt-4 mb-3">Status History</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${logsHtml}
                    </tbody>
                </table>
            </div>

            ${actionButtonsHtml}
        `;

        document.getElementById('orderDetailsContent').innerHTML = modalContent;

        window.currentOrderData = data;
        hideSuggestions();

        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
        modal.show();
    } catch (error) {
        console.error('Error loading order details:', error);
        showNotification('Error loading order details', 'error');
    }
}

// Print directly from table action button
async function printOrder(orderId) {
    await sendOrderToBillingById(orderId, true);
}

// Print Bill
function printBill(orderId) {
    const data = normalizeOrderPayload(window.currentOrderData);
    if (!data || !Array.isArray(data.items)) {
        alert('Order data not available');
        return;
    }

    // Create print window
    const printWindow = window.open('', '', 'height=600,width=800');

    let itemsRows = '';
    if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
            itemsRows += `
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${item.medicine_name || 'N/A'}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: center;">${item.quantity || 0}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">Rs. ${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><strong>Rs. ${parseFloat(item.subtotal || 0).toFixed(2)}</strong></td>
                </tr>
            `;
        });
    }

    const billHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Order Bill - ${data.order_id || data.id}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                .bill-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px solid #007bff;
                    padding-bottom: 20px;
                }
                .bill-header h1 {
                    margin: 0;
                    color: #007bff;
                    font-size: 28px;
                }
                .bill-header p {
                    margin: 5px 0;
                    color: #666;
                }
                .bill-info {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                }
                .info-section {
                    flex: 1;
                }
                .info-section h3 {
                    color: #007bff;
                    font-size: 16px;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 5px;
                }
                .info-section p {
                    margin: 5px 0;
                    font-size: 14px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                table thead {
                    background-color: #007bff;
                    color: white;
                }
                table th {
                    padding: 12px;
                    text-align: left;
                }
                table td {
                    padding: 10px;
                }
                .total-section {
                    margin-top: 20px;
                    text-align: right;
                    border-top: 2px solid #007bff;
                    padding-top: 15px;
                }
                .total-section h2 {
                    margin: 0;
                    color: #007bff;
                    font-size: 24px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 5px 15px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: bold;
                    background-color: #28a745;
                    color: white;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                    border-top: 1px solid #ddd;
                    padding-top: 20px;
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="bill-header">
                <h1>🏥 MUNEEB DRUG HOUSE</h1>
                <p>Complete Pharmacy Solutions</p>
                <p>Phone: +92-XXX-XXXXXXX | Email: info@waqardrughouse.com</p>
            </div>

            <div class="bill-info">
                <div class="info-section">
                    <h3>Bill To:</h3>
                    <p><strong>${data.customer_name || 'N/A'}</strong></p>
                    <p>Phone: ${data.phone || 'N/A'}</p>
                    <p>Email: ${data.email || 'N/A'}</p>
                    <p>Address: ${data.address || 'N/A'}</p>
                </div>
                <div class="info-section" style="text-align: right;">
                    <h3>Order Details:</h3>
                    <p><strong>Order ID:</strong> ${data.order_id || data.id}</p>
                    <p><strong>Date:</strong> ${formatDate(data.created_at || data.order_date)}</p>
                    <p><strong>Status:</strong> <span class="status-badge">${data.status}</span></p>
                    <p><strong>Payment:</strong> ${data.payment_method || 'N/A'}</p>
                    ${data.payment_method && data.payment_method.toLowerCase() !== 'cash_on_delivery' && data.payment_method.toLowerCase() !== 'cash' ? `
                        <p><strong>Transaction ID:</strong> ${data.transaction_id || 'N/A'}</p>
                    ` : ''}
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th style="text-align: center;">Quantity</th>
                        <th style="text-align: right;">Price</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsRows}
                </tbody>
            </table>

            <div class="total-section">
                <h2>Total Amount: Rs. ${parseFloat(data.total_amount || 0).toFixed(2)}</h2>
            </div>

            <div class="footer">
                <p><strong>Thank you for your business!</strong></p>
                <p>This is a computer-generated bill. No signature required.</p>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>

            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 30px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    Print Bill
                </button>
                <button onclick="window.close()" style="padding: 10px 30px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `;

    printWindow.document.write(billHTML);
    printWindow.document.close();
    printWindow.focus();
}

// Download Bill as PDF
function downloadBill(orderId) {
    const data = window.currentOrderData;
    if (!data) {
        alert('Order data not available');
        return;
    }

    // Check if jsPDF is loaded
    if (typeof window.jspdf === 'undefined') {
        // Load jsPDF dynamically
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        script.onload = () => {
            generatePDFBill(data);
        };
        document.head.appendChild(script);
    } else {
        generatePDFBill(data);
    }
}

function generatePDFBill(data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Header
    doc.setFontSize(22);
    doc.setTextColor(0, 123, 255);
    doc.text('WAQAR DRUG HOUSE', 105, 20, { align: 'center' });

    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text('Complete Pharmacy Solutions', 105, 27, { align: 'center' });
    doc.text('Phone: +92-XXX-XXXXXXX | Email: info@waqardrughouse.com', 105, 32, { align: 'center' });

    // Line separator
    doc.setDrawColor(0, 123, 255);
    doc.setLineWidth(0.5);
    doc.line(20, 35, 190, 35);

    // Bill To and Order Details
    doc.setFontSize(12);
    doc.setTextColor(0, 123, 255);
    doc.text('Bill To:', 20, 45);
    doc.text('Order Details:', 120, 45);

    doc.setFontSize(10);
    doc.setTextColor(0);
    doc.text(`${data.customer_name || 'N/A'}`, 20, 52);
    doc.text(`Phone: ${data.phone || 'N/A'}`, 20, 58);
    doc.text(`Email: ${data.email || 'N/A'}`, 20, 64);
    doc.text(`Address: ${data.address || 'N/A'}`, 20, 70);

    doc.text(`Order ID: ${data.order_id || data.id}`, 120, 52);
    doc.text(`Date: ${formatDate(data.created_at || data.order_date)}`, 120, 58);
    doc.text(`Status: ${data.status}`, 120, 64);
    doc.text(`Payment: ${data.payment_method || 'N/A'}`, 120, 70);

    // Add Transaction ID if payment is not cash on delivery
    let yPosOffset = 76;
    if (data.payment_method && data.payment_method.toLowerCase() !== 'cash_on_delivery' && data.payment_method.toLowerCase() !== 'cash') {
        doc.text(`Transaction ID: ${data.transaction_id || 'N/A'}`, 120, yPosOffset);
        yPosOffset += 6;
    }

    // Items table
    let yPos = yPosOffset + 9;
    doc.setFontSize(12);
    doc.setTextColor(0, 123, 255);
    doc.text('Order Items:', 20, yPos);

    yPos += 7;
    doc.setFontSize(10);
    doc.setFillColor(0, 123, 255);
    doc.rect(20, yPos, 170, 7, 'F');
    doc.setTextColor(255);
    doc.text('Medicine', 25, yPos + 5);
    doc.text('Qty', 110, yPos + 5);
    doc.text('Price', 135, yPos + 5);
    doc.text('Subtotal', 165, yPos + 5);

    yPos += 10;
    doc.setTextColor(0);

    if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
            doc.text(`${item.medicine_name || 'N/A'}`, 25, yPos);
            doc.text(`${item.quantity || 0}`, 110, yPos);
            doc.text(`Rs. ${parseFloat(item.price || 0).toFixed(2)}`, 135, yPos);
            doc.text(`Rs. ${parseFloat(item.subtotal || 0).toFixed(2)}`, 165, yPos);
            yPos += 7;
        });
    }

    // Total
    yPos += 5;
    doc.setDrawColor(0, 123, 255);
    doc.setLineWidth(0.5);
    doc.line(20, yPos, 190, yPos);

    yPos += 8;
    doc.setFontSize(14);
    doc.setTextColor(0, 123, 255);
    doc.text(`Total Amount: Rs. ${parseFloat(data.total_amount || 0).toFixed(2)}`, 190, yPos, { align: 'right' });

    // Footer
    yPos += 20;
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text('Thank you for your business!', 105, yPos, { align: 'center' });
    doc.text('This is a computer-generated bill. No signature required.', 105, yPos + 5, { align: 'center' });
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 105, yPos + 10, { align: 'center' });

    // Save PDF
    doc.save(`Bill_${data.order_id || data.id}_${Date.now()}.pdf`);
}


// Initialize orders on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - Orders page');

    // Test with sample data if needed
    window.testOrdersDisplay = function() {
        console.log('Testing orders display with sample data...');
        const sampleOrders = [
            {
                "id": 15,
                "customer_id": 452,
                "order_id": "ORD20251125_F624F9",
                "order_number": "ORD20251125586",
                "customer_name": "waqar anjum",
                "phone": "03211532010",
                "address": "pakistan",
                "payment_method": "cash_on_delivery",
                "total_amount": "943.34",
                "notes": "",
                "delivery_date": null,
                "preferred_time": null,
                "status": "Pending",
                "order_date": "2025-11-25T20:54:39.000000Z",
                "transaction_id": "TXN20251125165439_F61CB4",
                "created_at": "2025-11-25T20:54:39.000000Z",
                "updated_at": "2025-12-03T13:24:41.000000Z",
                "items": []
            }
        ];

        allOrders = sampleOrders;
        displayOrders(sampleOrders);
    };

    // Make test function globally available
    console.log('Test function available: window.testOrdersDisplay()');

    // Load orders automatically
    console.log('Auto-loading orders...');
    if (typeof loadOrders === 'function') {
        setTimeout(loadOrders, 1000);
    } else {
        console.error('loadOrders function not found');
    }

    // Initialize sidebar dropdown functionality
    const dropdownItems = document.querySelectorAll('.nav-dropdown');
    dropdownItems.forEach(item => {
        const link = item.querySelector('a.nav-link');
        const dropdownMenu = item.querySelector('.dropdown-menu');

        if (link && dropdownMenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle active state
                const wasActive = item.classList.contains('active');

                // Close all other dropdowns
                dropdownItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                item.classList.toggle('active', !wasActive);
            });
        }

        // Add click handlers to dropdown menu items to allow navigation
        if (dropdownMenu) {
            const childLinks = dropdownMenu.querySelectorAll('a.nav-link');
            childLinks.forEach(childLink => {
                childLink.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent parent dropdown from toggling
                    // Allow default navigation to href
                });
            });
        }
    });

    // Sidebar toggle functionality
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.modern-sidebar');
    const mainContent = document.querySelector('.main-content');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.style.marginLeft = sidebar.classList.contains('collapsed') ? '80px' : '280px';
            }
        });
    }
});
