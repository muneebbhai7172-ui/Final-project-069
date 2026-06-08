<?php
include 'includes/db_connect.php';

echo "<h2>Database Update Script</h2>";

// Read and execute the database update file
$sqlFile = 'database_transaction_updates.sql';

if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    // Split the SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            echo "<p>Executing: " . substr($statement, 0, 50) . "...</p>";
            if ($conn->query($statement)) {
                echo "<p style='color: green;'>✓ Success</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            }
        }
    }
    
    echo "<h3>Update Complete</h3>";
} else {
    echo "<p style='color: red;'>Database update file not found: $sqlFile</p>";
}

$conn->close();
?>