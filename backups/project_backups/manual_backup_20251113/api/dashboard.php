<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $response = ['success' => true, 'data' => []];

    // Get today's sales
    $stmt = $pdo->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'Completed' AND DATE(order_date) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['total_sales'] = (float) ($result['total_sales'] ?? 0);

    // Get out of stock medicines (quantity <= 5 for low stock warning)
    $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM medicines WHERE quantity <= 5");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['out_of_stock'] = (int) ($result['out_of_stock'] ?? 0);

    // Get medicines near expiry (within 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as near_expiry FROM medicines WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['near_expiry'] = (int) ($result['near_expiry'] ?? 0);

    // Get today's orders count
    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders WHERE DATE(order_date) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['total_orders'] = (int) ($result['total_orders'] ?? 0);

    // Get total customers count
    $stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['total_customers'] = (int) ($result['total_customers'] ?? 0);

    // Get sales data for the last 7 days
    $stmt = $pdo->query("
        SELECT DATE(order_date) as date, SUM(total_amount) as sales
        FROM orders
        WHERE status = 'Completed' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(order_date)
        ORDER BY date
    ");
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['sales_data'] = [
        'labels' => [],
        'values' => []
    ];

    // Fill in missing dates with 0 sales
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        foreach ($salesData as $data) {
            if ($data['date'] === $date) {
                $response['data']['sales_data']['labels'][] = date('M j', strtotime($date));
                $response['data']['sales_data']['values'][] = (float) $data['sales'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $response['data']['sales_data']['labels'][] = date('M j', strtotime($date));
            $response['data']['sales_data']['values'][] = 0;
        }
    }

    // Get top selling products (last 30 days)
    $stmt = $pdo->query("
        SELECT m.name, SUM(oi.quantity) as total_quantity
        FROM order_items oi
        INNER JOIN medicines m ON oi.medicine_id = m.id
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY m.id, m.name
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['top_products'] = [
        'labels' => [],
        'values' => []
    ];

    if (!empty($topProducts)) {
        foreach ($topProducts as $product) {
            $response['data']['top_products']['labels'][] = $product['name'];
            $response['data']['top_products']['values'][] = (int) $product['total_quantity'];
        }
    } else {
        // Default data if no products found
        $response['data']['top_products']['labels'] = ['No Data'];
        $response['data']['top_products']['values'] = [0];
    }

    // Get recent orders (last 10)
    $stmt = $pdo->query("
        SELECT o.id, o.customer_name, o.total_amount, o.status, o.order_date,
               COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['recent_orders'] = array_map(function($order) {
        return [
            'id' => '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT),
            'customer' => $order['customer_name'] ?? 'Guest',
            'items' => (int) ($order['item_count'] ?? 0),
            'total' => 'Rs. ' . number_format($order['total_amount'], 2),
            'status' => $order['status'],
            'time' => date('M j, H:i', strtotime($order['order_date']))
        ];
    }, $recentOrders);

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>