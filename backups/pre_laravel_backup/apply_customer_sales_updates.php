<?php
/**
 * Apply Customer and Sales Database Updates
 * This script updates the database schema to support automatic customer and sales data
 * Run this once to update your database
 */

header('Content-Type: application/json');

include '../includes/db_connect.php';

$results = [];
$errors = [];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if customers table exists, create if not
    $sql = "CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL UNIQUE,
        `email` VARCHAR(255) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `purchases` INT DEFAULT 0 COMMENT 'Total number of purchases',
        `points` INT DEFAULT 0,
        `last_purchase_date` DATETIME NULL COMMENT 'Date of last purchase',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_phone` (`phone`),
        INDEX `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        $results[] = "Customers table ready";
    }
    
    // Function to add column if not exists
    function addColumnIfNotExists($conn, $table, $column, $definition) {
        $checkQuery = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = '$table' 
                       AND COLUMN_NAME = '$column'";
        $result = $conn->query($checkQuery);
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            $conn->query($sql);
            return true;
        }
        return false;
    }
    
    // Add email column
    if (addColumnIfNotExists($conn, 'customers', 'email', "VARCHAR(255) DEFAULT NULL")) {
        $results[] = "Added 'email' column to customers table";
    } else {
        $results[] = "'email' column already exists";
    }
    
    // Add purchases column
    if (addColumnIfNotExists($conn, 'customers', 'purchases', "INT DEFAULT 0 COMMENT 'Total number of purchases'")) {
        $results[] = "Added 'purchases' column to customers table";
    } else {
        $results[] = "'purchases' column already exists";
    }
    
    // Add last_purchase_date column
    if (addColumnIfNotExists($conn, 'customers', 'last_purchase_date', "DATETIME NULL COMMENT 'Date of last purchase'")) {
        $results[] = "Added 'last_purchase_date' column to customers table";
    } else {
        $results[] = "'last_purchase_date' column already exists";
    }
    
    // Add address column
    if (addColumnIfNotExists($conn, 'customers', 'address', "TEXT NULL COMMENT 'Customer address'")) {
        $results[] = "Added 'address' column to customers table";
    } else {
        $results[] = "'address' column already exists";
    }
    
    // Add updated_at column
    if (addColumnIfNotExists($conn, 'customers', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP")) {
        $results[] = "Added 'updated_at' column to customers table";
    } else {
        $results[] = "'updated_at' column already exists";
    }
    
    // Add created_at column
    if (addColumnIfNotExists($conn, 'customers', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")) {
        $results[] = "Added 'created_at' column to customers table";
    } else {
        $results[] = "'created_at' column already exists";
    }
    
    // Create sales table
    $sql = "CREATE TABLE IF NOT EXISTS `sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` VARCHAR(50) NOT NULL,
        `customer_name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `total_amount` DECIMAL(10, 2) NOT NULL,
        `payment_method` VARCHAR(50) DEFAULT 'cash_on_delivery',
        `transaction_date` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_order_id` (`order_id`),
        INDEX `idx_phone` (`phone`),
        INDEX `idx_transaction_date` (`transaction_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sales and receipt records'";
    
    if ($conn->query($sql)) {
        $results[] = "Created 'sales' table for receipt records";
    }
    
    // Update existing customers with order data
    $sql = "UPDATE customers c
            INNER JOIN (
                SELECT 
                    phone,
                    customer_name,
                    email,
                    address,
                    COUNT(*) as total_purchases,
                    MAX(created_at) as last_purchase
                FROM orders
                WHERE status = 'Dispatched' OR status = 'Completed'
                GROUP BY phone
            ) o ON c.phone = o.phone
            SET 
                c.purchases = COALESCE(c.purchases, 0) + o.total_purchases,
                c.last_purchase_date = o.last_purchase,
                c.name = COALESCE(NULLIF(c.name, ''), o.customer_name),
                c.email = COALESCE(NULLIF(c.email, ''), o.email),
                c.address = COALESCE(NULLIF(c.address, ''), o.address),
                c.updated_at = NOW()";
    
    if ($conn->query($sql)) {
        $affectedRows = $conn->affected_rows;
        $results[] = "Synced $affectedRows customer records with order data";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database updated successfully!',
        'results' => $results,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    $errors[] = $e->getMessage();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating database',
        'results' => $results,
        'errors' => $errors
    ]);
}

$conn->close();
?>
