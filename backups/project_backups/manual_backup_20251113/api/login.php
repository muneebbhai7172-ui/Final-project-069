<?php
session_start();
include '../includes/db_connect.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$response = ['success' => false, 'message' => 'Invalid request'];

// Rate limiting
function checkRateLimit($ip, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['attempts'] < 5;
}

// Log login attempt
function logLoginAttempt($ip, $email, $success, $conn) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, success, attempt_time) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ssi", $ip, $email, $success);
    $stmt->execute();
    $stmt->close();
}

// Generate secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Validate CSRF token
function validateCSRFToken($token) {
    return !empty($token) && strlen($token) === 64 && ctype_xdigit($token);
}

// Check for 2FA requirement
function requires2FA($userId, $conn) {
    $stmt = $conn->prepare("SELECT two_factor_enabled FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        $stmt->close();
        return (bool)$settings['two_factor_enabled'];
    }
    $stmt->close();
    return false;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);
    
    // Support both JSON and form data
    $email = '';
    $password = '';
    $rememberMe = false;
    
    if($data && is_array($data)) {
        // JSON request
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';
        $rememberMe = isset($data['rememberMe']) && $data['rememberMe'] === true;
    } else {
        // Form data request
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember']) && $_POST['remember'] === 'on';
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Validate input
    if(empty($email) || empty($password)) {
        $response['message'] = 'Email and password are required';
        echo json_encode($response);
        exit;
    }
    
    // Validate email format
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        echo json_encode($response);
        exit;
    }
    
    // Check rate limiting
    if(!checkRateLimit($clientIP, $conn)) {
        $response['message'] = 'Too many login attempts. Please try again in 30 seconds.';
        logLoginAttempt($clientIP, $email, 0, $conn);
        echo json_encode($response);
        exit;
    }
    
    // Prepare user query
    $stmt = $conn->prepare("SELECT id, username, email, password, role, account_locked, failed_attempts, last_login FROM users WHERE email = ? AND active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result && $result->num_rows > 0){
        $user = $result->fetch_assoc();
        
        // Check if account is locked
        if($user['account_locked']) {
            $response['message'] = 'Account is locked. Please contact administrator.';
            logLoginAttempt($clientIP, $email, 0, $conn);
            echo json_encode($response);
            exit;
        }
        
        // Verify password
        if(password_verify($password, $user['password'])){
            
            // Check if 2FA is required
            if(requires2FA($user['id'], $conn)) {
                // Store temporary session for 2FA
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_login_time'] = time();
                
                $response['success'] = true;
                $response['requires_2fa'] = true;
                $response['message'] = '2FA verification required';
                
                logLoginAttempt($clientIP, $email, 1, $conn);
                echo json_encode($response);
                exit;
            }
            
            // Generate session token
            $sessionToken = generateSessionToken();
            
            // Generate device fingerprint (simple implementation)
            $fingerprint = hash('sha256', $userAgent . $clientIP . date('Y-m-d'));
            
            // Clear failed attempts
            $updateStmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Create session record
            $sessionStmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, device_fingerprint, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $sessionStmt->bind_param("issss", $user['id'], $sessionToken, $clientIP, $userAgent, $fingerprint);
            $sessionStmt->execute();
            $sessionStmt->close();
            
            // Set session variables
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['login_time'] = time();
            
            // Set remember me cookie if requested
            if($rememberMe) {
                $rememberToken = generateSessionToken();
                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', true, true); // 30 days
                
                // Store remember token in database
                $rememberStmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                $rememberStmt->bind_param("is", $user['id'], $rememberToken);
                $rememberStmt->execute();
                $rememberStmt->close();
            }
            
            $response['success'] = true;
            $response['message'] = 'Login successful';
            $response['session_token'] = $sessionToken;
            $response['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'last_login' => $user['last_login']
            ];
            
            // Redirect to dashboard HTML (frontend)
            $response['redirect'] = 'admin/dashboard.html';
            
            logLoginAttempt($clientIP, $email, 1, $conn);
            
        } else {
            // Increment failed attempts
            $failedAttempts = $user['failed_attempts'] + 1;
            $updateStmt = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $failedAttempts, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Lock account after 5 failed attempts
            if($failedAttempts >= 5) {
                $lockStmt = $conn->prepare("UPDATE users SET account_locked = 1 WHERE id = ?");
                $lockStmt->bind_param("i", $user['id']);
                $lockStmt->execute();
                $lockStmt->close();
                
                $response['message'] = 'Account locked due to multiple failed attempts';
            } else {
                $response['message'] = 'Invalid credentials. ' . (5 - $failedAttempts) . ' attempts remaining.';
            }
            
            logLoginAttempt($clientIP, $email, 0, $conn);
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid credentials';
        logLoginAttempt($clientIP, $email, 0, $conn);
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>
