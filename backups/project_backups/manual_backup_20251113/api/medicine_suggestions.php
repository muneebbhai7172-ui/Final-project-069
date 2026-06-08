<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $search = isset($_GET['search']) ? $_GET['search'] : (isset($_GET['q']) ? $_GET['q'] : '');
    $type = isset($_GET['type']) ? $_GET['type'] : 'both'; // 'medicine', 'category', or 'both'
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    // Allow single character searches for better UX
    if (strlen($search) < 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'Search term is required', 
            'suggestions' => []
        ]);
        exit;
    }
    
    $suggestions = [];
    
    // Get medicine suggestions
    if ($type === 'medicine' || $type === 'both') {
        $stmt = $pdo->prepare("
            SELECT id, name, category, price, quantity, image
            FROM medicines 
            WHERE (name LIKE ? OR category LIKE ?) AND quantity > 0
            ORDER BY 
                CASE WHEN name LIKE ? THEN 1 ELSE 2 END,
                quantity DESC,
                name ASC
            LIMIT " . intval($limit) . "
        ");
        
        $searchTerm = "%" . $search . "%";
        $exactTerm = $search . "%";
        $stmt->execute([$searchTerm, $searchTerm, $exactTerm]);
        
        $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($medicines as $medicine) {
            $suggestions[] = [
                'type' => 'medicine',
                'name' => $medicine['name'],
                'category' => $medicine['category'],
                'price' => $medicine['price'],
                'image' => $medicine['image'],
                'quantity' => $medicine['quantity']
            ];
        }
    }
    
    // Get category suggestions
    if ($type === 'category' || $type === 'both') {
        $stmt = $pdo->prepare("
            SELECT category, COUNT(*) as count
            FROM medicines 
            WHERE category LIKE ? AND quantity > 0
            GROUP BY category
            HAVING count > 0
            ORDER BY 
                CASE WHEN category LIKE ? THEN 1 ELSE 2 END,
                count DESC
            LIMIT 5
        ");
        
        $searchTerm = "%" . $search . "%";
        $exactTerm = $search . "%";
        $stmt->execute([$searchTerm, $exactTerm]);
        
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as $category) {
            $suggestions[] = [
                'type' => 'category',
                'name' => $category['category'],
                'count' => $category['count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'count' => count($suggestions)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'suggestions' => []
    ]);
}
