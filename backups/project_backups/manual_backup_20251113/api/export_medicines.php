<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="medicines_export_' . date('Y-m-d') . '.csv"');

require_once '../config/database.php';

try {
    $where = [];
    $params = [];

    // Build WHERE clause based on filters
    if (!empty($_GET['search'])) {
        $where[] = "name LIKE ?";
        $params[] = "%" . $_GET['search'] . "%";
    }

    if (!empty($_GET['category'])) {
        $where[] = "category = ?";
        $params[] = $_GET['category'];
    }

    if (!empty($_GET['stock'])) {
        switch ($_GET['stock']) {
            case 'in-stock':
                $where[] = "quantity > 0";
                break;
            case 'low-stock':
                $where[] = "quantity > 0 AND quantity < 10";
                break;
            case 'out-of-stock':
                $where[] = "quantity <= 0";
                break;
        }
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("
        SELECT id, name, category, price, quantity, description, created_at
        FROM medicines
        $whereClause
        ORDER BY name
    ");

    $stmt->execute($params);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Category', 'Price', 'Quantity', 'Description', 'Created At']);

    // Output data
    foreach ($medicines as $medicine) {
        fputcsv($output, [
            $medicine['id'],
            $medicine['name'],
            $medicine['category'],
            $medicine['price'],
            $medicine['quantity'],
            $medicine['description'] ?? '',
            $medicine['created_at']
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    // If there's an error, output error message
    header('Content-Type: text/plain');
    echo 'Error exporting medicines: ' . $e->getMessage();
}
?>