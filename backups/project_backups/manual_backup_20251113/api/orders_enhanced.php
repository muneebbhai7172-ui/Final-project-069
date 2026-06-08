<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include '../includes/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'customer_orders':
                    getCustomerOrders();
                    break;
                case 'order_details':
                    getOrderDetails();
                    break;
                case 'order_stats':
                    getOrderStats();
                    break;
                default:
                    getAllOrders();
            }
        } else {
            getAllOrders();
        }
        break;
    case 'POST':
        createAdvancedOrder();
        break;
    case 'PUT':
        updateOrderStatus();
        break;
    case 'DELETE':
        cancelOrder();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getAllOrders() {
    global $conn;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $stmt = $conn->prepare("SELECT o.*, COUNT(oi.id) as item_count 
                           FROM orders o 
                           LEFT JOIN order_items oi ON o.id = oi.order_id 
                           GROUP BY o.id 
                           ORDER BY o.order_date DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders");
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $total = $totalResult->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_orders' => $total
        ]
    ]);
}

function getCustomerOrders() {
    global $conn;
    $phone = $_GET['phone'] ?? '';
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT o.*, COUNT(oi.id) as item_count 
                           FROM orders o 
                           LEFT JOIN order_items oi ON o.id = oi.order_id 
                           WHERE o.phone = ? 
                           GROUP BY o.id 
                           ORDER BY o.order_date DESC");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'orders' => $orders]);
}

function getOrderDetails() {
    global $conn;
    $orderId = $_GET['order_id'] ?? 0;
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    // Get order information
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    // Get order items with medicine details
    $stmt = $conn->prepare("SELECT oi.*, m.name as medicine_name, m.image 
                           FROM order_items oi 
                           JOIN medicines m ON oi.medicine_id = m.id 
                           WHERE oi.order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    
    $order['items'] = $items;
    
    echo json_encode(['success' => true, 'order' => $order]);
}

function getOrderStats() {
    global $conn;
    
    $stats = [];
    
    // Total orders
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_orders'] = $result->fetch_assoc()['total'];
    
    // Orders by status
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $stmt->execute();
    $result = $stmt->get_result();
    $statusStats = $result->fetch_all(MYSQLI_ASSOC);
    $stats['orders_by_status'] = $statusStats;
    
    // Revenue
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total_revenue FROM orders WHERE status != 'Cancelled'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_revenue'] = $result->fetch_assoc()['total_revenue'] ?? 0;
    
    // Today's orders
    $stmt = $conn->prepare("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(order_date) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_orders'] = $result->fetch_assoc()['today_orders'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function createAdvancedOrder() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Enhanced validation
    $requiredFields = ['customer_name', 'phone', 'address', 'payment_method', 'total_amount', 'items'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Validate phone number format
    if (!preg_match('/^(\+92|0)?[0-9]{10,11}$/', $data['phone'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        return;
    }
    
    // Validate items array
    if (!is_array($data['items']) || empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Order must contain at least one item']);
        return;
    }
    
    $conn->begin_transaction();
    try {
        // Generate order number
        $orderNumber = 'WDH' . date('Ymd') . sprintf('%04d', mt_rand(1, 9999));
        
        // Insert order with additional fields
        $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_name, phone, address, payment_method, total_amount, notes, delivery_date, preferred_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $notes = $data['notes'] ?? '';
        $deliveryDate = $data['delivery_date'] ?? null;
        $preferredTime = $data['preferred_time'] ?? '';
        
        $stmt->bind_param("sssssdsss", $orderNumber, $data['customer_name'], $data['phone'], $data['address'], 
                         $data['payment_method'], $data['total_amount'], $notes, $deliveryDate, $preferredTime);
        $stmt->execute();
        $orderId = $conn->insert_id;

        $totalCalculated = 0;
        
        // Insert order items with validation
        foreach ($data['items'] as $item) {
            if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['price'])) {
                throw new Exception('Invalid item data');
            }
            
            // Check medicine availability
            $checkStmt = $conn->prepare("SELECT quantity, name FROM medicines WHERE id = ?");
            $checkStmt->bind_param("i", $item['id']);
            $checkStmt->execute();
            $medicineResult = $checkStmt->get_result();
            $medicine = $medicineResult->fetch_assoc();
            
            if (!$medicine) {
                throw new Exception('Medicine not found: ID ' . $item['id']);
            }
            
            if ($medicine['quantity'] < $item['quantity']) {
                throw new Exception('Insufficient stock for: ' . $medicine['name']);
            }
            
            // Insert order item
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $orderId, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();

            // Update medicine quantity
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $item['quantity'], $item['id']);
            $stmt->execute();
            
            $totalCalculated += $item['price'] * $item['quantity'];
        }
        
        // Verify total amount
        if (abs($totalCalculated - $data['total_amount']) > 0.01) {
            throw new Exception('Total amount mismatch');
        }
        
        // Create order log entry
        $stmt = $conn->prepare("INSERT INTO order_logs (order_id, status, notes, created_at) VALUES (?, 'Pending', 'Order created', NOW())");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        
        $conn->commit();
        
        // Send response with order details
        echo json_encode([
            'success' => true, 
            'message' => 'Order placed successfully!', 
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'estimated_delivery' => date('Y-m-d', strtotime('+1 day'))
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to place order: ' . $e->getMessage()]);
    }
}

function updateOrderStatus() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Order ID and status required']);
        return;
    }
    
    $validStatuses = ['Pending', 'Confirmed', 'Preparing', 'Dispatched', 'Delivered', 'Cancelled'];
    if (!in_array($data['status'], $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $data['status'], $data['order_id']);
        $stmt->execute();
        
        // Log status change
        $notes = $data['notes'] ?? "Status changed to {$data['status']}";
        $stmt = $conn->prepare("INSERT INTO order_logs (order_id, status, notes, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $data['order_id'], $data['status'], $notes);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
    }
}

function cancelOrder() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    $conn->begin_transaction();
    try {
        // Get order items to restore stock
        $stmt = $conn->prepare("SELECT oi.medicine_id, oi.quantity FROM order_items oi 
                               JOIN orders o ON oi.order_id = o.id 
                               WHERE o.id = ? AND o.status != 'Cancelled'");
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        
        // Restore stock
        foreach ($items as $item) {
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param("ii", $item['quantity'], $item['medicine_id']);
            $stmt->execute();
        }
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
        $stmt->bind_param("i", $data['order_id']);
        $stmt->execute();
        
        // Log cancellation
        $reason = $data['reason'] ?? 'Order cancelled';
        $stmt = $conn->prepare("INSERT INTO order_logs (order_id, status, notes, created_at) VALUES (?, 'Cancelled', ?, NOW())");
        $stmt->bind_param("is", $data['order_id'], $reason);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()]);
    }
}
?>