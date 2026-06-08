<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$action = $_POST['action'] ?? '';

if ($action === 'preview') {
    handlePreview();
} elseif ($action === 'import') {
    handleImport();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handlePreview() {
    $file = $_FILES['excel_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }

    // Check file type (accept CSV and Excel files)
    $allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (!in_array($file['type'], $allowedTypes) && !in_array(strtolower($fileExtension), ['csv', 'xls', 'xlsx'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a CSV or Excel file.']);
        return;
    }

    try {
        $data = [];
        
        if (strtolower($fileExtension) === 'csv' || $file['type'] === 'text/csv' || $file['type'] === 'application/csv') {
            // Handle CSV files
            if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ","); // Skip header row
                
                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Skip empty rows
                    if (empty(array_filter($row))) continue;

                    $medicine = [
                        'name' => trim($row[0] ?? ''),
                        'category' => trim($row[1] ?? ''),
                        'price' => trim($row[2] ?? ''),
                        'quantity' => trim($row[3] ?? ''),
                        'description' => trim($row[4] ?? ''),
                        'expiry_date' => trim($row[5] ?? '')
                    ];

                    // Basic validation
                    if (empty($medicine['name'])) continue;

                    $data[] = $medicine;
                }
                fclose($handle);
            }
        } else {
            // For Excel files, convert to simple array parsing
            echo json_encode(['success' => false, 'message' => 'Excel files not supported. Please convert to CSV format.']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $data]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error reading file: ' . $e->getMessage()]);
    }
}

function handleImport() {
    global $pdo;

    $file = $_FILES['excel_file'] ?? null;
    $skipDuplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1';

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }

    try {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        // Prepare statement for checking duplicates
        $checkStmt = $pdo->prepare("SELECT id FROM medicines WHERE name = ?");

        // Prepare statement for insertion
        $insertStmt = $pdo->prepare("
            INSERT INTO medicines (name, category, price, quantity, description, expiry_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($fileExtension) === 'csv' || $file['type'] === 'text/csv') {
            if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ","); // Skip header row
                
                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Skip empty rows
                    if (empty(array_filter($row))) continue;

                    $name = trim($row[0] ?? '');
                    $category = trim($row[1] ?? '');
                    $price = trim($row[2] ?? '');
                    $quantity = trim($row[3] ?? '');
                    $description = trim($row[4] ?? '');
                    $expiry_date = trim($row[5] ?? '');

                    // Validate required fields
                    if (empty($name)) {
                        $errors++;
                        continue;
                    }

                    // Validate price and quantity
                    $price = floatval($price);
                    $quantity = intval($quantity);

                    if ($price <= 0 || $quantity < 0) {
                        $errors++;
                        continue;
                    }

                    // Validate expiry date
                    if (!empty($expiry_date)) {
                        $expiry_date = date('Y-m-d', strtotime($expiry_date));
                        if (!$expiry_date) {
                            $expiry_date = null;
                        }
                    } else {
                        $expiry_date = null;
                    }

                    // Check for duplicates if requested
                    if ($skipDuplicates) {
                        $checkStmt->execute([$name]);
                        if ($checkStmt->fetch()) {
                            $skipped++;
                            continue;
                        }
                    }

                    // Insert medicine
                    try {
                        $insertStmt->execute([$name, $category, $price, $quantity, $description, $expiry_date]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors++;
                    }
                }
                fclose($handle);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Excel files not supported. Please convert to CSV format.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
    }
}
?>