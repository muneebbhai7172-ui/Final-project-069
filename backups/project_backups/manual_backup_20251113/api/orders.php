<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Generate order ID
function generateOrderId() {
    return 'ORD' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -6));
}

switch ($method) {
    case 'GET':
        if (isset($_GET['order_id'])) {
            getOrderDetails();
        } else {
            getOrders();
        }
        break;
    case 'POST':
        createOrder();
        break;
    case 'PUT':
        updateOrderStatus();
        break;
    case 'DELETE':
        deleteOrder();
        break;
    default:
        json_response(['error' => 'Method not allowed'], 405);
}

function createOrder() {
    global $conn;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            json_response(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()], 400);
        }
        
        if (!$data) {
            json_response(['success' => false, 'message' => 'No data received'], 400);
        }
        
        // Validate required fields
        $required = ['customer_name', 'email', 'phone', 'address', 'cart_items'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                json_response(['success' => false, 'message' => "Missing required field: $field"], 400);
            }
        }
    
        $customerName = trim($data['customer_name']);
        $email = trim($data['email']);
        $phone = trim($data['phone']);
        $address = trim($data['address']);
        $cartItems = $data['cart_items'];
        $paymentMethod = $data['payment_method'] ?? 'cash_on_delivery';
        $notes = $data['notes'] ?? '';
        $transactionId = $data['transaction_id'] ?? null; // Get transaction ID from request
        
        // Generate transaction ID if not provided
        if (empty($transactionId)) {
            $transactionId = 'TXN' . date('YmdHis') . '_' . strtoupper(substr(uniqid(), -6));
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'Invalid email format'], 400);
        }
        
        // Validate cart items
        if (empty($cartItems) || !is_array($cartItems)) {
            json_response(['success' => false, 'message' => 'Cart cannot be empty'], 400);
        }

        $conn->begin_transaction();
        
        // Calculate total and validate stock
        $totalAmount = 0;
        $orderItems = [];
        
        foreach ($cartItems as $item) {
            if (!isset($item['medicine_id']) || !isset($item['quantity']) || !isset($item['price'])) {
                throw new Exception('Invalid cart item structure');
            }
            
            $medicineId = intval($item['medicine_id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            
            if ($quantity <= 0) {
                throw new Exception('Invalid quantity for medicine ID: ' . $medicineId);
            }
            
            // Check medicine exists and has sufficient stock
            $stmt = $conn->prepare("SELECT name, quantity as stock, price as current_price FROM medicines WHERE id = ?");
            $stmt->bind_param("i", $medicineId);
            $stmt->execute();
            $result = $stmt->get_result();
            $medicine = $result->fetch_assoc();
            
            if (!$medicine) {
                throw new Exception('Medicine not found: ID ' . $medicineId);
            }
            
            if ($medicine['stock'] < $quantity) {
                throw new Exception('Insufficient stock for ' . $medicine['name'] . '. Available: ' . $medicine['stock']);
            }
            
            $orderItems[] = [
                'medicine_id' => $medicineId,
                'medicine_name' => $medicine['name'],
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $price * $quantity
            ];
            
            $totalAmount += $price * $quantity;
        }
        
        // Generate order ID and order number
        $orderId = generateOrderId();
        $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Insert order using existing table structure (no email column in orders table)
        $stmt = $conn->prepare("
            INSERT INTO orders (order_id, order_number, customer_name, phone, address, total_amount, payment_method, notes, transaction_id, status, order_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
        ");
        $stmt->bind_param("sssssdsss", $orderId, $orderNumber, $customerName, $phone, $address, $totalAmount, $paymentMethod, $notes, $transactionId);
        $stmt->execute();
        $orderDbId = $conn->insert_id;
        
        // Insert order items
        foreach ($orderItems as $item) {
            $stmt = $conn->prepare("
                INSERT INTO order_items (order_id, medicine_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiid", $orderDbId, $item['medicine_id'], $item['quantity'], $item['price']);
            $stmt->execute();
            
            // Update reserved quantity
            $stmt = $conn->prepare("UPDATE medicines SET reserved_quantity = COALESCE(reserved_quantity, 0) + ? WHERE id = ?");
            $stmt->bind_param("ii", $item['quantity'], $item['medicine_id']);
            $stmt->execute();
        }
        
        // Save customer email in customers table if not exists
        $stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new customer
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, points, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
            $stmt->bind_param("sss", $customerName, $phone, $email);
            $stmt->execute();
        } else {
            // Update existing customer email if empty
            $stmt = $conn->prepare("UPDATE customers SET email = ?, name = ?, updated_at = NOW() WHERE phone = ? AND (email IS NULL OR email = '')");
            $stmt->bind_param("sss", $email, $customerName, $phone);
            $stmt->execute();
        }
        
        $conn->commit();
        
        json_response([
            'success' => true,
            'message' => 'Order created successfully',
            'order' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'total_amount' => $totalAmount,
                'status' => 'Pending',
                'items' => $orderItems,
                'customer' => [
                    'name' => $customerName,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        json_response(['success' => false, 'message' => 'Error creating order: ' . $e->getMessage()], 500);
    }
}

function getOrders() {
    global $conn;
    
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 100);
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($status)) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(order_id LIKE ? OR customer_name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // Get orders - only select columns that exist in the table
        $query = "
            SELECT id, order_id, customer_name, phone, total_amount, status, payment_method, created_at 
            FROM orders 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        // Return orders directly as array for simpler frontend handling
        json_response($orders);
        
    } catch (Exception $e) {
        json_response(['error' => 'Error fetching orders: ' . $e->getMessage()], 500);
    }
}

function getOrderDetails() {
    global $conn;
    $orderId = $_GET['order_id'];
    
    try {
        // Get order details including transaction_id
        $stmt = $conn->prepare("
            SELECT o.*, 
                   o.transaction_id,
                   o.payment_method,
                   o.order_id,
                   o.customer_name,
                   o.phone,
                   o.address,
                   o.total_amount,
                   o.status,
                   o.notes,
                   o.created_at,
                   o.order_date
            FROM orders o 
            WHERE o.order_id = ? OR o.id = ?
        ");
        $stmt->bind_param("si", $orderId, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if (!$order) {
            json_response(['error' => 'Order not found'], 404);
        }
        
        // Ensure transaction_id is included in response
        if (!isset($order['transaction_id'])) {
            $order['transaction_id'] = null;
        }
        
        // Get order items with medicine names
        $stmt = $conn->prepare("
            SELECT oi.*, 
                   m.name as medicine_name, 
                   m.image, 
                   m.category,
                   (oi.quantity * oi.price) as subtotal
            FROM order_items oi 
            LEFT JOIN medicines m ON oi.medicine_id = m.id 
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order['id']);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        
        $orderItems = [];
        while ($row = $itemsResult->fetch_assoc()) {
            // Ensure medicine_name is set
            if (empty($row['medicine_name'])) {
                $row['medicine_name'] = 'Unknown Medicine';
            }
            // Ensure subtotal is calculated
            if (!isset($row['subtotal']) || $row['subtotal'] === null) {
                $row['subtotal'] = floatval($row['quantity']) * floatval($row['price']);
            }
            $orderItems[] = $row;
        }
        
        // Get order logs if table exists
        $orderLogs = [];
        if ($conn->query("SHOW TABLES LIKE 'order_logs'")->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT * FROM order_logs WHERE order_id = ? ORDER BY created_at ASC
            ");
            $stmt->bind_param("i", $order['id']);
            $stmt->execute();
            $logsResult = $stmt->get_result();
            
            while ($row = $logsResult->fetch_assoc()) {
                $orderLogs[] = $row;
            }
        }
        
        $order['items'] = $orderItems;
        $order['logs'] = $orderLogs;
        
        // Return order data directly
        json_response($order);
        
    } catch (Exception $e) {
        json_response(['error' => 'Error fetching order details: ' . $e->getMessage()], 500);
    }
}

function updateOrderStatus() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['status'])) {
        json_response(['success' => false, 'message' => 'Order ID and status required'], 400);
    }
    
    $orderId = $data['id'];
    $status = $data['status'];
    $notes = $data['notes'] ?? '';
    
    $validStatuses = ['Pending', 'Dispatched', 'Completed', 'Cancelled'];
    if (!in_array($status, $validStatuses)) {
        json_response(['success' => false, 'message' => 'Invalid status'], 400);
    }
    
    try {
        $conn->begin_transaction();
        
        // Get current order status and details
        $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentOrder = $result->fetch_assoc();
        
        if (!$currentOrder) {
            throw new Exception('Order not found');
        }
        
        $oldStatus = $currentOrder['status'];
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $orderId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Order not found or no changes made');
        }
        
        // Handle stock deduction when order is dispatched
        if ($status === 'Dispatched' && $oldStatus !== 'Dispatched') {
            // Get order details for receipt and customer update
            $stmt = $conn->prepare("
                SELECT order_id, customer_name, phone, address, total_amount, payment_method, created_at 
                FROM orders 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $orderResult = $stmt->get_result();
            $orderDetails = $orderResult->fetch_assoc();
            
            if (!$orderDetails) {
                throw new Exception('Order details not found');
            }
            
            // Get customer email from customers table if exists
            $customerEmail = '';
            $emailStmt = $conn->prepare("SELECT email FROM customers WHERE phone = ?");
            $emailStmt->bind_param("s", $orderDetails['phone']);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            if ($emailResult->num_rows > 0) {
                $customerData = $emailResult->fetch_assoc();
                $customerEmail = $customerData['email'] ?? '';
            }
            
            // Get order items to deduct stock
            $stmt = $conn->prepare("SELECT medicine_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $itemsResult = $stmt->get_result();
            
            while ($item = $itemsResult->fetch_assoc()) {
                // Check if medicine exists and has enough stock
                $checkStmt = $conn->prepare("SELECT quantity, name FROM medicines WHERE id = ?");
                $checkStmt->bind_param("i", $item['medicine_id']);
                $checkStmt->execute();
                $medicineResult = $checkStmt->get_result();
                $medicine = $medicineResult->fetch_assoc();
                
                if (!$medicine) {
                    throw new Exception('Medicine not found (ID: ' . $item['medicine_id'] . ')');
                }
                
                if ($medicine['quantity'] < $item['quantity']) {
                    throw new Exception('Insufficient stock for ' . $medicine['name'] . '. Available: ' . $medicine['quantity'] . ', Required: ' . $item['quantity']);
                }
                
                // Deduct from stock
                $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
                $stmt->bind_param("ii", $item['quantity'], $item['medicine_id']);
                $stmt->execute();
            }
            
            // Save receipt data to sales/transactions table (if exists)
            if ($conn->query("SHOW TABLES LIKE 'sales'")->num_rows > 0) {
                $receiptStmt = $conn->prepare("
                    INSERT INTO sales (
                        order_id, customer_name, phone, total_amount, 
                        payment_method, transaction_date, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $receiptStmt->bind_param(
                    "sssds",
                    $orderDetails['order_id'],
                    $orderDetails['customer_name'],
                    $orderDetails['phone'],
                    $orderDetails['total_amount'],
                    $orderDetails['payment_method']
                );
                $receiptStmt->execute();
            }
            
            // Update or insert customer data automatically
            $customerPhone = $orderDetails['phone'];
            $customerName = $orderDetails['customer_name'];
            $customerAddress = $orderDetails['address'] ?? '';
            
            // Check if customer exists
            $checkCustomer = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
            $checkCustomer->bind_param("s", $customerPhone);
            $checkCustomer->execute();
            $customerResult = $checkCustomer->get_result();
            
            // Check if required columns exist in customers table
            $checkColumns = $conn->query("SHOW COLUMNS FROM customers LIKE 'purchases'");
            $hasPurchasesColumn = $checkColumns->num_rows > 0;
            
            $checkColumns = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_purchase_date'");
            $hasLastPurchaseColumn = $checkColumns->num_rows > 0;
            
            $checkColumns = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'");
            $hasAddressColumn = $checkColumns->num_rows > 0;
            
            if ($customerResult->num_rows > 0) {
                // Update existing customer
                if ($hasPurchasesColumn && $hasLastPurchaseColumn && $hasAddressColumn) {
                    // Full update with all columns
                    $updateCustomer = $conn->prepare("
                        UPDATE customers 
                        SET name = ?, 
                            email = COALESCE(NULLIF(?, ''), email), 
                            address = COALESCE(NULLIF(?, ''), address),
                            purchases = COALESCE(purchases, 0) + 1,
                            last_purchase_date = NOW(),
                            updated_at = NOW()
                        WHERE phone = ?
                    ");
                    $updateCustomer->bind_param("ssss", $customerName, $customerEmail, $customerAddress, $customerPhone);
                } else {
                    // Basic update without new columns
                    $updateCustomer = $conn->prepare("
                        UPDATE customers 
                        SET name = ?, 
                            email = COALESCE(NULLIF(?, ''), email),
                            updated_at = NOW()
                        WHERE phone = ?
                    ");
                    $updateCustomer->bind_param("sss", $customerName, $customerEmail, $customerPhone);
                }
                $updateCustomer->execute();
            } else {
                // Insert new customer
                if ($hasPurchasesColumn && $hasLastPurchaseColumn && $hasAddressColumn) {
                    // Full insert with all columns
                    $insertCustomer = $conn->prepare("
                        INSERT INTO customers (
                            name, phone, email, address, purchases, 
                            last_purchase_date, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, 1, NOW(), NOW(), NOW())
                    ");
                    $insertCustomer->bind_param("ssss", $customerName, $customerPhone, $customerEmail, $customerAddress);
                } else {
                    // Basic insert without new columns
                    $insertCustomer = $conn->prepare("
                        INSERT INTO customers (name, phone, email, created_at, updated_at) 
                        VALUES (?, ?, ?, NOW(), NOW())
                    ");
                    $insertCustomer->bind_param("sss", $customerName, $customerPhone, $customerEmail);
                }
                $insertCustomer->execute();
            }
        }
        
        // Handle stock restoration if order is cancelled (only if it was previously dispatched)
        if ($status === 'Cancelled' && $oldStatus === 'Dispatched') {
            // Get order items to restore stock
            $stmt = $conn->prepare("SELECT medicine_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $itemsResult = $stmt->get_result();
            
            while ($item = $itemsResult->fetch_assoc()) {
                // Restore stock
                $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
                $stmt->bind_param("ii", $item['quantity'], $item['medicine_id']);
                $stmt->execute();
            }
        }
        
        // Log status change if table exists
        if ($conn->query("SHOW TABLES LIKE 'order_logs'")->num_rows > 0) {
            $logNotes = !empty($notes) ? $notes : "Status changed from $oldStatus to $status";
            $stmt = $conn->prepare("
                INSERT INTO order_logs (order_id, status, notes, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $orderId, $status, $logNotes);
            $stmt->execute();
        }
        
        $conn->commit();
        
        json_response([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'status' => $status,
            'old_status' => $oldStatus
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
                json_response(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()], 500);
    }
}

function deleteOrder() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if delete all orders
    if (isset($data['delete_all']) && $data['delete_all'] === true) {
        try {
            $conn->begin_transaction();
            
            // Get count of orders before deletion
            $countStmt = $conn->query("SELECT COUNT(*) as count FROM orders");
            $countResult = $countStmt->fetch_assoc();
            $deletedCount = $countResult['count'];
            
            // Delete all order items first (foreign key constraint)
            $conn->query("DELETE FROM order_items");
            
            // Delete all orders
            $conn->query("DELETE FROM orders");
            
            // Delete order logs if table exists
            if ($conn->query("SHOW TABLES LIKE 'order_logs'")->num_rows > 0) {
                $conn->query("DELETE FROM order_logs");
            }
            
            $conn->commit();
            
            json_response([
                'success' => true,
                'message' => "Successfully deleted $deletedCount orders",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Error deleting all orders: ' . $e->getMessage()], 500);
        }
        return;
    }
    
    // Delete single order
    if (!isset($data['id'])) {
        json_response(['success' => false, 'message' => 'Order ID required'], 400);
    }
    
    $orderId = $data['id'];
    
    try {
        $conn->begin_transaction();
        
        // Check if order exists
        $stmt = $conn->prepare("SELECT id, order_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Delete order items first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        
        // Delete order logs if table exists
        if ($conn->query("SHOW TABLES LIKE 'order_logs'")->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM order_logs WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
        }
        
        // Delete the order
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Order not found or already deleted');
        }
        
        $conn->commit();
        
        json_response([
            'success' => true,
            'message' => 'Order deleted successfully',
            'order_id' => $orderId,
            'order_number' => $order['order_id']
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        json_response(['success' => false, 'message' => 'Error deleting order: ' . $e->getMessage()], 500);
    }
}
?>

    }
}
?>