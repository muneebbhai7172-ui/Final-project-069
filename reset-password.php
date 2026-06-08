<?php
// Load composer autoloader
require 'vendor/autoload.php';

// Use bcrypt from password_hash
$email = 'muneebadmin@gmail.com';
$newPassword = '@Muneeb111111';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Direct database connection
$mysqli = new mysqli('127.0.0.1', 'root', '', 'pharmacy_db');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param('ss', $hashedPassword, $email);

if ($stmt->execute()) {
    echo "Password reset successfully for: $email\n";
    echo "Rows affected: " . $stmt->affected_rows . "\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}

$stmt->close();
$mysqli->close();
