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

// Function to get cart items with stock check
function getCartItems($user_id) {
    global $db;
    $query = "SELECT ci.*, p.name, p.price, p.image, p.stock 
              FROM cart_items ci 
              JOIN products p ON ci.product_id = p.id 
              WHERE ci.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to validate stock availability
function validateStock($cart_items) {
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            return [
                'valid' => false,
                'message' => "Not enough stock available for {$item['name']}. Available: {$item['stock']}"
            ];
        }
    }
    return ['valid' => true];
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 0;

if ($product_id > 0 && $quantity > 0) {
    // Fetch the product details
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        $cart_items = [
            [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image' => $product['image'],
            ]
        ];
        $total = $product['price'] * $quantity;
    } else {
        header("Location: home.php");
        exit();
    }
} else {
    // Existing cart logic
    $cart_items = getCartItems($user_id);
    // Calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
}


// Guimba Barangays
$guimba_barangays = [
    "Ayos Lomboy", "Bagong Buhay", "Bangar", "Calizon", "Cabaruan", "Cavite", "Naglabrahan",
    "Pacac", "San Andres", "San Antonio", "San Bernardino", "San Roque", "Santa Ana", "Santa Cruz",
    "Santa Lucia", "Santa Veronica", "Santo Cristo", "Triala", "Yuson"
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $db->begin_transaction();

    try {
        // Check stock availability
        foreach ($cart_items as $item) {
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->bind_param("i", $item['product_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            if ($product['stock'] < $item['quantity']) {
                throw new Exception("Not enough stock available for " . $item['name']);
            }
        }

        // Process the order
        $name = $db->real_escape_string($_POST['name']);
        $email = $db->real_escape_string($_POST['email']);
        $phone = $db->real_escape_string($_POST['phone']);
        $address = $db->real_escape_string($_POST['address']);
        $barangay = $db->real_escape_string($_POST['barangay']);
        $city = "Guimba";
        $province = "Nueva Ecija";
        $region = "Region 3";
        $country = "Philippines";
        $zip = $db->real_escape_string($_POST['zip']);

        // Create the order
        $stmt = $db->prepare("INSERT INTO orders (user_id, name, email, phone, address, barangay, city, province, region, country, zip, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        $stmt->bind_param("issssssssssd",
            $user_id, $name, $email, $phone, $address, $barangay,
            $city, $province, $region, $country, $zip, $total
        );

        if (!$stmt->execute()) {
            throw new Exception("Order creation failed: " . $stmt->error);
        }

        $order_id = $db->insert_id;

        // Insert order items and update stock
        foreach ($cart_items as $item) {
            // Insert order item
            $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            if (!$stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price'])) {
                throw new Exception("Failed to bind parameters for order item");
            }

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order item: " . $stmt->error);
            }
        }

        // Update product stock
        foreach ($cart_items as $item) {
            $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->bind_param("iii", $item['quantity'], $item['product_id'], $item['quantity']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product stock: " . $stmt->error);
            }
            if ($stmt->affected_rows === 0) {
                throw new Exception("Not enough stock available for product: " . $item['name']);
            }
        }


        // Clear the cart
        if ($product_id > 0 && $quantity > 0) {
            // This is a direct purchase, no need to clear the cart
        } else {
            // Clear the cart for regular checkout
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to clear cart: " . $stmt->error);
            }
        }

        // If we get here, commit the transaction
        $db->commit();

        // Redirect to thank you page
        header("Location: thank_you.php?order_id=" . $order_id);
        exit();

    } catch (Exception $e) {
        // Rollback the transaction on error
        $db->rollback();
        $error = "Failed to place the order. Please try again. Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center text-green-800">Checkout</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="text-center">
                <p class="text-xl text-gray-600 mb-4">Your cart is empty.</p>
                <a href="home.php" class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition duration-300">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-2xl font-semibold mb-4">Order Summary</h2>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="font-semibold"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-gray-600">Quantity: <?= $item['quantity'] ?></p>
                            </div>
                            <p class="font-semibold">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <div class="border-t pt-4 mt-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-semibold">Total:</h3>
                            <p class="text-xl font-semibold">₱<?= number_format($total, 2) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-2xl font-semibold mb-4">Shipping Information</h2>
                    <form action="" method="POST">
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 font-semibold mb-2">Full Name</label>
                            <input type="text" id="name" name="name" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
                            <input type="email" id="email" name="email" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="phone" class="block text-gray-700 font-semibold mb-2">Phone</label>
                            <input type="tel" id="phone" name="phone" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="address" class="block text-gray-700 font-semibold mb-2">Address</label>
                            <input type="text" id="address" name="address" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        <div class="mb-4">
                            <label for="barangay" class="block text-gray-700 font-semibold mb-2">Barangay</label>
                            <select id="barangay" name="barangay" required class="w-full px-3 py-2 border rounded-md">
                                <?php foreach ($guimba_barangays as $barangay): ?>
                                    <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="city" class="block text-gray-700 font-semibold mb-2">City</label>
                            <input type="text" id="city" name="city" value="Guimba" readonly class="w-full px-3 py-2 border rounded-md bg-gray-100">
                        </div>
                        <div class="mb-4">
                            <label for="province" class="block text-gray-700 font-semibold mb-2">Province</label>
                            <input type="text" id="province" name="province" value="Nueva Ecija" readonly class="w-full px-3 py-2 border rounded-md bg-gray-100">
                        </div>
                        <div class="mb-4">
                            <label for="region" class="block text-gray-700 font-semibold mb-2">Region</label>
                            <input type="text" id="region" name="region" value="Region 3" readonly class="w-full px-3 py-2 border rounded-md bg-gray-100">
                        </div>
                        <div class="mb-4">
                            <label for="country" class="block text-gray-700 font-semibold mb-2">Country</label>
                            <input type="text" id="country" name="country" value="Philippines" readonly class="w-full px-3 py-2 border rounded-md bg-gray-100">
                        </div>
                        <div class="mb-6">
                            <label for="zip" class="block text-gray-700 font-semibold mb-2">ZIP Code</label>
                            <input type="text" id="zip" name="zip" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-green-700 transition duration-300">Place Order</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

