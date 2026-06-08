<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
    $phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';
    
    if (!$orderId && !$phone && !$name) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide order ID, phone number, or name'
        ]);
        exit;
    }
    
    // Build query based on search criteria
    if ($orderId) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
    } elseif ($phone) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE customer_phone LIKE ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['%' . $phone . '%']);
    } elseif ($name) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE customer_name LIKE ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['%' . $name . '%']);
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
