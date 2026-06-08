<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\MedicineAIController;
use App\Http\Controllers\AdminMedicineAIController;

Route::get('/', function () {
    return redirect('/modules/frontend/index.html');
});

// Waqar-prefixed routes (for XAMPP compatibility)
Route::get('/waqar/{path}', function ($path) {
    $filePath = public_path($path);
    if (file_exists($filePath)) {
        return response()->file($filePath);
    }
    abort(404);
})->where('path', '.*');

Route::get('/login', function () {
    return response()->file(public_path('modules/auth/login.html'));
});

Route::get('/register', function () {
    return response()->file(public_path('modules/auth/register.html'));
});

Route::get('/dashboard', function () {
    return response()->file(public_path('modules/dashboard/index.html'));
});

Route::get('/customers', function () {
    return response()->file(public_path('modules/customers/index.html'));
});

Route::get('/medicines', function () {
    return response()->file(public_path('modules/medicines/index.html'));
});

// Serve static HTML files from public/modules
Route::get('/modules/medicines/index.html', function () {
    return response()->file(public_path('modules/medicines/index.html'));
});

Route::get('/modules/customers/index.html', function () {
    return response()->file(public_path('modules/customers/index.html'));
});

Route::get('/modules/orders/index.html', function () {
    return response()->file(public_path('modules/orders/index.html'));
});

Route::get('/modules/billing/index.html', function () {
    return response()->file(public_path('modules/billing/index.html'));
});

Route::get('/modules/stock/index.html', function () {
    return response()->file(public_path('modules/stock/index.html'));
});

Route::get('/modules/reports/index.html', function () {
    return response()->file(public_path('modules/reports/index.html'));
});

Route::get('/modules/records/index.html', function () {
    return response()->file(public_path('modules/records/index.html'));
});

Route::get('/modules/backup/index.html', function () {
    return response()->file(public_path('modules/backup/index.html'));
});

Route::get('/modules/settings/index.html', function () {
    return response()->file(public_path('modules/settings/index.html'));
});

Route::get('/modules/dashboard/index.html', function () {
    return response()->file(public_path('modules/dashboard/index.html'));
});

Route::get('/orders', function () {
    return response()->file(public_path('modules/orders/index.html'));
});

Route::get('/stock', function () {
    return response()->file(public_path('modules/stock/index.html'));
});

Route::get('/reports', function () {
    return response()->file(public_path('modules/reports/index.html'));
});

Route::get('/settings', function () {
    return response()->file(public_path('modules/settings/index.html'));
});

Route::get('/billing', function () {
    return response()->file(public_path('modules/billing/index.html'));
});

// Admin pages from admin folder
Route::get('/admin/billing', function () {
    return response()->file(public_path('admin/modern-billing.html'));
});

Route::get('/admin/customers', function () {
    return response()->file(public_path('admin/modern-customers.html'));
});

Route::get('/admin/records', function () {
    return response()->file(public_path('admin/modern-records.html'));
});

Route::get('/admin/reports', function () {
    return response()->file(public_path('admin/modern-reports.html'));
});

Route::get('/admin/settings', function () {
    return response()->file(public_path('admin/settings.html'));
});

Route::get('/admin/stock', function () {
    return response()->file(public_path('admin/stock-check.html'));
});

Route::get('/admin/medicine-import', function () {
    return response()->file(public_path('admin/medicine-import.html'));
});

// Shop/Frontend pages
Route::get('/shop', function () {
    return response()->file(public_path('modules/frontend/index.html'));
});

Route::get('/shop/search', function () {
    return response()->file(public_path('modules/shop/search.html'));
});

Route::get('/shop/cart', function () {
    return response()->file(public_path('modules/shop/cart.html'));
});

Route::get('/shop/medicine/{id}', function () {
    return response()->file(public_path('modules/shop/medicine-detail.html'));
});

Route::get('/shop/track-order', function () {
    return response()->file(public_path('modules/shop/track-order.html'));
});

// Auth Routes - Clean Laravel API style (with explicit web middleware for sessions)
Route::middleware('web')->group(function () {
    Route::post('/api/auth/login', [AuthController::class, 'login']);
    Route::post('/api/auth/register', [AuthController::class, 'register']);
    Route::post('/api/auth/logout', [AuthController::class, 'logout']);
    Route::get('/api/auth/check', [AuthController::class, 'check']);
    Route::get('/api/auth/user', [AuthController::class, 'user']);
    Route::post('/api/auth/unlock-account', [AuthController::class, 'unlockAccount']);
    Route::post('/api/auth/generate-license', [AuthController::class, 'generateLicenseKey']);

    // 2FA Routes
    Route::post('/api/auth/2fa-login', [AuthController::class, 'verify2FALogin']);
    Route::get('/api/auth/2fa-status', [AuthController::class, 'get2FAStatus']);
    Route::post('/api/auth/2fa-setup', [AuthController::class, 'setup2FA']);
    Route::post('/api/auth/2fa-verify', [AuthController::class, 'verify2FA']);
    Route::post('/api/auth/2fa-disable', [AuthController::class, 'disable2FA']);

    // Profile Routes
    Route::post('/api/auth/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/api/auth/change-password', [AuthController::class, 'changePassword']);
});

// Legacy routes for backward compatibility
Route::post('/modules/auth/api/login-api.php', [AuthController::class, 'login']);
Route::post('/modules/auth/api/register-api.php', [AuthController::class, 'register']);
Route::post('/modules/auth/api/logout-api.php', [AuthController::class, 'logout']);
Route::get('/modules/auth/api/check-api.php', [AuthController::class, 'check']);
Route::get('/modules/auth/api/user-api.php', [AuthController::class, 'user']);
Route::post('/modules/auth/api/unlock-account-api.php', [AuthController::class, 'unlockAccount']);

// Medicine Routes - Clean Laravel API style
Route::get('/api/medicines', [MedicineController::class, 'index']);
Route::get('/api/medicines/stock', [MedicineController::class, 'index']); // Stock page route
Route::delete('/api/medicines/delete-all', [MedicineController::class, 'deleteAll']);
Route::get('/api/medicines/suggestions/search', [MedicineController::class, 'suggestions']);
Route::get('/api/medicines/export/download', [MedicineController::class, 'export']);
Route::post('/api/medicines/import', [MedicineController::class, 'import']);
Route::post('/api/medicines/import/upload', [MedicineController::class, 'import']);
Route::post('/api/medicines/bulk-import', [MedicineController::class, 'import']);
Route::get('/api/medicines/low-stock/list', [MedicineController::class, 'lowStock']);
Route::get('/api/medicines/expiring/list', [MedicineController::class, 'expiring']);
Route::get('/api/medicines/{id}', [MedicineController::class, 'show']);
Route::post('/api/medicines', [MedicineController::class, 'store']);
Route::put('/api/medicines/{id}', [MedicineController::class, 'update']);
Route::delete('/api/medicines/{id}', [MedicineController::class, 'destroy']);

// Legacy routes
Route::get('/modules/medicines/api.php', [MedicineController::class, 'index']);
Route::get('/modules/medicines/api.php/{id}', [MedicineController::class, 'show']);
Route::post('/modules/medicines/api.php', [MedicineController::class, 'store']);
Route::put('/modules/medicines/api.php/{id}', [MedicineController::class, 'update']);
Route::delete('/modules/medicines/api.php/{id}', [MedicineController::class, 'destroy']);
Route::get('/modules/medicines/suggestions.php', [MedicineController::class, 'suggestions']);
Route::get('/modules/medicines/suggestions-api.php', [MedicineController::class, 'suggestions']);
Route::get('/modules/medicines/export.php', [MedicineController::class, 'export']);
Route::post('/modules/medicines/import.php', [MedicineController::class, 'import']);
Route::get('/modules/medicines/low-stock.php', [MedicineController::class, 'lowStock']);
Route::get('/modules/medicines/expiring.php', [MedicineController::class, 'expiring']);

// Customer Routes - Clean Laravel API style
Route::get('/api/customers', [CustomerController::class, 'index']);
Route::get('/api/customers/{id}', [CustomerController::class, 'show']);
Route::post('/api/customers', [CustomerController::class, 'store']);
Route::put('/api/customers/{id}', [CustomerController::class, 'update']);
Route::delete('/api/customers/{id}', [CustomerController::class, 'destroy']);
Route::delete('/api/customers', [CustomerController::class, 'deleteAll']);
Route::post('/api/customers/import/json', [CustomerController::class, 'importJson']);
Route::get('/api/customers/find-by-phone/{phone}', [CustomerController::class, 'findByPhone']);
Route::get('/api/customers/export/download', [CustomerController::class, 'export']);
Route::post('/api/customers/import/upload', [CustomerController::class, 'import']);

// Order Routes - Clean Laravel API style
Route::get('/api/orders', [OrderController::class, 'index']);
Route::get('/api/orders/customer/{email}', [OrderController::class, 'customerOrders']);
Route::get('/api/orders/{id}', [OrderController::class, 'show']);
Route::post('/api/orders', [OrderController::class, 'store']);
Route::put('/api/orders/{id}', [OrderController::class, 'update']);
Route::put('/api/orders/{id}/status', [OrderController::class, 'updateStatus']);
Route::delete('/api/orders/{id}', [OrderController::class, 'destroy']);
Route::delete('/api/orders', [OrderController::class, 'deleteAll']);
Route::get('/api/orders/track/{tracking}', [OrderController::class, 'track']);

// Billing Routes - Clean Laravel API style
Route::get('/api/bills', [BillingController::class, 'index']);
Route::get('/api/bills/search', [BillingController::class, 'search']);
Route::get('/api/bills/{id}', [BillingController::class, 'show']);
Route::post('/api/bills', [BillingController::class, 'store']);

// Records Routes (alias to bills)
Route::get('/api/records', [BillingController::class, 'index']);
Route::get('/api/records/{id}', [BillingController::class, 'show']);
Route::post('/api/records', [BillingController::class, 'store']);

// Stock Routes - Clean Laravel API style
Route::get('/api/stock', [StockController::class, 'index']);
Route::put('/api/stock/{id}', [StockController::class, 'update']);
Route::get('/api/stock/low-stock', [StockController::class, 'lowStock']);
Route::get('/api/stock/export', [StockController::class, 'export']);

// Legacy stock routes
Route::get('/modules/stock/api.php', [StockController::class, 'index']);
Route::put('/modules/stock/api.php/{id}', [StockController::class, 'update']);
Route::get('/modules/stock/low-stock.php', [StockController::class, 'lowStock']);
Route::get('/modules/stock/export.php', [StockController::class, 'export']);

// Dashboard Routes - Clean Laravel API style
Route::get('/api/dashboard/stats', [DashboardController::class, 'stats']);
Route::get('/api/dashboard/recent-sales', [DashboardController::class, 'recentSales']);
Route::get('/api/dashboard/low-stock', [DashboardController::class, 'lowStock']);
Route::get('/api/dashboard/sales-chart', [DashboardController::class, 'salesChart']);
Route::get('/api/dashboard/top-medicines', [DashboardController::class, 'topMedicines']);

// Legacy dashboard routes
Route::get('/modules/dashboard/stats.php', [DashboardController::class, 'stats']);
Route::get('/modules/dashboard/recent-sales.php', [DashboardController::class, 'recentSales']);
Route::get('/modules/dashboard/low-stock.php', [DashboardController::class, 'lowStock']);
Route::get('/modules/dashboard/sales-chart.php', [DashboardController::class, 'salesChart']);
Route::get('/modules/dashboard/top-medicines.php', [DashboardController::class, 'topMedicines']);

// Report Routes - Clean Laravel API style
Route::get('/api/reports/sales', [ReportController::class, 'sales']);
Route::get('/api/reports/inventory', [ReportController::class, 'inventory']);
Route::get('/api/reports/customers', [ReportController::class, 'customers']);
Route::get('/api/reports/export', [ReportController::class, 'export']);

// Legacy report routes
Route::get('/modules/reports/api.php', [ReportController::class, 'sales']);
Route::get('/modules/reports/sales.php', [ReportController::class, 'sales']);
Route::get('/modules/reports/inventory.php', [ReportController::class, 'inventory']);
Route::get('/modules/reports/customers.php', [ReportController::class, 'customers']);
Route::get('/modules/reports/export.php', [ReportController::class, 'export']);

// Settings Routes - Clean Laravel API style
Route::get('/api/settings', [SettingsController::class, 'index']);
Route::put('/api/settings', [SettingsController::class, 'update']);
Route::post('/api/settings/bulk-update', [SettingsController::class, 'bulkUpdate']);

// User Management Routes
Route::get('/api/settings/users', [SettingsController::class, 'getUsers']);
Route::get('/api/settings/users/{id}', [SettingsController::class, 'getUser']);
Route::post('/api/settings/users', [SettingsController::class, 'createUser']);
Route::put('/api/settings/users/{id}', [SettingsController::class, 'updateUser']);
Route::delete('/api/settings/users/{id}', [SettingsController::class, 'deleteUser']);
Route::post('/api/settings/profile', [SettingsController::class, 'updateProfile']);
Route::post('/api/settings/password', [SettingsController::class, 'changePassword']);
Route::post('/api/settings/store', [SettingsController::class, 'updateStoreInfo']);
Route::post('/api/settings/system', [SettingsController::class, 'updateSystemSettings']);

// Legacy settings routes
Route::get('/modules/settings/api.php', [SettingsController::class, 'index']);
Route::put('/modules/settings/api.php', [SettingsController::class, 'update']);
Route::post('/modules/settings/bulk-update.php', [SettingsController::class, 'bulkUpdate']);

// Backup Routes - Clean Laravel API style
Route::post('/api/backup/create', [BackupController::class, 'create']);
Route::get('/api/backup/list', [BackupController::class, 'list']);
Route::get('/api/backup/download/{filename}', [BackupController::class, 'download']);
Route::delete('/api/backup/{filename}', [BackupController::class, 'delete']);

// Legacy backup routes
Route::post('/modules/backup/create.php', [BackupController::class, 'create']);
Route::get('/modules/backup/list.php', [BackupController::class, 'list']);
Route::get('/modules/backup/download.php/{filename}', [BackupController::class, 'download']);
Route::delete('/modules/backup/delete.php/{filename}', [BackupController::class, 'delete']);

// Medicine AI Chatbot Routes - Customer facing
Route::get('/api/medicine-ai/status', [MedicineAIController::class, 'status']);
Route::get('/api/medicine-ai/suggestions', [MedicineAIController::class, 'getSuggestions']);
Route::post('/api/medicine-ai/chat', [MedicineAIController::class, 'chat']);

// Medicine AI Admin Routes - Advanced queries
Route::get('/api/admin/medicine-ai/prompts', [AdminMedicineAIController::class, 'getPromptTypes']);
Route::post('/api/admin/medicine-ai/query', [AdminMedicineAIController::class, 'query']);
Route::post('/api/admin/medicine-ai/compare', [AdminMedicineAIController::class, 'compare']);
Route::post('/api/admin/medicine-ai/batch', [AdminMedicineAIController::class, 'batchQuery']);
Route::post('/api/admin/medicine-ai/export', [AdminMedicineAIController::class, 'export']);
Route::delete('/api/admin/medicine-ai/cache', [AdminMedicineAIController::class, 'clearCache']);

// Admin AI Intelligence page route
Route::get('/admin/medicine-ai', function () {
    return response()->file(public_path('admin/medicine-ai.html'));
});
