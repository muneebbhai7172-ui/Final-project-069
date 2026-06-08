<?php
/**
 * Create Bills Table - One-time Setup Script
 * Run this file once to create the bills table in your database
 */

require_once 'config/database.php';

try {
    echo "Creating bills table...\n";
    
    // Create bills table
    $sql = "
    CREATE TABLE IF NOT EXISTS `bills` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invoice_number` varchar(50) NOT NULL,
      `customer_id` int(11) DEFAULT NULL,
      `customer_name` varchar(255) NOT NULL,
      `customer_phone` varchar(20) DEFAULT NULL,
      `customer_address` text DEFAULT NULL,
      `customer_type` enum('walk-in','registered') DEFAULT 'walk-in',
      `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
      `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
      `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
      `total` decimal(10,2) NOT NULL DEFAULT 0.00,
      `items` text NOT NULL COMMENT 'JSON encoded cart items',
      `payment_method` varchar(50) DEFAULT 'cash',
      `payment_status` enum('paid','pending','partial') DEFAULT 'paid',
      `cashier_id` int(11) DEFAULT 1,
      `notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `invoice_number` (`invoice_number`),
      KEY `customer_id` (`customer_id`),
      KEY `created_at` (`created_at`),
      KEY `customer_type` (`customer_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($sql);
    echo "✅ Bills table created successfully!\n\n";
    
    // Create index
    echo "Creating indexes...\n";
    $sql = "CREATE INDEX IF NOT EXISTS idx_invoice_date ON bills(invoice_number, created_at)";
    $pdo->exec($sql);
    echo "✅ Indexes created successfully!\n\n";
    
    // Try to add foreign key (may fail if customers table doesn't exist)
    echo "Adding foreign key constraint...\n";
    try {
        $sql = "
        ALTER TABLE `bills` 
        ADD CONSTRAINT `fk_bills_customer` 
        FOREIGN KEY (`customer_id`) 
        REFERENCES `customers` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE;
        ";
        $pdo->exec($sql);
        echo "✅ Foreign key constraint added successfully!\n\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: Could not add foreign key constraint. This is OK if customers table doesn't exist yet.\n";
        echo "   Error: " . $e->getMessage() . "\n\n";
    }
    
    // Verify table creation
    $stmt = $pdo->query("SHOW TABLES LIKE 'bills'");
    if ($stmt->rowCount() > 0) {
        echo "✅ SUCCESS! Bills table is ready to use.\n\n";
        
        // Show table structure
        echo "Table structure:\n";
        echo "================\n";
        $stmt = $pdo->query("DESCRIBE bills");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n", $col['Field'], $col['Type'], $col['Key'] ? '[' . $col['Key'] . ']' : '');
        }
        echo "\n";
        
        echo "You can now use the billing system!\n";
        echo "Go to: admin/modern-billing.html\n";
    } else {
        echo "❌ ERROR: Bills table was not created.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Your database connection in config/database.php\n";
    echo "2. That your database user has CREATE TABLE permissions\n";
    echo "3. That your database exists and is accessible\n";
}
