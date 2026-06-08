<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include '../includes/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

// Helper function to send JSON response
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Generate or get session ID
function getSessionId() {
    if (!isset($_COOKIE['cart_session'])) {
        $sessionId = 'cart_' . uniqid() . '_' . time();
        setcookie('cart_session', $sessionId, time() + (86400 * 30), '/'); // 30 days
        return $sessionId;
    }
    return $_COOKIE['cart_session'];
}

switch ($method) {
    case 'GET':
        getCartItems();
        break;
    case 'POST':
        addToCart();
        break;
    case 'PUT':
        updateCartItem();
        break;
    case 'DELETE':
        if (isset($_GET['clear']) && $_GET['clear'] == 'all') {
            clearCart();
        } else {
            removeFromCart();
        }
        break;
    default:
        json_response(['error' => 'Method not allowed'], 405);
}

function getCartItems() {
    global $conn;
    $sessionId = getSessionId();
    
    try {
        $stmt = $conn->prepare("
            SELECT cs.*, m.name, m.description, m.image, m.category, m.quantity as stock_quantity 
            FROM cart_sessions cs 
            JOIN medicines m ON cs.medicine_id = m.id 
            WHERE cs.session_id = ? 
            ORDER BY cs.created_at ASC
        ");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cartItems = [];
        $totalAmount = 0;
        $totalItems = 0;
        
        while ($row = $result->fetch_assoc()) {
            $cartItems[] = $row;
            $totalAmount += $row['price'] * $row['quantity'];
            $totalItems += $row['quantity'];
        }
        
        json_response([
            'success' => true,
            'items' => $cartItems,
            'summary' => [
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
                'item_count' => count($cartItems)
            ],
            'session_id' => $sessionId
        ]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error fetching cart: ' . $e->getMessage()], 500);
    }
}

function addToCart() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = getSessionId();
    
    // Validate required fields
    if (!isset($data['medicine_id']) || !isset($data['quantity']) || !isset($data['price'])) {
        json_response(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    $medicineId = intval($data['medicine_id']);
    $quantity = intval($data['quantity']);
    $price = floatval($data['price']);
    
    if ($quantity <= 0) {
        json_response(['success' => false, 'message' => 'Quantity must be greater than 0'], 400);
    }
    
    try {
        // Check medicine availability
        $stmt = $conn->prepare("SELECT name, quantity as stock FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $medicineId);
        $stmt->execute();
        $result = $stmt->get_result();
        $medicine = $result->fetch_assoc();
        
        if (!$medicine) {
            json_response(['success' => false, 'message' => 'Medicine not found'], 404);
        }
        
        if ($medicine['stock'] < $quantity) {
            json_response(['success' => false, 'message' => 'Insufficient stock. Available: ' . $medicine['stock']], 400);
        }
        
        // Check if item already exists in cart
        $stmt = $conn->prepare("SELECT quantity FROM cart_sessions WHERE session_id = ? AND medicine_id = ?");
        $stmt->bind_param("si", $sessionId, $medicineId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingItem = $result->fetch_assoc();
        
        if ($existingItem) {
            // Update existing item
            $newQuantity = $existingItem['quantity'] + $quantity;
            if ($medicine['stock'] < $newQuantity) {
                json_response(['success' => false, 'message' => 'Cannot add more. Stock limit: ' . $medicine['stock']], 400);
            }
            
            $stmt = $conn->prepare("UPDATE cart_sessions SET quantity = ?, price = ?, updated_at = NOW() WHERE session_id = ? AND medicine_id = ?");
            $stmt->bind_param("idsi", $newQuantity, $price, $sessionId, $medicineId);
        } else {
            // Insert new item
            $stmt = $conn->prepare("INSERT INTO cart_sessions (session_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siid", $sessionId, $medicineId, $quantity, $price);
        }
        
        $stmt->execute();
        
        json_response([
            'success' => true, 
            'message' => $medicine['name'] . ' added to cart successfully',
            'item' => [
                'medicine_id' => $medicineId,
                'quantity' => $existingItem ? $newQuantity : $quantity,
                'price' => $price
            ]
        ]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error adding to cart: ' . $e->getMessage()], 500);
    }
}

function updateCartItem() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = getSessionId();
    
    if (!isset($data['medicine_id']) || !isset($data['quantity'])) {
        json_response(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    $medicineId = intval($data['medicine_id']);
    $quantity = intval($data['quantity']);
    
    if ($quantity <= 0) {
        // Remove item if quantity is 0 or negative
        removeFromCart();
        return;
    }
    
    try {
        // Check stock availability
        $stmt = $conn->prepare("SELECT name, quantity as stock FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $medicineId);
        $stmt->execute();
        $result = $stmt->get_result();
        $medicine = $result->fetch_assoc();
        
        if (!$medicine) {
            json_response(['success' => false, 'message' => 'Medicine not found'], 404);
        }
        
        if ($medicine['stock'] < $quantity) {
            json_response(['success' => false, 'message' => 'Insufficient stock. Available: ' . $medicine['stock']], 400);
        }
        
        // Update cart item
        $stmt = $conn->prepare("UPDATE cart_sessions SET quantity = ?, updated_at = NOW() WHERE session_id = ? AND medicine_id = ?");
        $stmt->bind_param("isi", $quantity, $sessionId, $medicineId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            json_response(['success' => false, 'message' => 'Cart item not found'], 404);
        }
        
        json_response([
            'success' => true, 
            'message' => 'Cart updated successfully',
            'item' => [
                'medicine_id' => $medicineId,
                'quantity' => $quantity
            ]
        ]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error updating cart: ' . $e->getMessage()], 500);
    }
}

function removeFromCart() {
    global $conn;
    $sessionId = getSessionId();
    
    if (isset($_GET['medicine_id'])) {
        $medicineId = intval($_GET['medicine_id']);
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $medicineId = intval($data['medicine_id'] ?? 0);
    }
    
    if (!$medicineId) {
        json_response(['success' => false, 'message' => 'Medicine ID required'], 400);
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart_sessions WHERE session_id = ? AND medicine_id = ?");
        $stmt->bind_param("si", $sessionId, $medicineId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            json_response(['success' => false, 'message' => 'Cart item not found'], 404);
        }
        
        json_response(['success' => true, 'message' => 'Item removed from cart successfully']);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error removing from cart: ' . $e->getMessage()], 500);
    }
}

function clearCart() {
    global $conn;
    $sessionId = getSessionId();
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart_sessions WHERE session_id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        
        json_response([
            'success' => true, 
            'message' => 'Cart cleared successfully',
            'items_removed' => $stmt->affected_rows
        ]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error clearing cart: ' . $e->getMessage()], 500);
    }
}
?>