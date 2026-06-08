<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include '../includes/db_connect.php';

try {
    // Get total customers
    $stmt = $conn->prepare("SELECT COUNT(*) as total_customers FROM customers");
    $stmt->execute();
    $customers_result = $stmt->get_result();
    $customers_count = $customers_result->fetch_assoc()['total_customers'];

    // Get total medicines
    $stmt = $conn->prepare("SELECT COUNT(*) as total_medicines FROM medicines WHERE quantity > 0");
    $stmt->execute();
    $medicines_result = $stmt->get_result();
    $medicines_count = $medicines_result->fetch_assoc()['total_medicines'];

    // Get total orders (completed/paid orders for daily deliveries)
    $stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE status IN ('Completed', 'Dispatched')");
    $stmt->execute();
    $orders_result = $stmt->get_result();
    $orders_count = $orders_result->fetch_assoc()['total_orders'];

    // Get average rating (if reviews table exists and has ratings)
    $rating = 4.9; // Default rating if no reviews system
    $stmt = $conn->prepare("SHOW TABLES LIKE 'reviews'");
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE rating > 0");
        $stmt->execute();
        $rating_result = $stmt->get_result();
        $avg_rating = $rating_result->fetch_assoc()['avg_rating'];
        if ($avg_rating) {
            $rating = round($avg_rating, 1);
        }
    }

    // Calculate daily deliveries (orders from last 24 hours)
    $stmt = $conn->prepare("SELECT COUNT(*) as daily_deliveries FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND status IN ('Completed', 'Dispatched')");
    $stmt->execute();
    $daily_result = $stmt->get_result();
    $daily_deliveries = $daily_result->fetch_assoc()['daily_deliveries'];

    $stats = [
        'customers' => $customers_count,
        'medicines' => $medicines_count,
        'orders' => $orders_count,
        'daily_deliveries' => $daily_deliveries,
        'rating' => $rating
    ];

    echo json_encode($stats);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Failed to fetch statistics',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>