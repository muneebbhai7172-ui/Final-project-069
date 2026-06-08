<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include '../includes/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        getStock();
        break;
    case 'POST':
        createStock();
        break;
    case 'PUT':
        updateStock();
        break;
    case 'DELETE':
        deleteStock();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// GET - Fetch all stock
function getStock() {
    global $conn;
    
    try {
        $query = "SELECT id, name, category, description, quantity, price, expiry_date, 
                  created_at, updated_at 
                  FROM medicines 
                  ORDER BY name ASC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $stock = [];
        while ($row = $result->fetch_assoc()) {
            $stock[] = $row;
        }
        
        jsonResponse($stock);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch stock: ' . $e->getMessage()], 500);
    }
}

// POST - Create new stock item
function createStock() {
    global $conn;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            jsonResponse(['success' => false, 'message' => 'No data received'], 400);
        }
        
        // Validate required fields
        $required = ['name', 'category', 'quantity', 'price'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                jsonResponse(['success' => false, 'message' => "Missing required field: $field"], 400);
            }
        }
        
        $name = trim($data['name']);
        $category = trim($data['category']);
        $description = trim($data['description'] ?? '');
        $quantity = intval($data['quantity']);
        $price = floatval($data['price']);
        $expiryDate = $data['expiry_date'] ?? null;
        
        // Check if medicine already exists
        $stmt = $conn->prepare("SELECT id FROM medicines WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            jsonResponse(['success' => false, 'message' => 'Medicine already exists'], 400);
        }
        
        // Insert new medicine
        $stmt = $conn->prepare("
            INSERT INTO medicines (name, category, description, quantity, price, expiry_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param("sssisd", $name, $category, $description, $quantity, $price, $expiryDate);
        
        if ($stmt->execute()) {
            jsonResponse([
                'success' => true,
                'message' => 'Stock item created successfully',
                'id' => $conn->insert_id
            ]);
        } else {
            throw new Exception($stmt->error);
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to create stock: ' . $e->getMessage()], 500);
    }
}

// PUT - Update stock item
function updateStock() {
    global $conn;
    
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Stock ID required'], 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            jsonResponse(['success' => false, 'message' => 'No data received'], 400);
        }
        
        // Check if just adding quantity
        if (isset($data['add_quantity'])) {
            $addQty = intval($data['add_quantity']);
            
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $addQty, $id);
            
            if ($stmt->execute()) {
                jsonResponse(['success' => true, 'message' => 'Quantity added successfully']);
            } else {
                throw new Exception($stmt->error);
            }
            return;
        }
        
        // Full update
        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? '');
        $description = trim($data['description'] ?? '');
        $quantity = intval($data['quantity'] ?? 0);
        $price = floatval($data['price'] ?? 0);
        $expiryDate = $data['expiry_date'] ?? null;
        
        if (empty($name) || empty($category)) {
            jsonResponse(['success' => false, 'message' => 'Name and category are required'], 400);
        }
        
        $stmt = $conn->prepare("
            UPDATE medicines 
            SET name = ?, category = ?, description = ?, quantity = ?, price = ?, 
                expiry_date = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param("sssidsi", $name, $category, $description, $quantity, $price, $expiryDate, $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                jsonResponse(['success' => true, 'message' => 'Stock updated successfully']);
            } else {
                jsonResponse(['success' => false, 'message' => 'No changes made or stock not found'], 404);
            }
        } else {
            throw new Exception($stmt->error);
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to update stock: ' . $e->getMessage()], 500);
    }
}

// DELETE - Delete stock item
function deleteStock() {
    global $conn;
    
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Stock ID required'], 400);
        }
        
        // Check if medicine is used in any orders
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE medicine_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            jsonResponse([
                'success' => false, 
                'message' => 'Cannot delete: Medicine is used in existing orders'
            ], 400);
        }
        
        // Delete medicine
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                jsonResponse(['success' => true, 'message' => 'Stock deleted successfully']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Stock not found'], 404);
            }
        } else {
            throw new Exception($stmt->error);
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to delete stock: ' . $e->getMessage()], 500);
    }
}
?>
