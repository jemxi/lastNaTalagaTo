<?php
require_once 'db.php';

// Function to get related products
function getRelatedProducts($product_id, $limit = 4) {
    global $db;
    $stmt = $db->prepare("SELECT p.* FROM products p 
                        JOIN products current ON p.category = current.category 
                        WHERE current.id = ? AND p.id != ? 
                        ORDER BY RAND() LIMIT ?");
    $stmt->bind_param("iii", $product_id, $product_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id === 0) {
    header("Location: manage_products.php");
    exit();
}

// Get product details
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: manage_products.php");
    exit();
}

// Get related products
$related_products = getRelatedProducts($product_id);

// Get product reviews
require_once 'product_reviews.php';
$reviews = getProductReviews($product_id);
$average_rating = getAverageRating($product_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Product Details</h1>
                <a href="manage_products.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Back to Products</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-auto rounded-lg shadow-md">
                </div>
                <div>
                    <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($product['name']) ?></h2>
                    <p class="text-xl font-bold mb-4">₱<?= number_format($product['price'], 2) ?></p>
                    <p class="text-gray-600 mb-4"><?= htmlspecialchars($product['description']) ?></p>
                    <p class="mb-4">Category: <?= htmlspecialchars($product['category']) ?></p>
                    <p class="mb-4">Stock: <?= $product['stock'] ?></p>
                    <div class="flex items-center mb-4">
                        <div class="flex">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($average_rating) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="ml-2 text-gray-600">(<?= count($reviews) ?> reviews)</span>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="mt-8">
                <h3 class="text-2xl font-bold mb-4">Customer Reviews</h3>
                <?php if (empty($reviews)): ?>
                    <p class="text-gray-600">No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-b border-gray-200 py-4">
                            <div class="flex items-center mb-2">
                                <div class="flex">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="ml-2 font-semibold"><?= htmlspecialchars($review['username']) ?></span>
                                <span class="ml-2 text-gray-500"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                            </div>
                            <p class="text-gray-700"><?= htmlspecialchars($review['review_text']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Related Products Section -->
            <div class="mt-8">
                <h3 class="text-2xl font-bold mb-4">Related Products</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($related_products as $related): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <img src="<?= htmlspecialchars($related['image']) ?>" alt="<?= htmlspecialchars($related['name']) ?>" class="w-full h-48 object-cover">
                            <div class="p-4">
                                <h4 class="text-lg font-semibold mb-2"><?= htmlspecialchars($related['name']) ?></h4>
                                <p class="text-gray-600">₱<?= number_format($related['price'], 2) ?></p>
                                <a href="?id=<?= $related['id'] ?>" class="mt-2 inline-block text-blue-600 hover:text-blue-800">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
