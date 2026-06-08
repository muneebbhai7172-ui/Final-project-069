<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include '../includes/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['medicine_id'])) {
            getReviews($_GET['medicine_id']);
        } else {
            echo json_encode(['error' => 'Medicine ID required']);
        }
        break;
    case 'POST':
        addReview();
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getReviews($medicineId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE medicine_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $medicineId);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($reviews);
}

function addReview() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $conn->prepare("INSERT INTO reviews (medicine_id, customer_name, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $data['medicine_id'], $data['customer_name'], $data['rating'], $data['comment']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add review']);
    }
}
?>