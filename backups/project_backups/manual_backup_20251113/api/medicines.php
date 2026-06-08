<?php
include '../includes/db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Helper function to send JSON response
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Fuzzy match function for approximate name matching
function fuzzyMatch($str1, $str2) {
    $str1 = strtolower($str1);
    $str2 = strtolower($str2);
    
    similar_text($str1, $str2, $percent);
    return $percent;
}

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        json_response(['error' => 'Method not allowed'], 405);
}

function handleGet($conn) {
    // Get single medicine by ID
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medicine = $result->fetch_assoc();
        
        if ($medicine) {
            json_response($medicine);
        } else {
            json_response(['error' => 'Medicine not found'], 404);
        }
    }
    
    // Search with advanced filtering
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $stockFilter = $_GET['stock_filter'] ?? '';
    $priceMin = isset($_GET['price_min']) ? floatval($_GET['price_min']) : null;
    $priceMax = isset($_GET['price_max']) ? floatval($_GET['price_max']) : null;
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = $_GET['sort_order'] ?? 'ASC';
    $limit = intval($_GET['limit'] ?? 1000);
    $offset = intval($_GET['offset'] ?? 0);

    $where = [];
    $params = [];
    $types = '';

    // Search with fuzzy matching
    if (!empty($search)) {
        $where[] = "(name LIKE ? OR description LIKE ? OR category LIKE ? OR manufacturer LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
        $types .= 's';
    }

    // Stock filtering
    if (!empty($stockFilter)) {
        switch ($stockFilter) {
            case 'high':
                $where[] = "quantity > 100";
                break;
            case 'medium':
                $where[] = "quantity >= 20 AND quantity <= 100";
                break;
            case 'low':
                $where[] = "quantity > 0 AND quantity < 20";
                break;
            case 'out':
                $where[] = "quantity <= 0";
                break;
        }
    }

    // Price filtering
    if ($priceMin !== null) {
        $where[] = "price >= ?";
        $params[] = $priceMin;
        $types .= 'd';
    }
    
    if ($priceMax !== null) {
        $where[] = "price <= ?";
        $params[] = $priceMax;
        $types .= 'd';
    }

    // Build WHERE clause
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Validate sort column
    $validSorts = ['name', 'price', 'quantity', 'category', 'created_at'];
    if (!in_array($sortBy, $validSorts)) {
        $sortBy = 'name';
    }
    
    $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    
    $query = "SELECT * FROM medicines $whereClause ORDER BY $sortBy $sortOrder LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    try {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $medicines = [];
        while ($row = $result->fetch_assoc()) {
            $medicines[] = $row;
        }
        
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM medicines $whereClause";
        if (!empty($where)) {
            $stmt = $conn->prepare($countQuery);
            // Remove last two params (limit and offset)
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);
            if (!empty($countParams)) {
                $stmt->bind_param($countTypes, ...$countParams);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
        } else {
            $total = count($medicines);
        }
        
        json_response($medicines);
        
    } catch (Exception $e) {
        json_response(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function handlePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || empty($data['name'])) {
        json_response(['success' => false, 'message' => 'Medicine name is required'], 400);
    }
    
    $name = $data['name'];
    $category = $data['category'] ?? 'Other';
    $description = $data['description'] ?? '';
    $price = floatval($data['price'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $expiryDate = $data['expiry_date'] ?? null;
    $manufacturer = $data['manufacturer'] ?? '';
    $batchNumber = $data['batch_number'] ?? '';
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO medicines (name, category, description, price, quantity, expiry_date, manufacturer, batch_number, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("sssdiiss", $name, $category, $description, $price, $quantity, $expiryDate, $manufacturer, $batchNumber);
        $stmt->execute();
        
        $insertId = $conn->insert_id;
        
        json_response([
            'success' => true,
            'message' => 'Medicine added successfully',
            'id' => $insertId
        ]);
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error adding medicine: ' . $e->getMessage()], 500);
    }
}

function handlePut($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        json_response(['success' => false, 'message' => 'Medicine ID is required'], 400);
    }
    
    $id = intval($data['id']);
    $name = $data['name'];
    $category = $data['category'] ?? 'Other';
    $description = $data['description'] ?? '';
    $price = floatval($data['price'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $expiryDate = $data['expiry_date'] ?? null;
    $manufacturer = $data['manufacturer'] ?? '';
    $batchNumber = $data['batch_number'] ?? '';
    
    try {
        $stmt = $conn->prepare("
            UPDATE medicines 
            SET name = ?, category = ?, description = ?, price = ?, quantity = ?, 
                expiry_date = ?, manufacturer = ?, batch_number = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssdisssi", $name, $category, $description, $price, $quantity, $expiryDate, $manufacturer, $batchNumber, $id);
        $stmt->execute();
        
        json_response([
            'success' => true,
            'message' => 'Medicine updated successfully'
        ]);
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error updating medicine: ' . $e->getMessage()], 500);
    }
}

function handleDelete($conn) {
    // Check for delete all action
    if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
        try {
            $stmt = $conn->prepare("DELETE FROM medicines");
            $stmt->execute();
            
            json_response([
                'success' => true,
                'message' => 'All medicines deleted successfully',
                'deleted_count' => $stmt->affected_rows
            ]);
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Error deleting all medicines: ' . $e->getMessage()], 500);
        }
        return;
    }
    
    // Single delete
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        json_response(['success' => false, 'message' => 'Medicine ID is required'], 400);
    }
    
    $id = intval($data['id']);
    
    try {
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            json_response([
                'success' => true,
                'message' => 'Medicine deleted successfully'
            ]);
        } else {
            json_response(['success' => false, 'message' => 'Medicine not found'], 404);
        }
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error deleting medicine: ' . $e->getMessage()], 500);
    }
}

$conn->close();
?>
                $countStmt = $conn->prepare($countQuery);
                if (!empty($where)) {
                    $countTypes = str_replace('ii', '', $types); // Remove limit and offset types
                    $countParams = array_slice($params, 0, -2); // Remove limit and offset params
                    if (!empty($countParams)) {
                        $countStmt->bind_param($countTypes, ...$countParams);
                    }
                }
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $total = $countResult->fetch_assoc()['total'];
                
                json_response([
                    'success' => true,
                    'medicines' => $medicines,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
            } catch (Exception $e) {
                json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
            }
        }
        break;

    case 'POST':
        $data = $_POST;
        // Basic validation
        if (empty($data['name']) || !isset($data['price']) || !isset($data['quantity'])) {
            json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
        }

        // Handle image upload
        $imageName = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadDir = '../images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $imageName = uniqid() . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $imageName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                // Image uploaded successfully
            } else {
                json_response(['success' => false, 'message' => 'Failed to upload image.'], 500);
            }
        }

        $stmt = $conn->prepare("INSERT INTO medicines (name, description, price, quantity, category, image, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdisss", $data['name'], $data['description'], $data['price'], $data['quantity'], $data['category'], $imageName, $data['expiry_date']);
        if ($stmt->execute()) {
            json_response(['success' => true, 'id' => $conn->insert_id]);
        } else {
            json_response(['success' => false, 'message' => 'Failed to add medicine.'], 500);
        }
        break;

    case 'PUT':
        // Check if it's JSON or form data
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
        } else {
            $data = $_POST;
        }
        
        if (empty($data['id']) || empty($data['name']) || !isset($data['price']) || !isset($data['quantity'])) {
            json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
        }
        
        $id = intval($data['id']);

        // Handle image upload
        $imageName = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadDir = '../images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $imageName = uniqid() . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $imageName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                // Image uploaded successfully
            } else {
                json_response(['success' => false, 'message' => 'Failed to upload image.'], 500);
            }
        }

        // If no new image, keep the existing one
        if (!$imageName) {
            $stmt = $conn->prepare("SELECT image FROM medicines WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $imageName = $row['image'];
            }
        }

        $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, price = ?, quantity = ?, category = ?, image = ?, expiry_date = ? WHERE id = ?");
        $stmt->bind_param("ssdisssi", $data['name'], $data['description'], $data['price'], $data['quantity'], $data['category'], $imageName, $data['expiry_date'], $id);
        if ($stmt->execute()) {
            json_response(['success' => true]);
        } else {
            json_response(['success' => false, 'message' => $stmt->error], 500);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            json_response(['success' => false, 'message' => 'ID not provided.'], 400);
        }
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            json_response(['success' => true]);
        } else {
            json_response(['success' => false, 'message' => $stmt->error], 500);
        }
        break;

    default:
        json_response(['message' => 'Method Not Allowed'], 405);
        break;
}
?>
