/**
 * Admin Page Template Update Script
 * This script updates all admin pages to use the centralized sidebar component
 */

// Function to update an admin page
function updateAdminPage(filename) {
    const pageHead = `    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Template Loader -->
    <script src="../js/template-loader.js"></script>
    <script src="../js/admin-common.js"></script>
</head>
<body class="bg-light">

<div class="d-flex">
    <!-- Sidebar will be loaded dynamically by template-loader.js -->

    <!-- Main Content -->
    <div class="admin-content flex-grow-1" style="margin-left: 300px; min-height: 100vh;">`;

    const pageEnd = `
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/utils.js"></script>
</body>
</html>`;

    console.log(`Template for updating ${filename}:`);
    console.log('Replace the head section ending and body beginning with:');
    console.log(pageHead);
    console.log('\nReplace the scripts section with:');
    console.log(pageEnd);
}

// List of admin pages to update
const adminPages = [
    'billing.html',
    'customers.html',
    'medicine-search.html',
    'orders.html',
    'reports.html',
    'stock-check.html'
];

console.log('Admin Page Update Guide:');
console.log('========================');
adminPages.forEach(page => {
    console.log(`\n--- ${page} ---`);
    updateAdminPage(page);
});