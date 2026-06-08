
// Billing System Code
const BILLING_PREFILL_KEY = 'billingPrefillOrder';
let currentCustomerType = 'walk-in';
let cartItems = [];
let invoiceNumber = 'INV-' + Date.now().toString().slice(-8);
let allMedicines = [];
let allCustomers = [];

function getApiBaseUrl() {
    if (typeof api !== 'undefined' && api.baseURL) {
        return api.baseURL;
    }

    const loc = window.location;
    if (loc.port === '8000') {
        return loc.origin;
    }

    if (loc.pathname.includes('/waqar/')) {
        return loc.origin + '/waqar/public';
    }

    return loc.origin;
}

// Helper Functions - Define before initialization
function showToast(message, type = 'info') {
    const bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
    const toast = $(`
        <div style="
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: ${bgColor};
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 10000;
            font-weight: 600;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        ">${message}</div>
    `).appendTo('body');

    setTimeout(() => {
        toast.css({ opacity: 1, transform: 'translateY(0)' });
    }, 100);

    setTimeout(() => {
        toast.css({ opacity: 0, transform: 'translateY(20px)' });
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function selectMedicine(med) {
    console.log('Selecting medicine:', med);

    // Store medicine data
    $('#selectedMedicineId').val(med.id);
    $('#selectedMedicineName').val(med.name);
    $('#selectedMedicinePrice').val(med.price);
    $('#selectedMedicineStock').val(med.quantity);
    $('#medicineQuantity').attr('max', med.quantity);
    $('#medicineQuantity').val(1);

    // Clear and hide search box, show selected display
    $('#medicineSearch').val('');

    // Update and show selected medicine display
    $('#displayMedicineName').text(med.name);
    $('#displayMedicinePrice').text('Rs. ' + parseFloat(med.price).toFixed(2));
    $('#displayMedicineStock').text(med.quantity + ' units');
    $('#selectedMedicineDisplay').slideDown(300);

    // Focus on quantity
    setTimeout(() => {
        $('#medicineQuantity').focus();
        $('#medicineQuantity').select();
    }, 100);

    showToast(`✅ Selected: ${med.name} (Rs. ${med.price})`, 'success');
}

function clearMedicineSelection() {
    $('#selectedMedicineId').val('');
    $('#selectedMedicineName').val('');
    $('#selectedMedicinePrice').val('');
    $('#selectedMedicineStock').val('');
    $('#medicineQuantity').val(1);
    $('#medicineQuantity').removeAttr('max');
    $('#selectedMedicineDisplay').slideUp(300);
    $('#medicineSearch').focus();
}

function selectCustomer(cust) {
    console.log('Selecting customer:', cust);
    $('#selectedCustomerId').val(cust.id);
    $('#customerDetailName').text(cust.name);
    $('#customerDetailPhone').text(cust.phone || 'No phone');
    $('#customerDetailAddress').text(cust.address || 'No address');
    $('#customerDetailPoints').text((cust.points || 0) + ' points');
    $('#customerDetails').slideDown(300);
    updatePreview();

    showToast(`Selected: ${cust.name}`, 'success');
}

$(document).ready(function() {
    // Check authentication (commented out for testing)
    // if (!sessionStorage.getItem('user_role')) {
    //     window.location.href = '../auth/login.html';
    //     return;
    // }

    // Initialize
    initializePage();
    loadMedicines();
    loadCustomers();
    updatePreviewDate();
    updatePreviewInvoice();
    loadBillingPrefill();
});

function initializePage() {
    // Add search icons and loading spinners to inputs
    $('.autocomplete-wrapper').each(function() {
        if (!$(this).find('.search-icon').length) {
            $(this).prepend('<i class="fas fa-search search-icon"></i>');
            $(this).append('<i class="fas fa-spinner fa-spin loading-spinner"></i>');
        }
    });

    // Enhanced Medicine Autocomplete with better UI
    $('#medicineSearch').autocomplete({
        minLength: 2,
        delay: 300,
        source: function(request, response) {
            const wrapper = $('#medicineSearch').closest('.autocomplete-wrapper');
            wrapper.addClass('loading');

            $.ajax({
                url: getApiBaseUrl() + '/api/medicines/suggestions/search',
                method: 'GET',
                dataType: 'json',
                data: { term: request.term, limit: 20 },
                success: function(data) {
                    wrapper.removeClass('loading');
                    console.log('Medicine API Response:', data);
                    if (data.success && data.suggestions && data.suggestions.length > 0) {
                        const items = data.suggestions.map(med => ({
                            label: med.name,
                            value: med.name,
                            data: med
                        }));
                        response(items);
                    } else {
                        response([{ label: 'No medicines found', value: '', data: null }]);
                    }
                },
                error: function(xhr, status, error) {
                    wrapper.removeClass('loading');
                    console.error('Medicine API Error:', error);
                    response([{ label: 'Error loading medicines', value: '', data: null }]);
                }
            });
        },
        select: function(event, ui) {
            event.preventDefault(); // Prevent default autocomplete behavior
            if (!ui.item.data) {
                return false;
            }
            // Store the medicine name BEFORE clearing
            const selectedMed = ui.item.data;
            $('#medicineSearch').val(selectedMed.name); // Show selected medicine name
            selectMedicine(selectedMed);
            setTimeout(() => $('#medicineQuantity').focus(), 100);
            return false;
        },
        focus: function(event, ui) {
            return false;
        }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
        if (!item.data) {
            return $("<li class='no-results-message'>")
                .append(`<div><i class="fas fa-capsules"></i><div style="margin-top: 8px;">${item.label}</div></div>`)
                .appendTo(ul);
        }

        const med = item.data;
        const stockBadge = med.quantity > 0
            ? `<span class="autocomplete-item-badge badge-success"><i class="fas fa-check-circle"></i> Stock: ${med.quantity}</span>`
            : `<span class="autocomplete-item-badge badge-danger"><i class="fas fa-times-circle"></i> Out of Stock</span>`;

        const categoryBadge = med.category
            ? `<span class="autocomplete-item-badge badge-info"><i class="fas fa-tag"></i> ${med.category}</span>`
            : '';

        const html = `
            <div class="autocomplete-item">
                <div class="autocomplete-item-main">
                    <div class="autocomplete-item-title">${med.name}</div>
                    <div class="autocomplete-item-subtitle">
                        ${categoryBadge}
                        ${stockBadge}
                    </div>
                </div>
                <div class="autocomplete-item-price">Rs. ${parseFloat(med.price).toFixed(2)}</div>
            </div>
        `;

        return $("<li>").append(html).appendTo(ul);
    };

    // Enhanced Customer Autocomplete
    $('#customerSearch').autocomplete({
        minLength: 2,
        delay: 300,
        source: function(request, response) {
            const wrapper = $('#customerSearch').closest('.autocomplete-wrapper');
            wrapper.addClass('loading');

            $.ajax({
                url: getApiBaseUrl() + '/api/customers',
                method: 'GET',
                dataType: 'json',
                data: { search: request.term, limit: 20 },
                success: function(data) {
                    wrapper.removeClass('loading');
                    console.log('Customer API Response:', data);
                    if (data.success && data.customers && data.customers.length > 0) {
                        const items = data.customers.map(cust => ({
                            label: cust.name,
                            value: cust.name,
                            data: cust
                        }));
                        response(items);
                    } else {
                        response([{ label: 'No customers found', value: '', data: null }]);
                    }
                },
                error: function(xhr, status, error) {
                    wrapper.removeClass('loading');
                    console.error('Customer API Error:', error);
                    response([{ label: 'Error loading customers', value: '', data: null }]);
                }
            });
        },
        select: function(event, ui) {
            if (!ui.item.data) {
                return false;
            }
            selectCustomer(ui.item.data);
            return true;
        },
        focus: function(event, ui) {
            return false;
        }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
        if (!item.data) {
            return $("<li class='no-results-message'>")
                .append(`<div><i class="fas fa-user-search"></i><div style="margin-top: 8px;">${item.label}</div></div>`)
                .appendTo(ul);
        }

        const cust = item.data;
        const pointsBadge = `<span class="autocomplete-item-badge badge-info"><i class="fas fa-star"></i> ${cust.points || 0} points</span>`;
        const phone = cust.phone ? `<span><i class="fas fa-phone"></i> ${cust.phone}</span>` : '';

        const html = `
            <div class="autocomplete-item">
                <div class="autocomplete-item-main">
                    <div class="autocomplete-item-title">${cust.name}</div>
                    <div class="autocomplete-item-subtitle">
                        ${phone}
                        ${pointsBadge}
                    </div>
                </div>
            </div>
        `;

        return $("<li>").append(html).appendTo(ul);
    };

    // Update preview on input changes
    $('#walkinName, #walkinPhone').on('input', updatePreview);

    // Add keyboard support for quantity input (Enter key to add to cart)
    $('#medicineQuantity').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            addItemToCart();
        }
    });
}

function formatMedicineLabel(med) {
    const stock = med.quantity > 0 ? `<span style="color: #10b981;">In Stock (${med.quantity})</span>` : `<span style="color: #ef4444;">Out of Stock</span>`;
    return `
        <div class="medicine-info">
            <div>
                <div class="medicine-name">${med.name}</div>
                <div class="medicine-details">${med.category} • ${stock}</div>
            </div>
            <div class="medicine-price">Rs. ${parseFloat(med.price).toFixed(2)}</div>
        </div>
    `;
}

function formatCustomerLabel(cust) {
    return `
        <div class="medicine-info">
            <div>
                <div class="medicine-name">${cust.name}</div>
                <div class="medicine-details">${cust.phone || 'No phone'} • ${cust.points || 0} points</div>
            </div>
        </div>
    `;
}

function loadMedicines() {
    $.ajax({
        url: getApiBaseUrl() + '/api/medicines',
        method: 'GET',
        success: function(response) {
            if (response.success && response.medicines) {
                allMedicines = response.medicines;
            }
        },
        error: function(err) {
            console.error('Failed to load medicines:', err);
        }
    });
}

function loadCustomers() {
    $.ajax({
        url: getApiBaseUrl() + '/api/customers',
        method: 'GET',
        success: function(response) {
            if (response.success && response.customers) {
                allCustomers = response.customers;
            }
        },
        error: function(err) {
            console.error('Failed to load customers:', err);
        }
    });
}

function setCustomerType(type) {
    currentCustomerType = type;

    // Update button states
    $('.customer-type-btn').removeClass('active');
    event.target.closest('.customer-type-btn').classList.add('active');

    // Toggle forms
    if (type === 'walk-in') {
        $('#walkinForm').show();
        $('#registeredForm').hide();
        $('#previewType').text('Walk-in Customer');
    } else {
        $('#walkinForm').hide();
        $('#registeredForm').show();
        $('#previewType').text('Registered Customer');
    }

    updatePreview();
}

function addItemToCart() {
    const medicineId = $('#selectedMedicineId').val();
    const medicineName = $('#selectedMedicineName').val(); // Get from hidden field instead
    const price = parseFloat($('#selectedMedicinePrice').val());
    const stock = parseInt($('#selectedMedicineStock').val());
    const quantity = parseInt($('#medicineQuantity').val());

    console.log('Adding to cart:', { medicineId, medicineName, price, stock, quantity });

    if (!medicineId || !medicineName) {
        showToast('⚠️ Please select a medicine from the suggestions', 'error');
        console.error('Missing medicine data:', { medicineId, medicineName });
        return;
    }

    if (isNaN(price) || price <= 0) {
        showToast('⚠️ Invalid medicine price', 'error');
        console.error('Invalid price:', price);
        return;
    }

    if (isNaN(quantity) || quantity < 1) {
        showToast('⚠️ Please enter a valid quantity (minimum 1)', 'error');
        return;
    }

    if (isNaN(stock) || stock < 1) {
        showToast('❌ This medicine is out of stock!', 'error');
        return;
    }

    // Check existing cart quantity
    const existingIndex = cartItems.findIndex(item => item.id === medicineId);
    let totalRequestedQty = quantity;

    if (existingIndex >= 0) {
        totalRequestedQty = cartItems[existingIndex].quantity + quantity;
    }

    // Validate against available stock
    if (totalRequestedQty > stock) {
        showToast(`❌ Insufficient stock! Available: ${stock}, In cart: ${existingIndex >= 0 ? cartItems[existingIndex].quantity : 0}`, 'error');
        return;
    }

    // Add or update item in cart
    if (existingIndex >= 0) {
        cartItems[existingIndex].quantity = totalRequestedQty;
        cartItems[existingIndex].total = totalRequestedQty * cartItems[existingIndex].price;
        showToast(`✅ Updated ${medicineName} quantity to ${totalRequestedQty}`, 'success');
    } else {
        cartItems.push({
            id: medicineId,
            name: medicineName,
            price: price,
            quantity: quantity,
            total: price * quantity,
            maxStock: stock
        });
        showToast(`✅ Added ${quantity} × ${medicineName} to cart`, 'success');
    }

    // Reset inputs
    $('#medicineSearch').val('');
    $('#selectedMedicineId').val('');
    $('#selectedMedicineName').val('');
    $('#selectedMedicinePrice').val('');
    $('#selectedMedicineStock').val('');
    $('#medicineQuantity').val('1');
    $('#medicineQuantity').removeAttr('max');
    $('#selectedMedicineDisplay').slideUp(200);
    $('#medicineSearch').focus();

    updateCart();
}

function removeFromCart(index) {
    const itemName = cartItems[index].name;
    cartItems.splice(index, 1);
    updateCart();
    showToast(`Removed ${itemName} from cart`, 'info');
}

function updateCart() {
    const tbody = $('#cartTableBody');
    const previewBody = $('#previewItems');

    if (cartItems.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" style="text-align: center; color: #64748b; padding: 40px;">
                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                    <p>No items in cart</p>
                </td>
            </tr>
        `);
        previewBody.html(`
            <tr>
                <td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">No items</td>
            </tr>
        `);
    } else {
        tbody.empty();
        previewBody.empty();

        cartItems.forEach((item, index) => {
            tbody.append(`
                <tr>
                    <td>${item.name}</td>
                    <td style="text-align: center;">${item.quantity}</td>
                    <td style="text-align: right;">Rs. ${item.price.toFixed(2)}</td>
                    <td style="text-align: right;">Rs. ${item.total.toFixed(2)}</td>
                    <td>
                        <button class="remove-btn" onclick="removeFromCart(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `);

            previewBody.append(`
                <tr>
                    <td>${item.name}</td>
                    <td style="text-align: center;">${item.quantity}</td>
                    <td style="text-align: right;">Rs. ${item.price.toFixed(2)}</td>
                    <td style="text-align: right;">Rs. ${item.total.toFixed(2)}</td>
                </tr>
            `);
        });
    }

    updateTotals();
}

function updateTotals() {
    const subtotal = cartItems.reduce((sum, item) => sum + item.total, 0);
    const discount = 0; // Can be calculated based on customer loyalty points
    const tax = subtotal * 0.02; // 2% tax
    const grandTotal = subtotal - discount + tax;

    $('#subtotal').text(`Rs. ${subtotal.toFixed(2)}`);
    $('#discount').text(`Rs. ${discount.toFixed(2)}`);
    $('#tax').text(`Rs. ${tax.toFixed(2)}`);
    $('#grandTotal').text(`Rs. ${grandTotal.toFixed(2)}`);

    $('#previewSubtotal').text(`Rs. ${subtotal.toFixed(2)}`);
    $('#previewDiscount').text(`Rs. ${discount.toFixed(2)}`);
    $('#previewTax').text(`Rs. ${tax.toFixed(2)}`);
    $('#previewGrandTotal').text(`Rs. ${grandTotal.toFixed(2)}`);
}

function updatePreview() {
    if (currentCustomerType === 'walk-in') {
        const name = $('#walkinName').val() || '-';
        const phone = $('#walkinPhone').val() || '-';
        $('#previewCustomer').text(name);
        $('#previewPhone').text(phone);
    } else {
        const name = $('#customerDetailName').text() || '-';
        const phone = $('#customerDetailPhone').text() || '-';
        $('#previewCustomer').text(name);
        $('#previewPhone').text(phone);
    }
}

function updatePreviewDate() {
    const now = new Date();
    $('#previewDate').text(now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }));
}

function updatePreviewInvoice() {
    $('#previewInvoiceNo').text(invoiceNumber);
}

function buildBillingPrefillFromOrder(order) {
    const items = Array.isArray(order?.items)
        ? order.items.map(item => {
            const quantity = Number.parseInt(item?.quantity ?? item?.qty ?? 0, 10) || 0;
            const price = Number.parseFloat(item?.price ?? item?.unit_price ?? 0) || 0;
            const total = Number.parseFloat(item?.subtotal ?? item?.total ?? (quantity * price)) || 0;

            return {
                id: item?.medicine_id || item?.id || null,
                name: item?.medicine_name || item?.name || 'N/A',
                quantity,
                price,
                total
            };
        })
        : [];

    const subtotal = items.reduce((sum, item) => sum + item.total, 0);

    return {
        source: 'online-order',
        orderId: order?.id || null,
        orderCode: order?.order_id || order?.order_number || String(order?.id || ''),
        invoiceNumber: `INV-${String(order?.order_id || order?.order_number || Date.now()).replace(/[^a-zA-Z0-9_-]/g, '')}`,
        customer: {
            name: order?.customer_name || '',
            phone: order?.phone || '',
            address: order?.address || ''
        },
        items,
        totals: {
            subtotal,
            total: Number.parseFloat(order?.total_amount || subtotal) || subtotal
        }
    };
}

function applyBillingPrefill(prefill) {
    if (!prefill) {
        return false;
    }

    invoiceNumber = prefill.invoiceNumber || invoiceNumber;
    currentCustomerType = 'walk-in';

    $('.customer-type-btn').removeClass('active');
    $('.customer-type-btn').first().addClass('active');
    $('#walkinForm').show();
    $('#registeredForm').hide();

    $('#walkinName').val(prefill.customer?.name || '');
    $('#walkinPhone').val(prefill.customer?.phone || '');
    $('#walkinAddress').val(prefill.customer?.address || '');
    $('#saveAsCustomer').prop('checked', false);

    cartItems = (prefill.items || []).map(item => ({
        id: item.id,
        name: item.name,
        price: Number.parseFloat(item.price) || 0,
        quantity: Number.parseInt(item.quantity, 10) || 0,
        total: Number.parseFloat(item.total) || 0
    }));

    updateCart();
    updatePreview();
    updatePreviewInvoice();

    return true;
}

async function loadBillingPrefill() {
    const urlParams = new URLSearchParams(window.location.search);
    const autoPrint = urlParams.get('autoprint') === '1';

    let storedPrefill = null;
    try {
        storedPrefill = JSON.parse(sessionStorage.getItem(BILLING_PREFILL_KEY) || 'null');
    } catch (error) {
        console.warn('Unable to parse billing prefill from sessionStorage:', error);
    }

    if (storedPrefill) {
        sessionStorage.removeItem(BILLING_PREFILL_KEY);
        applyBillingPrefill(storedPrefill);

        if (autoPrint) {
            setTimeout(() => printReceipt(), 500);
        }

        return true;
    }

    const orderId = urlParams.get('orderId');
    if (!orderId || typeof api === 'undefined') {
        return false;
    }

    try {
        const response = await api.getOrder(orderId);
        const order = response?.data || response;
        const prefill = buildBillingPrefillFromOrder(order);
        applyBillingPrefill(prefill);

        if (autoPrint) {
            setTimeout(() => printReceipt(), 500);
        }

        return true;
    } catch (error) {
        console.error('Failed to load order for billing prefill:', error);
        return false;
    }
}

function saveBill() {
    if (cartItems.length === 0) {
        showToast('⚠️ Please add items to the cart', 'error');
        return;
    }

    let customerData = {};

    if (currentCustomerType === 'walk-in') {
        const name = $('#walkinName').val();
        const phone = $('#walkinPhone').val();
        const address = $('#walkinAddress').val();
        const saveAsCustomer = $('#saveAsCustomer').is(':checked');

        if (!name) {
            showToast('⚠️ Please enter customer name', 'error');
            return;
        }

        customerData = {
            type: 'walk-in',
            name: name,
            phone: phone || '',
            address: address || '',
            save_as_customer: saveAsCustomer
        };
    } else {
        const customerId = $('#selectedCustomerId').val();
        if (!customerId) {
            showToast('⚠️ Please select a customer', 'error');
            return;
        }

        customerData = {
            type: 'registered',
            customer_id: customerId
        };
    }

    const subtotal = cartItems.reduce((sum, item) => sum + item.total, 0);
    const discount = 0;
    const tax = subtotal * 0.02;
    const total = subtotal - discount + tax;

    const billData = {
        invoice_number: invoiceNumber,
        customer: customerData,
        items: cartItems,
        subtotal: subtotal,
        discount: discount,
        tax: tax,
        total: total
    };

    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    $.ajax({
        url: getApiBaseUrl() + '/api/bills',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(billData),
        success: function(response) {
            console.log('Save response:', response);
            if (response.success) {
                // Update success modal
                $('#successInvoice').text('Invoice: ' + invoiceNumber);
                $('#successTotal').text('Total: Rs. ' + total.toFixed(2));

                // Show success modal
                const modal = new bootstrap.Modal(document.getElementById('successModal'));
                modal.show();

                showToast('✅ Bill saved successfully!', 'success');
            } else {
                showToast('❌ Error: ' + (response.message || 'Failed to save bill'), 'error');
                console.error('Save failed:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Save error:', xhr.responseText);
            showToast('❌ Failed to save bill. Please check console for details.', 'error');
        },
        complete: function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

function closeSuccessModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('successModal'));
    if (modal) {
        modal.hide();
    }
    resetBill();
}

function printReceipt() {
    if (cartItems.length === 0) {
        showToast('⚠️ Please add items to print', 'warning');
        return;
    }
    window.print();
}

function downloadPDF() {
    if (cartItems.length === 0) {
        showToast('⚠️ Please add items to generate PDF', 'warning');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Add logo/header
    doc.setFillColor(102, 126, 234);
    doc.rect(0, 0, 210, 40, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.text('MUNEEB DRUG HOUSE', 105, 20, { align: 'center' });

    doc.setFontSize(10);
    doc.text('Medical Plaza, Main Boulevard', 105, 28, { align: 'center' });
    doc.text('Phone: +92-300-1234567 | Email: info@waqardrughouse.com', 105, 34, { align: 'center' });

    // Reset text color
    doc.setTextColor(0, 0, 0);

    // Bill details
    doc.setFontSize(12);
    doc.text('Invoice No: ' + invoiceNumber, 14, 50);
    doc.text('Date: ' + new Date().toLocaleDateString(), 14, 57);
    doc.text('Customer: ' + $('#previewCustomer').text(), 14, 64);
    doc.text('Phone: ' + $('#previewPhone').text(), 14, 71);
    doc.text('Type: ' + $('#previewType').text(), 14, 78);

    // Items table
    const tableData = cartItems.map(item => [
        item.name,
        item.quantity,
        'Rs. ' + item.price.toFixed(2),
        'Rs. ' + item.total.toFixed(2)
    ]);

    doc.autoTable({
        head: [['Medicine', 'Qty', 'Price', 'Total']],
        body: tableData,
        startY: 85,
        theme: 'grid',
        headStyles: {
            fillColor: [59, 130, 246],
            fontSize: 11,
            fontStyle: 'bold'
        },
        styles: {
            fontSize: 10
        }
    });

    // Totals
    const finalY = doc.lastAutoTable.finalY + 10;
    const subtotal = cartItems.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * 0.02;
    const total = subtotal + tax;

    doc.setFontSize(11);
    doc.text('Subtotal:', 140, finalY);
    doc.text('Rs. ' + subtotal.toFixed(2), 180, finalY, { align: 'right' });

    doc.text('Tax (2%):', 140, finalY + 7);
    doc.text('Rs. ' + tax.toFixed(2), 180, finalY + 7, { align: 'right' });

    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text('Grand Total:', 140, finalY + 17);
    doc.text('Rs. ' + total.toFixed(2), 180, finalY + 17, { align: 'right' });

    // Footer
    doc.setFontSize(9);
    doc.setFont(undefined, 'normal');
    doc.setTextColor(100, 100, 100);
    doc.text('Thank you for choosing Muneeb Drug House!', 105, finalY + 30, { align: 'center' });
    doc.text('Your trusted healthcare partner', 105, finalY + 36, { align: 'center' });

    // Save
    doc.save('Invoice_' + invoiceNumber + '.pdf');
}

function resetBill() {
    cartItems = [];
    invoiceNumber = 'INV-' + Date.now().toString().slice(-8);

    $('#walkinName').val('');
    $('#walkinPhone').val('');
    $('#walkinAddress').val('');
    $('#saveAsCustomer').prop('checked', false);
    $('#customerSearch').val('');
    $('#selectedCustomerId').val('');
    $('#customerDetails').hide();
    $('#medicineSearch').val('');
    $('#selectedMedicineId').val('');
    $('#selectedMedicineName').val('');
    $('#selectedMedicinePrice').val('');
    $('#selectedMedicineStock').val('');
    $('#medicineQuantity').val('1');
    $('#selectedMedicineDisplay').hide();

    updateCart();
    updatePreviewInvoice();
    updatePreview();
}
