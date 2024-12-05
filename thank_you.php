<?php
session_start();
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-md mx-auto text-center">
            <h1 class="text-3xl font-bold mb-4 text-green-800">Thank You for Your Order!</h1>
            <p class="text-xl mb-4">Your order (ID: <?= $order_id ?>) has been successfully placed.</p>
            <p class="mb-8">We'll send you an email with the order details and tracking information once your order has been shipped.</p>
            <a href="home.php" class="bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition duration-300">Continue Shopping</a>
        </div>
    </div>
</body>
</html>

