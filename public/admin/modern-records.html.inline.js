
let currentBillData = null;
function getApiBaseUrl() {
    const loc = window.location;
    if (loc.port === '8000') {
        return loc.origin;
    }
    if (loc.pathname.includes('/waqar/')) {
        return loc.origin + '/waqar/public';
    }
    return loc.origin;
}

$(document).ready(function() {
    fetch(getApiBaseUrl() + '/api/auth/user', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success && !data.user && !data.username) {
            window.location.href = '../login.html';
            return;
        }

        if (data.user || data.username) {
            const user = data.user || data;
            sessionStorage.setItem('user_role', user.role || 'staff');
        }

        initializeSearch();
        loadRecentBills();
    })
    .catch(error => {
        console.error('Auth check failed:', error);
        window.location.href = '../login.html';
    });
});

function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        sessionStorage.clear();
        localStorage.clear();
        window.location.href = '../login.html';
    }
}

function initializeSearch() {
    let searchTimeout;

    $('#recordSearch').on('input', function() {
        const query = $(this).val().trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            $('#recordsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h5>Search for bills and receipts</h5>
                    <p>Start typing to find past transactions</p>
                </div>
            `);
            return;
        }

        $('.search-box').addClass('loading');

        searchTimeout = setTimeout(() => {
            searchBills(query);
        }, 300);
    });
}

function searchBills(query) {
    $.ajax({
        url: getApiBaseUrl() + '/api/bills',
        method: 'GET',
        data: { search: query },
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        dataType: 'json',
        success: function(response) {
            $('.search-box').removeClass('loading');

            if (response.success && response.data && response.data.length > 0) {
                displayRecords(response.data);
            } else {
                $('#recordsContainer').html(`
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No records found</h5>
                        <p>Try searching with different keywords</p>
                    </div>
                `);
            }
        },
        error: function(err) {
            $('.search-box').removeClass('loading');
            console.error('Search error:', err);
            $('#recordsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Error loading records</h5>
                    <p>Please try again</p>
                </div>
            `);
        }
    });
}

function loadRecentBills() {
    $.ajax({
        url: getApiBaseUrl() + '/api/bills',
        method: 'GET',
        data: { limit: 20 },
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                // Don't display by default, wait for search
            }
        }
    });
}

function displayRecords(bills) {
    let html = '<div class="records-grid">';

    bills.forEach(bill => {
        const customerType = bill.customer_type === 'registered' ? 'Registered' : 'Walk-in';
        const badgeClass = bill.customer_type === 'registered' ? 'badge-registered' : 'badge-walkin';
        const date = new Date(bill.created_at).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        html += `
            <div class="record-card" onclick="viewBillDetail(${bill.id})">
                <div class="record-header">
                    <div class="invoice-number">${bill.invoice_number}</div>
                    <div class="record-amount">Rs. ${parseFloat(bill.total).toFixed(2)}</div>
                </div>
                <div class="record-info">
                    <div class="record-label">Customer</div>
                    <div class="record-value">${bill.customer_name}</div>
                </div>
                ${bill.customer_phone ? `
                <div class="record-info">
                    <div class="record-label">Phone</div>
                    <div class="record-value">${bill.customer_phone}</div>
                </div>
                ` : ''}
                <div class="record-footer">
                    <div class="record-date"><i class="fas fa-clock"></i> ${date}</div>
                    <div class="customer-type-badge ${badgeClass}">${customerType}</div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    $('#recordsContainer').html(html);
}

function viewBillDetail(billId) {
    $.ajax({
        url: `${getApiBaseUrl()}/api/bills/${billId}`,
        method: 'GET',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                currentBillData = response.data;
                displayBillDetail(response.data);
                const modal = new bootstrap.Modal(document.getElementById('billDetailModal'));
                modal.show();
            } else {
                alert('Failed to load bill details');
            }
        },
        error: function(err) {
            console.error('Error loading bill:', err);
            alert('Error loading bill details');
        }
    });
}

function displayBillDetail(bill) {
    const items = JSON.parse(bill.items);
    const date = new Date(bill.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    let itemsHtml = '';
    items.forEach(item => {
        itemsHtml += `
            <tr>
                <td>${item.name}</td>
                <td style="text-align: center;">${item.quantity}</td>
                <td style="text-align: right;">Rs. ${parseFloat(item.price).toFixed(2)}</td>
                <td style="text-align: right;">Rs. ${parseFloat(item.total).toFixed(2)}</td>
            </tr>
        `;
    });

    const html = `
        <div class="bill-header">
            <div class="bill-logo">
                <i class="fas fa-pills"></i>
            </div>
            <h2 style="font-size: 24px; font-weight: 700; margin: 10px 0 5px 0;">Muneeb Drug House</h2>
            <p style="font-size: 12px; color: #64748b;">Medical Plaza, Main Boulevard<br>Phone: +92-300-1234567</p>
        </div>

        <div style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 600; color: #64748b;">Invoice No:</span>
                <span style="color: #1e293b;">${bill.invoice_number}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 600; color: #64748b;">Date:</span>
                <span style="color: #1e293b;">${date}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 600; color: #64748b;">Customer:</span>
                <span style="color: #1e293b;">${bill.customer_name}</span>
            </div>
            ${bill.customer_phone ? `
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 600; color: #64748b;">Phone:</span>
                <span style="color: #1e293b;">${bill.customer_phone}</span>
            </div>
            ` : ''}
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 600; color: #64748b;">Type:</span>
                <span style="color: #1e293b;">${bill.customer_type === 'registered' ? 'Registered Customer' : 'Walk-in Customer'}</span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>

        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>Rs. ${parseFloat(bill.subtotal).toFixed(2)}</span>
            </div>
            <div class="total-row">
                <span>Discount:</span>
                <span>Rs. ${parseFloat(bill.discount).toFixed(2)}</span>
            </div>
            <div class="total-row">
                <span>Tax (2%):</span>
                <span>Rs. ${parseFloat(bill.tax).toFixed(2)}</span>
            </div>
            <div class="total-row grand-total">
                <span>Grand Total:</span>
                <span>Rs. ${parseFloat(bill.total).toFixed(2)}</span>
            </div>
        </div>

        <div class="action-buttons">
            <button class="btn-action btn-print" onclick="printBill()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn-action btn-pdf" onclick="downloadBillPDF()">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px dashed #e2e8f0;">
            <p style="font-size: 13px; color: #64748b; margin: 0;">Thank you for choosing Muneeb Drug House!</p>
        </div>
    `;

    $('#billDetailContent').html(html);
}

function printBill() {
    const printContent = document.getElementById('billDetailContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Bill - ${currentBillData.invoice_number}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { padding: 10px; border: 1px solid #ddd; }
                .items-table th { background: #f5f5f5; }
                .totals-section { background: #f9f9f9; padding: 15px; margin: 20px 0; }
                .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
                .grand-total { font-size: 20px; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
                .action-buttons { display: none; }
            </style>
        </head>
        <body>${printContent}</body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

function downloadBillPDF() {
    if (!currentBillData) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const items = JSON.parse(currentBillData.items);

    // Header
    doc.setFillColor(102, 126, 234);
    doc.rect(0, 0, 210, 40, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.text('MUNEEB DRUG HOUSE', 105, 20, { align: 'center' });

    doc.setFontSize(10);
    doc.text('Medical Plaza, Main Boulevard', 105, 28, { align: 'center' });
    doc.text('Phone: +92-300-1234567', 105, 34, { align: 'center' });

    doc.setTextColor(0, 0, 0);
    doc.setFontSize(12);
    doc.text('Invoice: ' + currentBillData.invoice_number, 14, 50);
    doc.text('Customer: ' + currentBillData.customer_name, 14, 57);
    if (currentBillData.customer_phone) {
        doc.text('Phone: ' + currentBillData.customer_phone, 14, 64);
    }

    // Items table
    const tableData = items.map(item => [
        item.name,
        item.quantity,
        'Rs. ' + parseFloat(item.price).toFixed(2),
        'Rs. ' + parseFloat(item.total).toFixed(2)
    ]);

    doc.autoTable({
        head: [['Medicine', 'Qty', 'Price', 'Total']],
        body: tableData,
        startY: 75,
        theme: 'grid'
    });

    // Totals
    const finalY = doc.lastAutoTable.finalY + 10;
    doc.setFontSize(11);
    doc.text('Subtotal: Rs. ' + parseFloat(currentBillData.subtotal).toFixed(2), 140, finalY);
    doc.text('Tax: Rs. ' + parseFloat(currentBillData.tax).toFixed(2), 140, finalY + 7);
    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text('Total: Rs. ' + parseFloat(currentBillData.total).toFixed(2), 140, finalY + 17);

    doc.save('Bill_' + currentBillData.invoice_number + '.pdf');
}
</script>

<script src="../shared/js/sidebar-loader.js">