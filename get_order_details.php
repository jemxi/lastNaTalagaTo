<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
  die("Connection failed: " . $db->connect_error);
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
  die("Unauthorized access");
}

if (!isset($_GET['order_id'])) {
  die("No order ID provided");
}

$orderId = intval($_GET['order_id']);

// Get order details
$orderQuery = "SELECT * FROM orders WHERE id = ?";
$orderStmt = $db->prepare($orderQuery);
$orderStmt->bind_param("i", $orderId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();

// Get order items
$itemsQuery = "SELECT oi.*, p.name as product_name, p.image as product_image, p.price 
               FROM order_items oi 
               JOIN products p ON oi.product_id = p.id 
               WHERE oi.order_id = ?";
$itemsStmt = $db->prepare($itemsQuery);
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);

$response = [
  'order' => $order,
  'items' => $items
];

header('Content-Type: application/json');
echo json_encode($response);


?>
