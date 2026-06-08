<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');

require_once '../config/database.php';

try {
    $type = $_GET['type'] ?? 'sales';
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    $group = $_GET['group'] ?? 'daily';

    // Get report data (reuse logic from reports.php)
    switch ($type) {
        case 'sales':
            $data = generateSalesReport($pdo, $from, $to, $group);
            exportSalesReport($data);
            break;
        case 'inventory':
            $data = generateInventoryReport($pdo);
            exportInventoryReport($data);
            break;
        case 'customers':
            $data = generateCustomerReport($pdo, $from, $to);
            exportCustomerReport($data);
            break;
        case 'bills':
            $data = generateBillsReport($pdo, $from, $to, $group);
            exportBillsReport($data);
            break;
        default:
            throw new Exception('Invalid report type');
    }

} catch (Exception $e) {
    // If there's an error, output error message
    header('Content-Type: text/plain');
    echo 'Error exporting report: ' . $e->getMessage();
}

function exportSalesReport($data) {
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Orders', 'Revenue', 'Avg Order Value']);

    foreach ($data['details'] as $row) {
        fputcsv($output, [$row['date'], $row['orders'], $row['revenue'], $row['avg_order']]);
    }

    fclose($output);
}

function exportInventoryReport($data) {
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Medicine', 'Category', 'Stock', 'Value', 'Status']);

    foreach ($data['details'] as $row) {
        fputcsv($output, [$row['name'], $row['category'], $row['quantity'], $row['value'], $row['status']]);
    }

    fclose($output);
}

function exportCustomerReport($data) {
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Phone', 'Points', 'Joined', 'Status']);

    foreach ($data['details'] as $row) {
        fputcsv($output, [$row['name'], $row['phone'], $row['points'], $row['joined'], $row['status']]);
    }

    fclose($output);
}

function exportBillsReport($data) {
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Bill ID', 'Customer', 'Type', 'Total', 'Date']);

    foreach ($data['details'] as $row) {
        fputcsv($output, [$row['id'], $row['customer_name'], $row['customer_type'], $row['total'], $row['created_at']]);
    }

    fclose($output);
}

// Include the report generation functions from reports.php
function generateSalesReport($pdo, $from, $to, $group) {
    $dateFormat = getDateFormat($group);
    $groupField = getGroupField($group);

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
        }, $sales)
    ];
}

function generateInventoryReport($pdo) {
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
        ORDER BY name
    ");
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'details' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'category' => $item['category'],
                'quantity' => (int)$item['quantity'],
                'value' => (float)$item['value'],
                'status' => $item['status']
            ];
        }, $inventory)
    ];
}

function generateCustomerReport($pdo, $from, $to) {
    $stmt = $pdo->prepare("
        SELECT
            name,
            phone,
            points,
            DATE(created_at) as joined,
            CASE
                WHEN points >= 50 THEN 'VIP'
                WHEN points >= 25 THEN 'Regular'
                ELSE 'New'
            END as status
        FROM customers
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$from, $to]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'details' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'phone' => $item['phone'],
                'points' => (int)$item['points'],
                'joined' => $item['joined'],
                'status' => $item['status']
            ];
        }, $customers)
    ];
}

function generateBillsReport($pdo, $from, $to, $group) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            customer_name,
            customer_type,
            total,
            created_at
        FROM bills_backup
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$from, $to]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'details' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'customer_name' => $item['customer_name'],
                'customer_type' => ucfirst($item['customer_type']),
                'total' => (float)$item['total'],
                'created_at' => $item['created_at']
            ];
        }, $bills)
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