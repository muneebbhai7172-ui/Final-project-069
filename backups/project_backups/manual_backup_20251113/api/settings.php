<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getUserSettings($conn, $user_id);
            break;
            
        case 'users':
            getAllUsers($conn);
            break;
            
        case 'user':
            getSingleUser($conn);
            break;
            
        case 'add_user':
            addUser($conn);
            break;
            
        case 'update_user':
            updateUser($conn);
            break;
            
        case 'delete_user':
            deleteUser($conn);
            break;
            
        case 'update_account':
            updateAccountInfo($conn, $user_id);
            break;
            
        case 'change_password':
            changePassword($conn, $user_id);
            break;
            
        case 'update_notifications':
            updateNotificationPreferences($conn, $user_id);
            break;
            
        case 'update_system':
            updateSystemPreferences($conn, $user_id);
            break;
            
        case 'update_passkey':
            updatePasskeySettings($conn, $user_id);
            break;
            
        case 'clear_sessions':
            clearAllSessions($conn, $user_id);
            break;
            
        case 'delete_account':
            deleteAccount($conn, $user_id);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Settings API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAllUsers($conn) {
    $stmt = $conn->query("
        SELECT id, username, full_name, email, role, status, created_at 
        FROM users 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetch_all(MYSQLI_ASSOC);
    echo json_encode($users);
}

function getSingleUser($conn) {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT id, username, full_name, email, role, status 
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}

function addUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? '');
    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'staff';
    
    if (empty($username) || empty($fullName) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Check if username or email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        return;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, full_name, email, password, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->bind_param("sssss", $username, $fullName, $email, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding user']);
    }
}

function updateUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $username = trim($data['username'] ?? '');
    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = $data['role'] ?? 'staff';
    $password = $data['password'] ?? '';
    
    if ($id <= 0 || empty($username) || empty($fullName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Check if username or email already exists for another user
    $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->bind_param("ssi", $username, $email, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        return;
    }
    
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, full_name = ?, email = ?, password = ?, role = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $username, $fullName, $email, $hashedPassword, $role, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, full_name = ?, email = ?, role = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $username, $fullName, $email, $role, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating user']);
    }
}

function deleteUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Don't allow deleting yourself
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting user']);
    }
}

function getUserSettings($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT u.*, us.* 
        FROM users u 
        LEFT JOIN user_settings us ON u.id = us.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Get last login info
        $login_stmt = $conn->prepare("
            SELECT created_at 
            FROM activity_logs 
            WHERE user_id = ? AND action = 'login' 
            ORDER BY created_at DESC 
            LIMIT 1, 1
        ");
        $login_stmt->bind_param("i", $user_id);
        $login_stmt->execute();
        $login_result = $login_stmt->get_result();
        $last_login = $login_result->fetch_assoc();
        
        // Get passkey count
        $passkey_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_webauthn WHERE user_id = ?");
        $passkey_stmt->bind_param("i", $user_id);
        $passkey_stmt->execute();
        $passkey_result = $passkey_stmt->get_result();
        $passkey_count = $passkey_result->fetch_assoc()['count'];
        
        $response_data = [
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'theme_preference' => $user['theme_preference'] ?? 'light',
            'email_notifications' => (bool)($user['email_notifications'] ?? true),
            'security_notifications' => (bool)($user['security_notifications'] ?? true),
            'timezone' => $user['timezone'] ?? 'UTC',
            'language' => $user['language'] ?? 'en',
            'passkey_enabled' => (bool)($user['passkey_enabled'] ?? false),
            'biometric_enabled' => (bool)($user['biometric_enabled'] ?? false),
            'analytics_enabled' => (bool)($user['analytics_enabled'] ?? false),
            'account_status' => $user['status'] ?? 'active',
            'last_login' => $last_login ? date('M j, Y g:i A', strtotime($last_login['created_at'])) : 'Never',
            'passkey_count' => $passkey_count,
            'created_at' => date('M j, Y', strtotime($user['created_at']))
        ];
        
        echo json_encode(['success' => true, 'data' => $response_data]);
    } else {
        throw new Exception('User not found');
    }
}

function updateAccountInfo($conn, $user_id) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        throw new Exception('Full name and email are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if email is already taken by another user
    $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $email_check->bind_param("si", $email, $user_id);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        throw new Exception('Email is already in use by another account');
    }
    
    $conn->begin_transaction();
    
    try {
        // Update user info
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
        $stmt->execute();
        
        // Log the activity
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, 'account_update', ?, ?)
        ");
        $details = json_encode(['fields_updated' => ['full_name', 'email', 'phone']]);
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->bind_param("iss", $user_id, $details, $ip_address);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Account information updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function changePassword($conn, $user_id) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        throw new Exception('Current password and new password are required');
    }
    
    if (strlen($new_password) < 8) {
        throw new Exception('New password must be at least 8 characters long');
    }
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }
    
    $conn->begin_transaction();
    
    try {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        $update_stmt->execute();
        
        // Invalidate all existing sessions except current
        $session_stmt = $conn->prepare("
            UPDATE user_sessions 
            SET status = 'invalidated' 
            WHERE user_id = ? AND session_id != ?
        ");
        $current_session = session_id();
        $session_stmt->bind_param("is", $user_id, $current_session);
        $session_stmt->execute();
        
        // Clear all remember-me tokens
        $token_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        $token_stmt->bind_param("i", $user_id);
        $token_stmt->execute();
        
        // Log the activity
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, 'password_change', ?, ?)
        ");
        $details = json_encode(['change_time' => date('Y-m-d H:i:s')]);
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->bind_param("iss", $user_id, $details, $ip_address);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateNotificationPreferences($conn, $user_id) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $security_notifications = isset($_POST['security_notifications']) ? 1 : 0;
    $timezone = $_POST['timezone'] ?? 'UTC';
    $language = $_POST['language'] ?? 'en';
    
    // Validate timezone
    $valid_timezones = timezone_identifiers_list();
    if (!in_array($timezone, $valid_timezones)) {
        $timezone = 'UTC';
    }
    
    // Validate language
    $valid_languages = ['en', 'ur', 'es', 'fr', 'de'];
    if (!in_array($language, $valid_languages)) {
        $language = 'en';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO user_settings (user_id, email_notifications, security_notifications, timezone, language) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        email_notifications = VALUES(email_notifications),
        security_notifications = VALUES(security_notifications),
        timezone = VALUES(timezone),
        language = VALUES(language)
    ");
    $stmt->bind_param("iiiss", $user_id, $email_notifications, $security_notifications, $timezone, $language);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully']);
}

function updateSystemPreferences($conn, $user_id) {
    $theme_preference = $_POST['theme_preference'] ?? 'light';
    $analytics_enabled = isset($_POST['analytics_enabled']) ? 1 : 0;
    
    // Validate theme preference
    $valid_themes = ['light', 'dark', 'auto'];
    if (!in_array($theme_preference, $valid_themes)) {
        $theme_preference = 'light';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO user_settings (user_id, theme_preference, analytics_enabled) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        theme_preference = VALUES(theme_preference),
        analytics_enabled = VALUES(analytics_enabled)
    ");
    $stmt->bind_param("isi", $user_id, $theme_preference, $analytics_enabled);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'System preferences updated successfully']);
}

function updatePasskeySettings($conn, $user_id) {
    $passkey_enabled = isset($_POST['passkey_enabled']) ? 1 : 0;
    $biometric_enabled = isset($_POST['biometric_enabled']) ? 1 : 0;
    
    $stmt = $conn->prepare("
        INSERT INTO user_settings (user_id, passkey_enabled, biometric_enabled) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        passkey_enabled = VALUES(passkey_enabled),
        biometric_enabled = VALUES(biometric_enabled)
    ");
    $stmt->bind_param("iii", $user_id, $passkey_enabled, $biometric_enabled);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Passkey settings updated successfully']);
}

function clearAllSessions($conn, $user_id) {
    $current_session = session_id();
    
    $conn->begin_transaction();
    
    try {
        // Invalidate all sessions except current
        $stmt = $conn->prepare("
            UPDATE user_sessions 
            SET status = 'cleared', ended_at = NOW() 
            WHERE user_id = ? AND session_id != ?
        ");
        $stmt->bind_param("is", $user_id, $current_session);
        $stmt->execute();
        
        // Clear all remember-me tokens
        $token_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        $token_stmt->bind_param("i", $user_id);
        $token_stmt->execute();
        
        // Log the activity
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, 'sessions_cleared', ?, ?)
        ");
        $details = json_encode(['cleared_time' => date('Y-m-d H:i:s')]);
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->bind_param("iss", $user_id, $details, $ip_address);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'All sessions cleared successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteAccount($conn, $user_id) {
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        throw new Exception('Password is required to delete account');
    }
    
    // Verify password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Incorrect password');
    }
    
    $conn->begin_transaction();
    
    try {
        // Log the deletion before deleting the user
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, 'account_deleted', ?, ?)
        ");
        $details = json_encode(['deletion_time' => date('Y-m-d H:i:s')]);
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->bind_param("iss", $user_id, $details, $ip_address);
        $log_stmt->execute();
        
        // Delete user (cascade will handle related records)
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        
        $conn->commit();
        
        // Destroy session
        session_destroy();
        
        echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>