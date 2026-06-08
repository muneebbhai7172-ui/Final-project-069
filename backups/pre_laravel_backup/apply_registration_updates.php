<?php
// Apply database updates for registration system
include 'includes/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'updates' => []];

try {
    // Read the SQL file
    $sqlFile = 'database_registration_updates.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL update file not found: ' . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    $queries = explode(';', $sql);
    
    $updateCount = 0;
    $conn->autocommit(false); // Start transaction
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query) && !preg_match('/^--/', $query)) {
            try {
                $result = $conn->query($query);
                if ($result) {
                    $updateCount++;
                    if (stripos($query, 'CREATE TABLE') !== false) {
                        preg_match('/CREATE TABLE.*?`(\w+)`/', $query, $matches);
                        $tableName = $matches[1] ?? 'unknown';
                        $response['updates'][] = "Created table: $tableName";
                    } elseif (stripos($query, 'ALTER TABLE') !== false) {
                        preg_match('/ALTER TABLE.*?`(\w+)`/', $query, $matches);
                        $tableName = $matches[1] ?? 'unknown';
                        $response['updates'][] = "Updated table: $tableName";
                    } elseif (stripos($query, 'INSERT') !== false) {
                        $response['updates'][] = "Inserted default data";
                    } elseif (stripos($query, 'SELECT') !== false && stripos($query, 'Status') !== false) {
                        $response['updates'][] = "Database update completed successfully";
                    }
                }
            } catch (Exception $e) {
                // Skip errors for tables that might already exist
                if (stripos($e->getMessage(), 'already exists') === false && 
                    stripos($e->getMessage(), 'Duplicate column') === false) {
                    throw $e;
                }
                $response['updates'][] = "Skipped: " . substr($e->getMessage(), 0, 100) . "...";
            }
        }
    }
    
    $conn->commit();
    $conn->autocommit(true);
    
    // Verify the tables exist and check required columns
    $requiredTables = ['users', 'registration_attempts'];
    $existingTables = [];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $existingTables[] = $table;
        }
    }
    
    // Check if users table has required columns
    $result = $conn->query("DESCRIBE users");
    $userColumns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $userColumns[] = $row['Field'];
        }
    }
    
    $requiredColumns = ['id', 'username', 'email', 'password', 'first_name', 'last_name', 'phone', 'role', 'active', 'created_at'];
    $missingColumns = array_diff($requiredColumns, $userColumns);
    
    if (count($existingTables) === count($requiredTables) && empty($missingColumns)) {
        $response['success'] = true;
        $response['message'] = 'Database successfully updated for registration system!';
        $response['tables_verified'] = $existingTables;
        $response['user_columns'] = $userColumns;
        $response['total_updates'] = $updateCount;
    } else {
        $response['message'] = 'Some tables or columns are still missing';
        $response['existing_tables'] = $existingTables;
        $response['missing_columns'] = $missingColumns;
        $response['current_user_columns'] = $userColumns;
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    $response['message'] = 'Database update failed: ' . $e->getMessage();
    $response['error_details'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>