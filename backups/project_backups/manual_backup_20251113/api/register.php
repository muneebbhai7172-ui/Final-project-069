<?php
session_start();
include '../includes/db_connect.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$response = ['success' => false, 'message' => 'Invalid request'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirmPassword'] ?? '';
    $role = $data['role'] ?? '';
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Validate input
    $errors = [];
    
    if(empty($firstName) || strlen($firstName) < 2) {
        $errors['firstName'] = 'First name must be at least 2 characters long';
    }
    
    if(empty($lastName) || strlen($lastName) < 2) {
        $errors['lastName'] = 'Last name must be at least 2 characters long';
    }
    
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if(empty($phone) || strlen(preg_replace('/\D/', '', $phone)) < 10) {
        $errors['phone'] = 'Please enter a valid phone number with at least 10 digits';
    }
    
    if(empty($password) || strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }
    
    if(empty($role) || !in_array($role, ['pharmacist', 'admin', 'staff'])) {
        $errors['role'] = 'Please select a valid role';
    }
    
    if(!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Please correct the following errors';
        echo json_encode($response);
        exit;
    }
    
    // Enhanced password validation
    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $response['message'] = 'Password must contain: 8+ characters, uppercase letter, lowercase letter, number, and special character (@$!%*?&)';
        echo json_encode($response);
        exit;
    }
    
    // Validate password confirmation
    if($password !== $confirmPassword) {
        $response['message'] = 'Password and confirmation do not match';
        echo json_encode($response);
        exit;
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result && $result->num_rows > 0) {
        $response['message'] = 'Email address is already registered';
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    $stmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Create username from email
    $username = explode('@', $email)[0];
    
    // Ensure username is unique
    $originalUsername = $username;
    $counter = 1;
    do {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $username = $originalUsername . $counter;
            $counter++;
        } else {
            break;
        }
        $stmt->close();
    } while(true);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, role, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("sssssss", $username, $email, $hashedPassword, $firstName, $lastName, $phone, $role);
    
    if($stmt->execute()) {
        $userId = $conn->insert_id;
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = 'Account created successfully! You can now login.';
        $response['user_id'] = $userId;
    } else {
        $response['message'] = 'Registration failed: ' . $stmt->error;
        $stmt->close();
    }
    
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>