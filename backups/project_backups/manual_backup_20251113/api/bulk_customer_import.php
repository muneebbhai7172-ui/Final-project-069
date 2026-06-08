<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

// Enable error logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', '../logs/import_errors.log');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['customers']) || !is_array($data['customers'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format. Expected customers array.']);
        exit;
    }

    $customers = $data['customers'];
    $imported = 0;
    $updated = 0;
    $failed = 0;
    $errors = [];
    $batchSize = isset($data['batchSize']) ? intval($data['batchSize']) : count($customers);

    // Validate database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $pdo->beginTransaction();

    foreach ($customers as $index => $customer) {
        try {
            // Validate required fields
            $name = trim($customer['Name'] ?? '');
            $phone = trim($customer['Phone'] ?? '');
            $email = trim($customer['Email'] ?? '');
            $address = trim($customer['Address'] ?? '');

            if (empty($name)) {
                $errors[] = [
                    'row' => $index + 1,
                    'message' => 'Name is required',
                    'data' => $customer
                ];
                $failed++;
                continue;
            }

            if (empty($phone)) {
                $errors[] = [
                    'row' => $index + 1,
                    'message' => 'Phone is required',
                    'data' => $customer
                ];
                $failed++;
                continue;
            }

            // Validate phone format (basic validation)
            $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);
            
            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id, name, email, address FROM customers WHERE phone = ?");
            $stmt->execute([$phone]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing customer
                $updateStmt = $pdo->prepare("
                    UPDATE customers 
                    SET name = ?, 
                        email = ?, 
                        address = ?,
                        updated_at = NOW()
                    WHERE phone = ?
                ");
                $result = $updateStmt->execute([
                    $name,
                    $email ?: null,
                    $address ?: null,
                    $phone
                ]);
                
                if ($result) {
                    $updated++;
                } else {
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => 'Failed to update customer',
                        'data' => $customer
                    ];
                    $failed++;
                }
            } else {
                // Insert new customer
                $insertStmt = $pdo->prepare("
                    INSERT INTO customers (name, phone, email, address, points, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 0, NOW(), NOW())
                ");
                $result = $insertStmt->execute([
                    $name,
                    $phone,
                    $email ?: null,
                    $address ?: null
                ]);
                
                if ($result) {
                    $imported++;
                } else {
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => 'Failed to insert customer',
                        'data' => $customer
                    ];
                    $failed++;
                }
            }

        } catch (PDOException $e) {
            $errors[] = [
                'row' => $index + 1,
                'message' => $e->getMessage(),
                'data' => $customer
            ];
            $failed++;
            error_log("Import error on row " . ($index + 1) . ": " . $e->getMessage());
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'updated' => $updated,
        'failed' => $failed,
        'total' => count($customers),
        'errors' => $errors,
        'message' => "Import completed: {$imported} new, {$updated} updated, {$failed} failed"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Import exception: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage(),
        'imported' => 0,
        'updated' => 0,
        'failed' => 0,
        'errors' => []
    ]);
}
?>
