<?php
// Unlock All Locked Accounts Script
require_once 'includes/db_connect.php';

echo "=== Unlock All Locked Accounts ===\n";

try {
    // Find all locked accounts
    $stmt = $conn->prepare("SELECT id, username, email, failed_attempts FROM users WHERE account_locked = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        echo "Found " . $result->num_rows . " locked account(s):\n\n";
        
        while ($user = $result->fetch_assoc()) {
            echo "- {$user['email']} ({$user['username']}) - {$user['failed_attempts']} failed attempts\n";
        }
        
        echo "\nUnlocking all accounts...\n";
        
        // Unlock all accounts and reset failed attempts
        $updateStmt = $conn->prepare("UPDATE users SET account_locked = 0, failed_attempts = 0 WHERE account_locked = 1");
        
        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            
            // Clear all login attempts
            $clearStmt = $conn->prepare("DELETE FROM login_attempts");
            $clearStmt->execute();
            $clearStmt->close();
            
            echo "✅ SUCCESS: {$affectedRows} account(s) unlocked!\n";
            echo "✅ All rate limiting cleared\n";
            echo "✅ All users can now login immediately\n\n";
        } else {
            echo "❌ ERROR: Failed to unlock accounts\n";
        }
        
        $updateStmt->close();
    } else {
        echo "✅ No locked accounts found - all accounts are already unlocked!\n\n";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>