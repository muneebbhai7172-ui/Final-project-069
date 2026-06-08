<?php
/**
 * Path Configuration
 * Centralized path definitions for the application
 */

// Base paths
define('BASE_PATH', dirname(dirname(__FILE__)));
define('ROOT_URL', '/waqar');

// Module paths
define('MODULES_PATH', BASE_PATH . '/modules');
define('DASHBOARD_PATH', MODULES_PATH . '/dashboard');
define('MEDICINES_PATH', MODULES_PATH . '/medicines');
define('ORDERS_PATH', MODULES_PATH . '/orders');
define('CUSTOMERS_PATH', MODULES_PATH . '/customers');
define('BILLING_PATH', MODULES_PATH . '/billing');
define('STOCK_PATH', MODULES_PATH . '/stock');
define('REPORTS_PATH', MODULES_PATH . '/reports');
define('SETTINGS_PATH', MODULES_PATH . '/settings');
define('BACKUP_PATH', MODULES_PATH . '/backup');

// Shared resources paths
define('SHARED_PATH', BASE_PATH . '/shared');
define('SHARED_CSS_PATH', SHARED_PATH . '/css');
define('SHARED_JS_PATH', SHARED_PATH . '/js');
define('SHARED_COMPONENTS_PATH', SHARED_PATH . '/components');

// URLs for resources
define('SHARED_CSS_URL', ROOT_URL . '/shared/css');
define('SHARED_JS_URL', ROOT_URL . '/shared/js');
define('SHARED_COMPONENTS_URL', ROOT_URL . '/shared/components');

// Module URLs
define('DASHBOARD_URL', ROOT_URL . '/modules/dashboard');
define('MEDICINES_URL', ROOT_URL . '/modules/medicines');
define('ORDERS_URL', ROOT_URL . '/modules/orders');
define('CUSTOMERS_URL', ROOT_URL . '/modules/customers');
define('BILLING_URL', ROOT_URL . '/modules/billing');
define('STOCK_URL', ROOT_URL . '/modules/stock');
define('REPORTS_URL', ROOT_URL . '/modules/reports');
define('SETTINGS_URL', ROOT_URL . '/modules/settings');
define('BACKUP_URL', ROOT_URL . '/modules/backup');

// Other paths
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('IMAGES_PATH', BASE_PATH . '/images');
define('BACKUPS_PATH', BASE_PATH . '/backups');

// Helper function to get module URL
function getModuleUrl($module) {
    return ROOT_URL . '/modules/' . $module;
}

// Helper function to get shared resource URL
function getSharedUrl($type, $file = '') {
    $base = ROOT_URL . '/shared/' . $type;
    return $file ? $base . '/' . $file : $base;
}

// Helper function for relative path calculation
function getRelativePath($from, $to) {
    $from = explode('/', $from);
    $to = explode('/', $to);
    
    $relPath = '';
    $i = 0;
    
    // Find common base
    while (isset($from[$i]) && isset($to[$i]) && $from[$i] == $to[$i]) {
        $i++;
    }
    
    // Add ../  for each remaining directory in $from
    $j = count($from) - 1;
    while ($i <= $j) {
        if (!empty($from[$j])) {
            $relPath .= '../';
        }
        $j--;
    }
    
    // Add path parts from $to
    while ($i < count($to)) {
        if (!empty($to[$i])) {
            $relPath .= $to[$i] . '/';
        }
        $i++;
    }
    
    return rtrim($relPath, '/');
}
?>
