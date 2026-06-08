<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $billId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($billId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid bill ID'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT *
        FROM bills
        WHERE id = ?
    ");
    $stmt->execute([$billId]);
    
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        echo json_encode([
            'success' => true,
            'bill' => $bill
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Bill not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
