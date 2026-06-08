<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $recent = isset($_GET['recent']) ? intval($_GET['recent']) : 0;
    
    if ($recent > 0) {
        // Get recent bills
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, customer_id, customer_name, customer_phone, 
                   customer_type, subtotal, discount, tax, total, created_at
            FROM bills
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$recent]);
    } elseif (strlen($search) >= 2) {
        // Search bills
        $searchTerm = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, customer_id, customer_name, customer_phone, 
                   customer_type, subtotal, discount, tax, total, created_at
            FROM bills
            WHERE invoice_number LIKE ? 
               OR customer_name LIKE ? 
               OR customer_phone LIKE ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a search term (minimum 2 characters)',
            'bills' => []
        ]);
        exit;
    }
    
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bills' => $bills,
        'count' => count($bills)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'bills' => []
    ]);
}
