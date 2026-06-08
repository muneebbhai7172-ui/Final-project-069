<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../config/database.php';

try {
    $response = ['success' => false, 'message' => ''];
    
    // Get user info before destroying session
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    if ($user_id) {
        // Log the logout activity
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, 'logout', ?, ?, ?)
        ");
        
        $details = json_encode([
            'session_id' => $session_id,
            'logout_time' => date('Y-m-d H:i:s'),
            'logout_type' => 'manual'
        ]);
        
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("isss", $user_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        
        // Update user's last activity
        $update_stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        
        // Clear any remember-me tokens
        $token_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        $token_stmt->bind_param("i", $user_id);
        $token_stmt->execute();
        
        // Update session status if sessions table exists
        $session_stmt = $conn->prepare("
            UPDATE user_sessions 
            SET status = 'logged_out', ended_at = NOW() 
            WHERE user_id = ? AND session_id = ?
        ");
        if ($session_stmt) {
            $session_stmt->bind_param("is", $user_id, $session_id);
            $session_stmt->execute();
        }
    }
    
    // Clear all session data
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any authentication cookies
    $cookie_options = [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    
    setcookie('auth_token', '', $cookie_options);
    setcookie('remember_token', '', $cookie_options);
    setcookie('user_session', '', $cookie_options);
    
    $response['success'] = true;
    $response['message'] = 'Logged out successfully';
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'An error occurred during logout';
}

echo json_encode($response);
?>