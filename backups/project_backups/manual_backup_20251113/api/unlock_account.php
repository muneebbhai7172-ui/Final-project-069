<?php
// Account Unlock Script
header('Content-Type: application/json');

try {
    require_once dirname(__DIR__) . '/includes/db_connect.php';
    
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Valid email is required';
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, username, account_locked, failed_attempts FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Unlock account and reset failed attempts
                $updateStmt = $conn->prepare("UPDATE users SET account_locked = 0, failed_attempts = 0 WHERE email = ?");
                $updateStmt->bind_param("s", $email);
                
                if ($updateStmt->execute()) {
                    // Also clear login attempts from rate limiting
                    $clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
                    $clearStmt->bind_param("s", $email);
                    $clearStmt->execute();
                    $clearStmt->close();
                    
                    $response = [
                        'success' => true,
                        'message' => 'Account unlocked successfully! You can now try logging in again.',
                        'user' => [
                            'email' => $email,
                            'username' => $user['username'],
                            'was_locked' => (bool)$user['account_locked'],
                            'previous_failed_attempts' => $user['failed_attempts']
                        ]
                    ];
                } else {
                    $response['message'] = 'Failed to unlock account. Please try again.';
                }
                
                $updateStmt->close();
            } else {
                $response['message'] = 'No user found with this email address.';
            }
            
            $stmt->close();
        }
    } else {
        $response['message'] = 'Invalid request method';
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>