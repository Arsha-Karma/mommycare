<?php
session_start();
include('db.php');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 1; // Use your user ID for testing

echo "<h2>Debug Cart Information</h2>";

// Check cart table structure
$structure = $db->conn->query("DESCRIBE cart");
echo "<h3>Cart Table Structure:</h3>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $structure->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check current cart items
echo "<h3>Current Cart Items for User $user_id:</h3>";
$cart_items = $db->conn->query("SELECT * FROM cart WHERE user_id = $user_id");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>User ID</th><th>Product ID</th><th>Quantity</th><th>Status</th></tr>";
while($row = $cart_items->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td>{$row['product_id']}</td>";
    echo "<td>{$row['quantity']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test update query
echo "<h3>Testing Update Query:</h3>";
$test_update = $db->conn->query("UPDATE cart SET status = 'completed' WHERE user_id = $user_id AND status = 'active'");
if($test_update) {
    $affected = $db->conn->affected_rows;
    echo "Update successful. Affected rows: $affected<br>";
} else {
    echo "Update failed: " . $db->conn->error . "<br>";
}

// Check results after update
$cart_after = $db->conn->query("SELECT * FROM cart WHERE user_id = $user_id");
echo "<h3>Cart Items After Update:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>User ID</th><th>Product ID</th><th>Quantity</th><th>Status</th></tr>";
while($row = $cart_after->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td>{$row['product_id']}</td>";
    echo "<td>{$row['quantity']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "</tr>";
}
echo "</table>";
?>