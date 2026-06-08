<?php
require_once 'config/db.php';

echo "Adding missing customer_id column to orders table...\n";

try {
    // Add customer_id column to orders table
    $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id INT(11) NULL AFTER id');
    echo "Successfully added customer_id column to orders table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "customer_id column already exists in orders table.\n";
    } else {
        echo "Error adding customer_id column: " . $e->getMessage() . "\n";
    }
}

echo "Creating sessions table for Laravel...\n";

try {
    $sessionTableSQL = "CREATE TABLE sessions (
        id VARCHAR(255) NOT NULL PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        payload LONGTEXT NOT NULL,
        last_activity INT(11) NOT NULL,
        INDEX(user_id),
        INDEX(last_activity)
    )";
    $pdo->exec($sessionTableSQL);
    echo "Successfully created sessions table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already exists') !== false) {
        echo "Sessions table already exists.\n";
    } else {
        echo "Error creating sessions table: " . $e->getMessage() . "\n";
    }
}

echo "Creating cache table for Laravel...\n";

try {
    $cacheTableSQL = "CREATE TABLE cache (
        `key` VARCHAR(255) NOT NULL PRIMARY KEY,
        value MEDIUMTEXT NOT NULL,
        expiration INT(11) NOT NULL,
        INDEX(expiration)
    )";
    $pdo->exec($cacheTableSQL);
    echo "Successfully created cache table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already exists') !== false) {
        echo "Cache table already exists.\n";
    } else {
        echo "Error creating cache table: " . $e->getMessage() . "\n";
    }
}

echo "Creating cache_locks table for Laravel...\n";

try {
    $cacheLocksTableSQL = "CREATE TABLE cache_locks (
        `key` VARCHAR(255) NOT NULL PRIMARY KEY,
        owner VARCHAR(255) NOT NULL,
        expiration INT(11) NOT NULL
    )";
    $pdo->exec($cacheLocksTableSQL);
    echo "Successfully created cache_locks table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already exists') !== false) {
        echo "Cache locks table already exists.\n";
    } else {
        echo "Error creating cache_locks table: " . $e->getMessage() . "\n";
    }
}

echo "Updating existing orders to link with customers...\n";

try {
    // Try to link existing orders with customers based on phone number
    $updateSQL = "UPDATE orders o
                  JOIN customers c ON o.phone = c.phone
                  SET o.customer_id = c.id
                  WHERE o.customer_id IS NULL";
    $stmt = $pdo->prepare($updateSQL);
    $stmt->execute();
    $updatedRows = $stmt->rowCount();
    echo "Successfully linked $updatedRows existing orders with customers.\n";
} catch (Exception $e) {
    echo "Error updating existing orders: " . $e->getMessage() . "\n";
}

echo "All database updates completed!\n";
?>
