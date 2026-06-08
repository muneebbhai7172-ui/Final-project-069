<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');

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

    $stmt = $pdo->prepare("
        SELECT id, name, category, quantity, price, expiry_date, created_at
        FROM medicines
        $whereClause
        ORDER BY name
    ");

    $stmt->execute($params);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Category', 'Current Stock', 'Price', 'Expiry Date', 'Created Date', 'Status']);

    // Output data
    foreach ($medicines as $medicine) {
        $status = 'In Stock';
        if ($medicine['quantity'] <= 0) {
            $status = 'Out of Stock';
        } elseif ($medicine['quantity'] < 10) {
            $status = 'Low Stock';
        }

        if ($medicine['expiry_date']) {
            $expiry = new DateTime($medicine['expiry_date']);
            $today = new DateTime();
            $interval = $today->diff($expiry);

            if ($expiry < $today) {
                $status .= ' (Expired)';
            } elseif ($interval->days <= 30) {
                $status .= ' (Near Expiry)';
            }
        }

        fputcsv($output, [
            $medicine['id'],
            $medicine['name'],
            $medicine['category'],
            $medicine['quantity'],
            $medicine['price'],
            $medicine['expiry_date'] ?? 'N/A',
            $medicine['created_at'],
            $status
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    // If there's an error, output error message
    header('Content-Type: text/plain');
    echo 'Error exporting stock report: ' . $e->getMessage();
}
?>