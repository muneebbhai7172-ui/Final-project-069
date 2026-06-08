<?php
// Simple test to check medicines table
require_once '../config/db.php';

echo "<h1>Database Test</h1>";

try {
    // Check if medicines table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
    $result = $stmt->fetch();
    echo "<p>Total medicines in database: " . $result['total'] . "</p>";

    // Get first 10 medicines
    $stmt = $pdo->query("SELECT id, name, category, price, quantity FROM medicines LIMIT 10");
    $medicines = $stmt->fetchAll();

    if (empty($medicines)) {
        echo "<p><strong>No medicines found in database!</strong></p>";
    } else {
        echo "<h2>Sample Medicines:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Quantity</th></tr>";
        foreach ($medicines as $medicine) {
            echo "<tr>";
            echo "<td>" . $medicine['id'] . "</td>";
            echo "<td>" . $medicine['name'] . "</td>";
            echo "<td>" . $medicine['category'] . "</td>";
            echo "<td>" . $medicine['price'] . "</td>";
            echo "<td>" . $medicine['quantity'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test the suggestions API query directly
    echo "<h2>Testing Suggestions Query:</h2>";
    $search = 'para';
    $startsWithTerm = $search . "%";
    $limit = 10;

    $stmt = $pdo->prepare("
        SELECT id, name, category, price, quantity, image,
               CASE
                   WHEN LOWER(name) = LOWER(?) THEN 1
                   WHEN LOWER(name) LIKE LOWER(?) THEN 2
                   WHEN LOWER(category) LIKE LOWER(?) THEN 3
                   ELSE 4
               END as relevance
        FROM medicines
        WHERE (LOWER(name) LIKE LOWER(?) OR LOWER(category) LIKE LOWER(?))
        AND quantity > 0
        ORDER BY relevance ASC, quantity DESC, name ASC
        LIMIT ?
    ");

    $stmt->execute([$search, $startsWithTerm, $startsWithTerm, $startsWithTerm, $startsWithTerm, $limit]);
    $suggestions = $stmt->fetchAll();

    echo "<p>Query for 'para' returned " . count($suggestions) . " results:</p>";
    if (!empty($suggestions)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Name</th><th>Category</th><th>Price</th><th>Quantity</th><th>Relevance</th></tr>";
        foreach ($suggestions as $suggestion) {
            echo "<tr>";
            echo "<td>" . $suggestion['name'] . "</td>";
            echo "<td>" . $suggestion['category'] . "</td>";
            echo "<td>" . $suggestion['price'] . "</td>";
            echo "<td>" . $suggestion['quantity'] . "</td>";
            echo "<td>" . $suggestion['relevance'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
