<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/database.php';

try {
    $type = $_GET['type'] ?? 'sales';
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    $group = $_GET['group'] ?? 'daily';

    $response = ['success' => true, 'data' => []];

    switch ($type) {
        case 'sales':
            $response['data'] = generateSalesReport($pdo, $from, $to, $group);
            break;
        case 'stock':
        case 'inventory':
            $response['data'] = generateStockReport($pdo);
            break;
        case 'expiry':
            $response['data'] = generateExpiryReport($pdo);
            break;
        case 'customers':
        case 'customer':
            $response['data'] = generateCustomerReport($pdo, $from, $to);
            break;
        case 'bills':
            $response['data'] = generateBillsReport($pdo, $from, $to, $group);
            break;
        default:
            throw new Exception('Invalid report type');
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

function generateSalesReport($pdo, $from, $to, $group) {
    // Get date format based on grouping
    $dateFormat = getDateFormat($group);
    $groupField = getGroupField($group);

    // Get sales data
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(created_at, '$dateFormat') as period,
            COUNT(*) as orders,
            SUM(total_amount) as revenue,
            AVG(total_amount) as avg_order
        FROM orders
        WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY $groupField
        ORDER BY $groupField
    ");
    $stmt->execute([$from, $to]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary
    $stmt = $pdo->prepare("
        SELECT
            SUM(total_amount) as total_sales,
            COUNT(*) as total_orders,
            AVG(total_amount) as avg_order_value,
            DATE_FORMAT(created_at, '%Y-%m-%d') as best_day
        FROM orders
        WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY total_sales DESC
        LIMIT 1
    ");
    $stmt->execute([$from, $to]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_sales' => 0,
        'total_orders' => 0,
        'avg_order_value' => 0,
        'best_day' => 'N/A'
    ];

    return [
        'summary' => $summary,
        'details' => array_map(function($item) {
            return [
                'date' => $item['period'],
                'orders' => (int)$item['orders'],
                'revenue' => (float)$item['revenue'],
                'avg_order' => (float)$item['avg_order']
            ];
        }, $sales),
        'chart' => [
            'labels' => array_column($sales, 'period'),
            'values' => array_map('floatval', array_column($sales, 'revenue'))
        ],
        'distribution' => [
            'labels' => ['Completed Orders', 'Pending Orders'],
            'values' => [count($sales), 0] // Simplified
        ]
    ];
}

function generateStockReport($pdo) {
    // Get ALL medicines with detailed stock information
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            category,
            manufacturer,
            batch_number,
            quantity,
            price,
            (quantity * price) as stock_value,
            expiry_date,
            CASE
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity < 20 THEN 'Low Stock'
                WHEN quantity <= 100 THEN 'Medium Stock'
                ELSE 'High Stock'
            END as stock_status,
            CASE
                WHEN expiry_date IS NULL THEN 'No Expiry'
                WHEN expiry_date < CURDATE() THEN 'Expired'
                WHEN DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 'Expiring Soon'
                ELSE 'Valid'
            END as expiry_status
        FROM medicines
        ORDER BY
            CASE
                WHEN quantity <= 0 THEN 1
                WHEN quantity < 20 THEN 2
                WHEN quantity <= 100 THEN 3
                ELSE 4
            END,
            name
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_medicines FROM medicines");
    $totalMedicines = $stmt->fetch(PDO::FETCH_ASSOC)['total_medicines'];

    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM medicines WHERE quantity > 0 AND quantity < 20");
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];

    $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM medicines WHERE quantity <= 0");
    $outOfStock = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'];

    $stmt = $pdo->query("SELECT SUM(quantity * price) as total_value FROM medicines");
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?: 0;

    $stmt = $pdo->query("SELECT SUM(quantity) as total_quantity FROM medicines");
    $totalQuantity = $stmt->fetch(PDO::FETCH_ASSOC)['total_quantity'] ?: 0;

    // Category-wise distribution
    $stmt = $pdo->query("
        SELECT 
            category, 
            COUNT(*) as count,
            SUM(quantity) as total_qty,
            SUM(quantity * price) as category_value
        FROM medicines
        GROUP BY category
        ORDER BY category_value DESC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stock status distribution
    $stmt = $pdo->query("
        SELECT 
            CASE
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity < 20 THEN 'Low Stock'
                WHEN quantity <= 100 THEN 'Medium Stock'
                ELSE 'High Stock'
            END as status,
            COUNT(*) as count
        FROM medicines
        GROUP BY status
    ");
    $stockDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_medicines' => $totalMedicines,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => (float)$totalValue,
            'total_quantity' => (int)$totalQuantity
        ],
        'details' => array_map(function($item) {
            return [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'category' => $item['category'] ?: 'N/A',
                'manufacturer' => $item['manufacturer'] ?: 'N/A',
                'batch_number' => $item['batch_number'] ?: 'N/A',
                'quantity' => (int)$item['quantity'],
                'price' => (float)$item['price'],
                'stock_value' => (float)$item['stock_value'],
                'expiry_date' => $item['expiry_date'] ?: 'N/A',
                'stock_status' => $item['stock_status'],
                'expiry_status' => $item['expiry_status']
            ];
        }, $medicines),
        'categories' => array_map(function($item) {
            return [
                'category' => $item['category'],
                'count' => (int)$item['count'],
                'total_quantity' => (int)$item['total_qty'],
                'value' => (float)$item['category_value']
            ];
        }, $categories),
        'distribution' => [
            'labels' => array_column($stockDistribution, 'status'),
            'values' => array_map('intval', array_column($stockDistribution, 'count'))
        ]
    ];
}

function generateExpiryReport($pdo) {
    // Get medicines that are expired or expiring soon
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            category,
            manufacturer,
            batch_number,
            quantity,
            price,
            (quantity * price) as stock_value,
            expiry_date,
            DATEDIFF(expiry_date, CURDATE()) as days_to_expiry,
            CASE
                WHEN expiry_date < CURDATE() THEN 'Expired'
                WHEN DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 'Expires in 30 days'
                WHEN DATEDIFF(expiry_date, CURDATE()) <= 60 THEN 'Expires in 60 days'
                WHEN DATEDIFF(expiry_date, CURDATE()) <= 90 THEN 'Expires in 90 days'
                ELSE 'Valid'
            END as expiry_status
        FROM medicines
        WHERE expiry_date IS NOT NULL
        AND (
            expiry_date < CURDATE()
            OR DATEDIFF(expiry_date, CURDATE()) <= 90
        )
        ORDER BY expiry_date ASC
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as expired FROM medicines WHERE expiry_date < CURDATE()");
    $expired = $stmt->fetch(PDO::FETCH_ASSOC)['expired'];

    $stmt = $pdo->query("SELECT COUNT(*) as expiring_30 FROM medicines WHERE DATEDIFF(expiry_date, CURDATE()) BETWEEN 0 AND 30");
    $expiring30 = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_30'];

    $stmt = $pdo->query("SELECT COUNT(*) as expiring_60 FROM medicines WHERE DATEDIFF(expiry_date, CURDATE()) BETWEEN 31 AND 60");
    $expiring60 = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_60'];

    $stmt = $pdo->query("SELECT COUNT(*) as expiring_90 FROM medicines WHERE DATEDIFF(expiry_date, CURDATE()) BETWEEN 61 AND 90");
    $expiring90 = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_90'];

    // Calculate potential loss value
    $stmt = $pdo->query("SELECT SUM(quantity * price) as expired_value FROM medicines WHERE expiry_date < CURDATE()");
    $expiredValue = $stmt->fetch(PDO::FETCH_ASSOC)['expired_value'] ?: 0;

    $stmt = $pdo->query("SELECT SUM(quantity * price) as expiring_value FROM medicines WHERE DATEDIFF(expiry_date, CURDATE()) BETWEEN 0 AND 90");
    $expiringValue = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_value'] ?: 0;

    // Category-wise expiry distribution
    $stmt = $pdo->query("
        SELECT 
            category,
            COUNT(*) as count,
            SUM(quantity) as total_qty,
            SUM(quantity * price) as value
        FROM medicines
        WHERE expiry_date IS NOT NULL
        AND (expiry_date < CURDATE() OR DATEDIFF(expiry_date, CURDATE()) <= 90)
        GROUP BY category
        ORDER BY value DESC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Time-based distribution
    $expiryDistribution = [
        ['status' => 'Expired', 'count' => $expired],
        ['status' => 'Expires in 30 days', 'count' => $expiring30],
        ['status' => 'Expires in 60 days', 'count' => $expiring60],
        ['status' => 'Expires in 90 days', 'count' => $expiring90]
    ];

    return [
        'summary' => [
            'expired' => $expired,
            'expiring_30_days' => $expiring30,
            'expiring_60_days' => $expiring60,
            'expiring_90_days' => $expiring90,
            'total_affected' => $expired + $expiring30 + $expiring60 + $expiring90,
            'expired_value' => (float)$expiredValue,
            'expiring_value' => (float)$expiringValue,
            'total_potential_loss' => (float)($expiredValue + $expiringValue)
        ],
        'details' => array_map(function($item) {
            return [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'category' => $item['category'] ?: 'N/A',
                'manufacturer' => $item['manufacturer'] ?: 'N/A',
                'batch_number' => $item['batch_number'] ?: 'N/A',
                'quantity' => (int)$item['quantity'],
                'price' => (float)$item['price'],
                'stock_value' => (float)$item['stock_value'],
                'expiry_date' => $item['expiry_date'],
                'days_to_expiry' => (int)$item['days_to_expiry'],
                'expiry_status' => $item['expiry_status']
            ];
        }, $medicines),
        'categories' => array_map(function($item) {
            return [
                'category' => $item['category'],
                'count' => (int)$item['count'],
                'total_quantity' => (int)$item['total_qty'],
                'value' => (float)$item['value']
            ];
        }, $categories),
        'distribution' => [
            'labels' => array_column($expiryDistribution, 'status'),
            'values' => array_map('intval', array_column($expiryDistribution, 'count'))
        ]
    ];
}

function generateInventoryReport($pdo) {
    // Get inventory data
    $stmt = $pdo->query("
        SELECT
            name,
            category,
            quantity,
            price,
            (quantity * price) as value,
            CASE
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity < 10 THEN 'Low Stock'
                ELSE 'In Stock'
            END as status
        FROM medicines
        ORDER BY
            CASE
                WHEN quantity <= 0 THEN 1
                WHEN quantity < 10 THEN 2
                ELSE 3
            END,
            name
    ");
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary
    $stmt = $pdo->query("SELECT COUNT(*) as total_items FROM medicines");
    $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total_items'];

    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM medicines WHERE quantity > 0 AND quantity < 10");
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];

    $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM medicines WHERE quantity <= 0");
    $outOfStock = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'];

    $stmt = $pdo->query("SELECT SUM(quantity * price) as total_value FROM medicines");
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?: 0;

    // Category distribution
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as count
        FROM medicines
        GROUP BY category
        ORDER BY count DESC
        LIMIT 5
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_items' => $totalItems,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => (float)$totalValue
        ],
        'details' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'category' => $item['category'],
                'quantity' => (int)$item['quantity'],
                'value' => (float)$item['value'],
                'status' => $item['status']
            ];
        }, $inventory),
        'chart' => [
            'labels' => array_column($inventory, 'name'),
            'values' => array_map('floatval', array_column($inventory, 'value'))
        ],
        'distribution' => [
            'labels' => array_column($categories, 'category'),
            'values' => array_map('intval', array_column($categories, 'count'))
        ]
    ];
}

function generateCustomerReport($pdo, $from, $to) {
    // Get top customers by spending/points
    $stmt = $pdo->prepare("
        SELECT
            c.name,
            c.phone,
            c.points,
            DATE(c.created_at) as joined,
            CASE
                WHEN c.points >= 50 THEN 'VIP'
                WHEN c.points >= 25 THEN 'Regular'
                ELSE 'New'
            END as status,
            COALESCE(SUM(b.total), 0) as total_spent
        FROM customers c
        LEFT JOIN bills_backup b ON c.name = b.customer_name AND DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.name, c.phone, c.points, c.created_at
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$from, $to]);
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all customers for the date range (for details table)
    $stmt = $pdo->prepare("
        SELECT
            c.name,
            c.phone,
            c.points,
            DATE(c.created_at) as joined,
            CASE
                WHEN c.points >= 50 THEN 'VIP'
                WHEN c.points >= 25 THEN 'Regular'
                ELSE 'New'
            END as status,
            COALESCE(SUM(b.total), 0) as total_spent,
            COUNT(b.id) as total_purchases
        FROM customers c
        LEFT JOIN bills_backup b ON c.name = b.customer_name AND DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.name, c.phone, c.points, c.created_at
        ORDER BY total_spent DESC
    ");
    $stmt->execute([$from, $to]);
    $allCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary
    $stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
    $totalCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as new_customers FROM customers WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $newCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['new_customers'];

    $stmt = $pdo->query("SELECT COUNT(*) as regular_customers FROM customers WHERE points >= 25");
    $regularCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['regular_customers'];

    $stmt = $pdo->query("SELECT AVG(points) as avg_points FROM customers");
    $avgPoints = $stmt->fetch(PDO::FETCH_ASSOC)['avg_points'] ?: 0;

    // Calculate average purchase from bills in date range
    $stmt = $pdo->prepare("SELECT AVG(total) as avg_purchase FROM bills_backup WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $avgPurchase = $stmt->fetch(PDO::FETCH_ASSOC)['avg_purchase'] ?: 0;

    return [
        'summary' => [
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'regular_customers' => $regularCustomers,
            'avg_points' => (float)$avgPoints,
            'avg_purchase' => (float)$avgPurchase
        ],
        'topCustomers' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'phone' => $item['phone'],
                'points' => (int)$item['points'],
                'joined' => $item['joined'],
                'status' => $item['status'],
                'totalSpent' => (float)$item['total_spent']
            ];
        }, $topCustomers),
        'details' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'phone' => $item['phone'],
                'points' => (int)$item['points'],
                'joined' => $item['joined'],
                'status' => $item['status'],
                'totalSpent' => (float)$item['total_spent'],
                'totalPurchases' => (int)$item['total_purchases']
            ];
        }, $allCustomers),
        'chart' => [
            'labels' => array_column($topCustomers, 'name'),
            'values' => array_map('floatval', array_column($topCustomers, 'total_spent'))
        ],
        'distribution' => [
            'labels' => ['VIP', 'Regular', 'New'],
            'values' => [
                count(array_filter($allCustomers, fn($c) => $c['status'] === 'VIP')),
                count(array_filter($allCustomers, fn($c) => $c['status'] === 'Regular')),
                count(array_filter($allCustomers, fn($c) => $c['status'] === 'New'))
            ]
        ]
    ];
}

function generateBillsReport($pdo, $from, $to, $group) {
    $dateFormat = getDateFormat($group);
    $groupField = getGroupField($group);

    // Get bills data
    $stmt = $pdo->prepare("
        SELECT
            id,
            customer_name,
            customer_type,
            total,
            DATE_FORMAT(created_at, '$dateFormat') as period,
            created_at
        FROM bills_backup
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$from, $to]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_bills, SUM(total) as total_revenue FROM bills_backup WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_bills' => 0, 'total_revenue' => 0];

    $stmt = $pdo->prepare("SELECT COUNT(*) as walk_in_bills FROM bills_backup WHERE customer_type = 'walk-in' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $walkInBills = $stmt->fetch(PDO::FETCH_ASSOC)['walk_in_bills'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as regular_bills FROM bills_backup WHERE customer_type = 'regular' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $regularBills = $stmt->fetch(PDO::FETCH_ASSOC)['regular_bills'];

    return [
        'summary' => [
            'total_bills' => (int)$summary['total_bills'],
            'total_revenue' => (float)$summary['total_revenue'],
            'walk_in_bills' => (int)$walkInBills,
            'regular_bills' => (int)$regularBills
        ],
        'details' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'customer_name' => $item['customer_name'],
                'customer_type' => ucfirst($item['customer_type']),
                'total' => (float)$item['total'],
                'created_at' => $item['created_at']
            ];
        }, $bills),
        'chart' => [
            'labels' => array_column($bills, 'period'),
            'values' => array_map('floatval', array_column($bills, 'total'))
        ],
        'distribution' => [
            'labels' => ['Walk-in', 'Regular'],
            'values' => [$walkInBills, $regularBills]
        ]
    ];
}

function getDateFormat($group) {
    switch ($group) {
        case 'daily': return '%Y-%m-%d';
        case 'weekly': return '%Y-%u';
        case 'monthly': return '%Y-%m';
        case 'yearly': return '%Y';
        default: return '%Y-%m-%d';
    }
}

function getGroupField($group) {
    switch ($group) {
        case 'daily': return 'DATE(created_at)';
        case 'weekly': return 'YEARWEEK(created_at)';
        case 'monthly': return 'DATE_FORMAT(created_at, "%Y-%m")';
        case 'yearly': return 'YEAR(created_at)';
        default: return 'DATE(created_at)';
    }
}
?>