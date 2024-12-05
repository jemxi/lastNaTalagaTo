<?php
session_start();
require_once 'db_connection.php';

function getProductDetails($product_ids) {
    global $db;
    $ids = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($ids)");
    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['products'])) {
    $product_ids = explode(',', $_GET['products']);
    $products = getProductDetails($product_ids);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Products - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Product Comparison</h1>
        <?php if (isset($products) && count($products) > 1): ?>
            <div class="overflow-x-auto">
                <table class="w-full bg-white shadow-md rounded-lg">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Feature</th>
                            <?php foreach ($products as $product): ?>
                                <th class="py-3 px-6 text-left"><?= htmlspecialchars($product['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="py-3 px-6 text-left font-semibold">Price</td>
                            <?php foreach ($products as $product): ?>
                                <td class="py-3 px-6 text-left">₱<?= number_format($product['price'], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-left font-semibold">Category</td>
                            <?php foreach ($products as $product): ?>
                                <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['category']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-left font-semibold">Stock</td>
                            <?php foreach ($products as $product): ?>
                                <td class="py-3 px-6 text-left"><?= $product['stock'] ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-left font-semibold">Description</td>
                            <?php foreach ($products as $product): ?>
                                <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['description']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-xl text-center">Please select at least two products to compare.</p>
        <?php endif; ?>
    </div>
</body>
</html>
