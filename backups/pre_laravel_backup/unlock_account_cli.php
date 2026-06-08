<?php
// Quick Account Unlock Script (Command Line)
require_once 'includes/db_connect.php';

echo "=== Account Unlock Tool ===\n";

if ($argc > 1) {
    $email = $argv[1];
} else {
    echo "Enter email address to unlock: ";
    $email = trim(fgets(STDIN));
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email address\n";
    exit(1);
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username, account_locked, failed_attempts FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        echo "\nUser found:\n";
        echo "- Email: {$email}\n";
        echo "- Username: {$user['username']}\n";
        echo "- Account Locked: " . ($user['account_locked'] ? 'YES' : 'NO') . "\n";
        echo "- Failed Attempts: {$user['failed_attempts']}\n\n";
        
        // Unlock account and reset failed attempts
        $updateStmt = $conn->prepare("UPDATE users SET account_locked = 0, failed_attempts = 0 WHERE email = ?");
        $updateStmt->bind_param("s", $email);
        
        if ($updateStmt->execute()) {
            // Also clear login attempts from rate limiting
            $clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
            $clearStmt->bind_param("s", $email);
            $clearStmt->execute();
            $clearStmt->close();
            
            echo "✅ SUCCESS: Account unlocked and failed attempts reset!\n";
            echo "✅ Rate limiting cleared for this email\n";
            echo "✅ User can now login immediately\n\n";
        } else {
            echo "❌ ERROR: Failed to unlock account\n";
        }
        
        $updateStmt->close();
    } else {
        echo "❌ ERROR: No user found with email: {$email}\n";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>