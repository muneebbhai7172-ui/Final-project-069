<?php
header('Content-Type: application/json');

require_once 'config/database.php';

try {
    // Test database connection
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines");
    $result = $stmt->fetch();
    
    // Get sample medicines
    $stmt = $pdo->query("SELECT id, name, category, price, quantity FROM medicines LIMIT 5");
    $medicines = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'medicine_count' => $result['count'],
        'sample_medicines' => $medicines
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
