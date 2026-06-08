<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/database.php';

try {
    $filter = $_GET['filter'] ?? 'all';
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = [];
    $params = [];

    // Build WHERE clause
    if (!empty($search)) {
        $where[] = "name LIKE ?";
        $params[] = "%" . $search . "%";
    }

    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    // Add filter conditions
    switch ($filter) {
        case 'in-stock':
            $where[] = "quantity > 0";
            break;
        case 'low-stock':
            $where[] = "quantity > 0 AND quantity < 10";
            break;
        case 'out-of-stock':
            $where[] = "quantity <= 0";
            break;
        case 'near-expiry':
            $where[] = "expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()";
            break;
        case 'expired':
            $where[] = "expiry_date IS NOT NULL AND expiry_date < CURDATE()";
            break;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get medicines data
    $stmt = $pdo->prepare("
        SELECT id, name, category, quantity, expiry_date
        FROM medicines
        $whereClause
        ORDER BY
            CASE
                WHEN quantity <= 0 THEN 1
                WHEN quantity < 10 THEN 2
                WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 3
                WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 4
                ELSE 5
            END,
            name
    ");

    $stmt->execute($params);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summary = [
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'expired' => 0
    ];

    // Count all medicines by status
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE quantity > 10");
    $summary['in_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE quantity > 0 AND quantity <= 10");
    $summary['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE quantity <= 0");
    $summary['out_of_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
    $summary['expired'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get low stock alerts (items with quantity <= 5)
    $stmt = $pdo->query("
        SELECT id, name, category, quantity
        FROM medicines
        WHERE quantity <= 5 AND quantity > 0
        ORDER BY quantity ASC
        LIMIT 10
    ");
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $medicines,
        'summary' => $summary,
        'alerts' => $alerts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>