<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($order_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Check if the order belongs to the user and is in a returnable state (delivered)
$query = "SELECT id, status FROM orders WHERE id = ? AND user_id = ? AND status = 'delivered'";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or cannot be returned']);
    exit();
}

// Update the order status to return_pending
$update_query = "UPDATE orders SET status = 'return_pending' WHERE id = ?";
$update_stmt = $db->prepare($update_query);
$update_stmt->bind_param("i", $order_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Return request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit return request']);
}

