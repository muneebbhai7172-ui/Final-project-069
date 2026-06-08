<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            exit;
        }
        
        handleBillSave($data);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Legacy order bill generation
        if (isset($_GET['id'])) {
            generateOrderBill($_GET['id']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order ID required']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function handleBillSave($data) {
    global $pdo;

    if (empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Extract customer data
        $customer = $data['customer'] ?? [];
        $customerType = $customer['type'] ?? 'walk-in';
        $customerId = null;
        $customerName = 'Walk-in Customer';
        $customerPhone = null;
        $customerAddress = null;

        // Handle walk-in customer
        if ($customerType === 'walk-in') {
            $customerName = $customer['name'] ?? 'Walk-in Customer';
            $customerPhone = $customer['phone'] ?? null;
            $customerAddress = $customer['address'] ?? null;
            $saveAsCustomer = $customer['save_as_customer'] ?? false;

            // Save walk-in customer to database if requested
            if ($saveAsCustomer && $customerName) {
                // Check if customer already exists by phone (if provided) or name
                if ($customerPhone) {
                    $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
                    $stmt->execute([$customerPhone]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
                    $stmt->execute([$customerName]);
                }
                
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (name, phone, email, address, points, created_at)
                        VALUES (?, ?, ?, ?, 0, NOW())
                    ");
                    $stmt->execute([
                        $customerName,
                        $customerPhone,
                        $customer['email'] ?? null,
                        $customerAddress
                    ]);
                    $customerId = $pdo->lastInsertId();
                } else {
                    $customerId = $existing['id'];
                }
            }
        } else {
            // Registered customer
            $customerId = $customer['customer_id'] ?? null;
            if ($customerId) {
                $stmt = $pdo->prepare("SELECT name, phone, address FROM customers WHERE id = ?");
                $stmt->execute([$customerId]);
                $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customerData) {
                    $customerName = $customerData['name'];
                    $customerPhone = $customerData['phone'];
                    $customerAddress = $customerData['address'];
                }
            }
        }

        // Prepare bill data
        $invoiceNumber = $data['invoice_number'] ?? 'INV-' . date('YmdHis');
        $subtotal = $data['subtotal'] ?? 0;
        $discount = $data['discount'] ?? 0;
        $tax = $data['tax'] ?? 0;
        $total = $data['total'] ?? 0;
        $itemsJson = json_encode($data['items']);

        // Insert bill record (adjust table name as needed)
        $stmt = $pdo->prepare("
            INSERT INTO bills (invoice_number, customer_id, customer_name, customer_phone, customer_address, 
                             customer_type, subtotal, discount, tax, total, items, created_at, cashier_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $invoiceNumber,
            $customerId,
            $customerName,
            $customerPhone,
            $customerAddress,
            $customerType,
            $subtotal,
            $discount,
            $tax,
            $total,
            $itemsJson
        ]);

        $billId = $pdo->lastInsertId();

        // Update medicine stock
        foreach ($data['items'] as $item) {
            $stmt = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['id']]);
        }

        // Update customer points if registered customer
        if ($customerId) {
            $pointsEarned = floor($total / 100); // 1 point per Rs. 100 spent
            $stmt = $pdo->prepare("UPDATE customers SET points = points + ? WHERE id = ?");
            $stmt->execute([$pointsEarned, $customerId]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'bill_id' => $billId, 
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customerId,
            'message' => 'Bill saved successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save bill: ' . $e->getMessage()]);
    }
}

function generateBillHTML($cart, $customerType, $customer, $isSaved = false) {
    $billId = $isSaved ? 'BILL-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) : 'PREVIEW';
    $date = date('Y-m-d H:i:s');

    $html = "
    <div class='bill-container' style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; background: white;'>
        <div class='bill-header' style='text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;'>
            <h2 style='margin: 0;'>PharmaCare Pharmacy</h2>
            <p style='margin: 5px 0;'>123 Health Street, Medical City, MC 12345</p>
            <p style='margin: 5px 0;'>Phone: (555) 123-4567 | Email: info@pharmacare.com</p>
            <p style='margin: 5px 0; font-size: 14px;'>Bill ID: {$billId} | Date: {$date}</p>
        </div>

        <div class='customer-info' style='margin-bottom: 20px;'>
            <h4>Customer Information</h4>
            <p><strong>Type:</strong> " . ucfirst($customerType) . "</p>
            <p><strong>Name:</strong> " . ($customer ? $customer['name'] : 'Walk-in Customer') . "</p>";

    if ($customer) {
        $html .= "<p><strong>Phone:</strong> {$customer['phone']}</p>";
        $html .= "<p><strong>Points:</strong> {$customer['points']}</p>";
    }

    $html .= "
        </div>

        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
            <thead>
                <tr style='background-color: #f8f9fa;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Medicine</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: center;'>Qty</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Price</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Total</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($cart['items'] as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $html .= "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$item['name']}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$item['quantity']}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>$" . number_format($item['price'], 2) . "</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>$" . number_format($itemTotal, 2) . "</td>
                </tr>";
    }

    $html .= "
            </tbody>
        </table>

        <div class='bill-total' style='text-align: right; margin-bottom: 20px;'>
            <p style='margin: 5px 0;'>Subtotal: $" . number_format($cart['subtotal'], 2) . "</p>
            <p style='margin: 5px 0;'>Discount: $" . number_format($cart['discount'], 2) . "</p>
            <hr style='border: none; border-top: 1px solid #ccc; margin: 10px 0;'>
            <strong style='font-size: 18px;'>Total: $" . number_format($cart['total'], 2) . "</strong>
        </div>

        <div class='bill-footer' style='text-align: center; border-top: 1px solid #ccc; padding-top: 10px;'>
            <p style='margin: 5px 0;'>Thank you for your business!</p>
            <p style='margin: 5px 0; font-size: 12px;'>Pharmacy License: PH-2024-001 | Cashier: Admin</p>
        </div>
    </div>";

    return $html;
}

function generateOrderBill($orderId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, m.name FROM order_items oi JOIN medicines m ON oi.medicine_id = m.id WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cart = [
        'items' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ];
        }, $items),
        'subtotal' => $order['total_amount'],
        'discount' => 0,
        'total' => $order['total_amount']
    ];

    $html = generateBillHTML($cart, 'regular', [
        'name' => $order['customer_name'],
        'phone' => $order['phone']
    ], true);

    echo json_encode(['success' => true, 'html' => $html]);
}
?>