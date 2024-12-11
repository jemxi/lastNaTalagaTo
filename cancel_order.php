<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_POST['order_id'] ?? null;
$reason = $_POST['reason'] ?? '';
$comment = $_POST['comment'] ?? '';

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

// Check if the order belongs to the user and is in a cancellable state
$query = "SELECT id, status FROM orders WHERE id = ? AND user_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
    exit();
}

$order = $result->fetch_assoc();

if ($order['status'] == 'shipped') {
    // If the order is already shipped, set status to 'cancellation_pending'
    $new_status = 'cancellation_pending';
} elseif ($order['status'] == 'pending' || $order['status'] == 'processing') {
    // If the order is not yet shipped, automatically cancel it
    $new_status = 'cancelled';
} else {
    echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled']);
    exit();
}

// Update the order status and add cancellation details
$update_query = "UPDATE orders SET status = ?, cancellation_reason = ?, cancellation_comment = ?, cancelled_at = CURRENT_TIMESTAMP WHERE id = ?";
$update_stmt = $db->prepare($update_query);
$update_stmt->bind_param("sssi", $new_status, $reason, $comment, $order_id);

if ($update_stmt->execute()) {
    $message = $new_status == 'cancelled' ? 'Order cancelled successfully' : 'Cancellation request submitted and waiting for approval';
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel the order']);
}

$db->close();
