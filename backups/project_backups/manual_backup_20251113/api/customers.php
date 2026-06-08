<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/database.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['phone'])) {
                // Find customer by phone (for POS)
                $stmt = $pdo->prepare("SELECT id, name, phone, email, points FROM customers WHERE phone = ?");
                $stmt->execute([$_GET['phone']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($customer) {
                    echo json_encode(['success' => true, 'data' => $customer]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Customer not found']);
                }
            } elseif (isset($_GET['id'])) {
                // Get specific customer by ID
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($customer) {
                    echo json_encode($customer);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Customer not found']);
                }
            } else {
                // Get all customers with optional search filters
                $where = [];
                $params = [];

                if (!empty($_GET['search'])) {
                    $where[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
                    $searchTerm = "%" . $_GET['search'] . "%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }

                if (!empty($_GET['name'])) {
                    $where[] = "name LIKE ?";
                    $params[] = "%" . $_GET['name'] . "%";
                }

                if (!empty($_GET['phone'])) {
                    $where[] = "phone LIKE ?";
                    $params[] = "%" . $_GET['phone'] . "%";
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

                $stmt = $pdo->prepare("SELECT * FROM customers $whereClause ORDER BY name LIMIT " . intval($limit));
                $stmt->execute($params);
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'customers' => $customers]);
            }
            break;

        case 'POST':
            // Create new customer
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['name']) || !isset($data['phone'])) {
                echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
                break;
            }

            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
            $stmt->execute([$data['phone']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Phone number already exists']);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO customers (name, phone, email, points)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['email'] ?? null,
                $data['points'] ?? 0
            ]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            // Update customer
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? null;

            if (!$id || !isset($data['name']) || !isset($data['phone'])) {
                echo json_encode(['success' => false, 'message' => 'ID, name and phone are required']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE customers
                SET name = ?, phone = ?, email = ?, points = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['email'] ?? null,
                $data['points'] ?? 0,
                $id
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete customer
            $id = $_GET['id'] ?? null;

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Customer ID required']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>