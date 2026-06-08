<?php
// Database Settings Updates Script
// This script applies the necessary database changes for the enhanced settings functionality

require_once 'includes/db_connect.php';

function executeUpdates($conn) {
    $updates = [
        // Add missing columns to user_settings table
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS passkey_enabled BOOLEAN DEFAULT FALSE AFTER two_factor_enabled",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS biometric_enabled BOOLEAN DEFAULT FALSE AFTER passkey_enabled",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS analytics_enabled BOOLEAN DEFAULT FALSE AFTER language",
        
        // Add phone column to users table
        "ALTER TABLE users 
         ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email",
        
        // Add last_activity column to users table
        "ALTER TABLE users 
         ADD COLUMN IF NOT EXISTS last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        
        // Add password_changed_at column to users table
        "ALTER TABLE users 
         ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL",
        
        // Add status column to users table
        "ALTER TABLE users 
         ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'suspended', 'deleted') DEFAULT 'active'",
        
        // Add additional notification columns
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS push_notifications BOOLEAN DEFAULT TRUE AFTER security_notifications",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS sms_notifications BOOLEAN DEFAULT FALSE AFTER push_notifications",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS login_notifications BOOLEAN DEFAULT TRUE AFTER sms_notifications",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS session_timeout_minutes INT DEFAULT 30 AFTER login_notifications",
        
        "ALTER TABLE user_settings 
         ADD COLUMN IF NOT EXISTS auto_logout_enabled BOOLEAN DEFAULT FALSE AFTER session_timeout_minutes"
    ];
    
    $table_creates = [
        // Create activity_logs table
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            details JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_action (user_id, action),
            INDEX idx_created_at (created_at)
        )",
        
        // Create user_sessions table
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('active', 'logged_out', 'expired', 'invalidated', 'cleared') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ended_at TIMESTAMP NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_session (session_id),
            INDEX idx_user_status (user_id, status),
            INDEX idx_last_activity (last_activity)
        )",
        
        // Create user_preferences table
        "CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            preference_key VARCHAR(100) NOT NULL,
            preference_value TEXT,
            data_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_preference (user_id, preference_key),
            INDEX idx_preference_key (preference_key)
        )",
        
        // Create password_history table
        "CREATE TABLE IF NOT EXISTS password_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_created (user_id, created_at)
        )",
        
        // Create login_attempts table
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50),
            email VARCHAR(100),
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            success BOOLEAN NOT NULL,
            failure_reason VARCHAR(100),
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_attempted (ip_address, attempted_at),
            INDEX idx_username_attempted (username, attempted_at),
            INDEX idx_email_attempted (email, attempted_at)
        )"
    ];
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
        "CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)",
        "CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id)"
    ];
    
    $data_updates = [
        // Insert default settings for existing users
        "INSERT IGNORE INTO user_settings (user_id)
         SELECT id FROM users 
         WHERE id NOT IN (SELECT user_id FROM user_settings WHERE user_id IS NOT NULL)",
        
        // Update null values to defaults
        "UPDATE user_settings 
         SET 
             passkey_enabled = COALESCE(passkey_enabled, FALSE),
             biometric_enabled = COALESCE(biometric_enabled, FALSE),
             analytics_enabled = COALESCE(analytics_enabled, FALSE),
             push_notifications = COALESCE(push_notifications, TRUE),
             sms_notifications = COALESCE(sms_notifications, FALSE),
             login_notifications = COALESCE(login_notifications, TRUE),
             session_timeout_minutes = COALESCE(session_timeout_minutes, 30),
             auto_logout_enabled = COALESCE(auto_logout_enabled, FALSE)"
    ];
    
    $all_queries = array_merge($updates, $table_creates, $indexes, $data_updates);
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($all_queries as $query) {
        try {
            if ($conn->query($query)) {
                $success_count++;
                echo "✓ Query executed successfully\n";
            } else {
                $error_count++;
                $error = $conn->error;
                $errors[] = $error;
                echo "✗ Query failed: $error\n";
            }
        } catch (Exception $e) {
            $error_count++;
            $errors[] = $e->getMessage();
            echo "✗ Exception: " . $e->getMessage() . "\n";
        }
    }
    
    return [
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors,
        'total_queries' => count($all_queries)
    ];
}

// Check if script is run from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Database Settings Updates</title></head><body>";
    echo "<h1>Database Settings Updates</h1>";
    echo "<pre>";
}

echo "Starting database settings updates...\n\n";

try {
    $results = executeUpdates($conn);
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "DATABASE UPDATE SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total queries: " . $results['total_queries'] . "\n";
    echo "Successful: " . $results['success_count'] . "\n";
    echo "Failed: " . $results['error_count'] . "\n";
    
    if ($results['error_count'] > 0) {
        echo "\nErrors encountered:\n";
        foreach ($results['errors'] as $i => $error) {
            echo ($i + 1) . ". $error\n";
        }
    }
    
    if ($results['error_count'] === 0) {
        echo "\n✅ All database updates completed successfully!\n";
        echo "The enhanced settings functionality is now ready to use.\n";
    } else {
        echo "\n⚠️  Some updates failed. Please review the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
}

if (!$is_cli) {
    echo "</pre>";
    echo "<p><a href='admin/dashboard.html'>Go to Dashboard</a> | <a href='admin/settings.html'>Go to Settings</a></p>";
    echo "</body></html>";
}

echo "\nDone.\n";
?>