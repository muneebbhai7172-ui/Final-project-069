<?php
// Clear registration rate limiting attempts
include 'includes/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Clear all registration attempts older than 10 minutes
    $stmt = $conn->prepare("DELETE FROM registration_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $result = $stmt->execute();
    $deleted_count = $conn->affected_rows;
    $stmt->close();
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = "Cleared $deleted_count old registration attempts. Rate limiting reset.";
    } else {
        $response['message'] = 'Failed to clear registration attempts';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>