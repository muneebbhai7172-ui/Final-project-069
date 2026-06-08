<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d') . '.csv"');

require_once '../config/database.php';

try {
    $where = [];
    $params = [];

    // Build WHERE clause based on filters
    if (!empty($_GET['name'])) {
        $where[] = "name LIKE ?";
        $params[] = "%" . $_GET['name'] . "%";
    }

    if (!empty($_GET['phone'])) {
        $where[] = "phone LIKE ?";
        $params[] = "%" . $_GET['phone'] . "%";
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, points, created_at, updated_at
        FROM customers
        $whereClause
        ORDER BY name
    ");

    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Phone', 'Email', 'Points', 'Joined Date', 'Last Updated']);

    // Output data
    foreach ($customers as $customer) {
        fputcsv($output, [
            $customer['id'],
            $customer['name'],
            $customer['phone'],
            $customer['email'] ?? '',
            $customer['points'],
            $customer['created_at'],
            $customer['updated_at']
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    // If there's an error, output error message
    header('Content-Type: text/plain');
    echo 'Error exporting customers: ' . $e->getMessage();
}
?>