<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    header("Location: home.php");
    exit();
}

// Function to get order details
function getOrderDetails($order_id, $user_id) {
    global $db;
    $query = "SELECT o.*, 
              GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name, ' - ₱', p.price) SEPARATOR '<br>') as products
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN products p ON oi.product_id = p.id
              WHERE o.id = ? AND o.user_id = ?
              GROUP BY o.id";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$order = getOrderDetails($order_id, $user_id);

if (!$order) {
    header("Location: home.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center text-green-800">Order Details</h1>
        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
            <h2 class="text-2xl font-semibold mb-4">Order #<?= $order['id'] ?></h2>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="font-semibold">Date:</p>
                    <p><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                </div>
                <div>
                    <p class="font-semibold">Status:</p>
                    <p class="inline-block px-2 py-1 text-sm font-semibold rounded-full
                        <?php
                        switch($order['status']) {
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'processing': echo 'bg-blue-100 text-blue-800'; break;
                            case 'shipped': echo 'bg-purple-100 text-purple-800'; break;
                            case 'delivered': echo 'bg-green-100 text-green-800'; break;
                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?= ucfirst($order['status']) ?>
                    </p>
                </div>
            </div>
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2">Products:</h3>
                <p><?= $order['products'] ?></p>
            </div>
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2">Shipping Information:</h3>
                <p><?= htmlspecialchars($order['name']) ?></p>
                <p><?= htmlspecialchars($order['address']) ?></p>
                <p><?= htmlspecialchars($order['barangay']) . ', ' . htmlspecialchars($order['city']) ?></p>
                <p><?= htmlspecialchars($order['province']) . ', ' . htmlspecialchars($order['region']) ?></p>
                <p><?= htmlspecialchars($order['country']) . ' ' . htmlspecialchars($order['zip']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-xl font-semibold">Total: ₱<?= number_format($order['total_amount'], 2) ?></p>
            </div>
            <div class="mt-8 text-center">
                <a href="home.php" class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition duration-300">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>

