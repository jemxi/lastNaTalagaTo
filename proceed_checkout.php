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

// Function to get selected cart items with stock check
function getSelectedCartItems($user_id, $selected_items) {
    global $db;
    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
    $query = "SELECT ci.*, p.name, p.price, p.image, p.stock 
              FROM cart_items ci 
              JOIN products p ON ci.product_id = p.id 
              WHERE ci.user_id = ? AND ci.product_id IN ($placeholders)";
    $stmt = $db->prepare($query);
    $types = str_repeat('i', count($selected_items) + 1);
    $stmt->bind_param($types, $user_id, ...$selected_items);
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

$selected_items = isset($_GET['items']) ? explode(',', $_GET['items']) : [];

if (empty($selected_items)) {
    header("Location: cart.php");
    exit();
}

$cart_items = getSelectedCartItems($user_id, $selected_items);

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Guimba Barangays
$guimba_barangays = [
    "Agcano", "Ayos Lomboy", "Bacayao", "Bagong Barrio", "Balbalino", "Balingog East", 
    "Balingog West", "Banitan", "Bantug", "Bulakid", "Bunol", "Caballero", "Cabaruan", 
    "Caingin Tabing Ilog", "Calem", "Camiing", "Cardinal", "Casongsong", "Catimon", "Cavite", 
    "Cawayan Bugtong", "Consuelo", "Culong", "Escaño", "Faigal", "Galvan", "Guiset", 
    "Lamorito", "Lennec", "Macamias", "Macapabellag", "Macatcatuit", "Manacsac", 
    "Manggang Marikit", "Maturanoc", "Maybubon", "Naglabrahan", "Nagpandayan", 
    "Narvacan I", "Narvacan II", "Pacac", "Partida I", "Partida II", "Pasong Intsik", 
    "Saint John District (Poblacion)", "San Agustin", "San Andres", "San Bernardino", 
    "San Marcelino", "San Miguel", "San Rafael", "San Roque", "Santa Ana", "Santa Cruz", 
    "Santa Lucia", "Santa Veronica District (Poblacion)", "Santo Cristo District (Poblacion)", 
    "Saranay District (Poblacion)", "Sinulatan", "Subol", "Tampac I", "Tampac II & III", 
    "Triala", "Yuson"
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $db->begin_transaction();

    try {
        // Check stock availability
        $stock_validation = validateStock($cart_items);
        if (!$stock_validation['valid']) {
            throw new Exception($stock_validation['message']);
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

            // Update product stock
            $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->bind_param("iii", $item['quantity'], $item['product_id'], $item['quantity']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product stock: " . $stmt->error);
            }
            if ($stmt->affected_rows === 0) {
                throw new Exception("Not enough stock available for product: " . $item['name']);
            }

            // Remove item from cart
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $item['product_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to remove item from cart: " . $stmt->error);
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
    <style>
        .gradient-bg {
            background: linear-gradient(90deg, #38bdf8, #4ade80);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .transition-300 {
            transition: all 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-extrabold mb-10 text-center gradient-bg text-white py-4 rounded-lg shadow-md">Checkout</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="text-center">
                <p class="text-xl text-gray-600 mb-4">No items selected for checkout.</p>
                <a href="cart.php" class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition duration-300">Return to Cart</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Order Summary -->
                <div class="bg-white p-6 rounded-lg shadow-lg card-hover transition-300">
                    <h2 class="text-2xl font-semibold mb-6 border-b pb-4">Order Summary</h2>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="font-semibold text-lg"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-gray-500">Quantity: <?= $item['quantity'] ?></p>
                            </div>
                            <p class="font-bold text-lg text-green-600">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <div class="border-t pt-4 mt-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-semibold">Total:</h3>
                            <p class="text-xl font-bold text-green-800">₱<?= number_format($total, 2) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white p-6 rounded-lg shadow-lg card-hover transition-300">
                    <h2 class="text-2xl font-semibold mb-6 border-b pb-4">Shipping Information</h2>
                    <form action="" method="POST">
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-gray-700 font-medium">Full Name</label>
                                <input type="text" id="name" name="name" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                            </div>
                            <div>
                                <label for="email" class="block text-gray-700 font-medium">Email</label>
                                <input type="email" id="email" name="email" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                            </div>
                            <div>
                                <label for="phone" class="block text-gray-700 font-medium">Phone</label>
                                <input type="tel" id="phone" name="phone" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                            </div>
                            <div>
                                <label for="address" class="block text-gray-700 font-medium">Address</label>
                                <input type="text" id="address" name="address" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                            </div>
                            <div>
                                <label for="barangay" class="block text-gray-700 font-medium">Barangay</label>
                                <select id="barangay" name="barangay" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                                    <?php foreach ($guimba_barangays as $barangay): ?>
                                        <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="city" class="block text-gray-700 font-medium">City</label>
                                    <input type="text" id="city" name="city" value="Guimba" readonly class="w-full px-4 py-2 border rounded-md bg-gray-100">
                                </div>
                                <div>
                                    <label for="province" class="block text-gray-700 font-medium">Province</label>
                                    <input type="text" id="province" name="province" value="Nueva Ecija" readonly class="w-full px-4 py-2 border rounded-md bg-gray-100">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="region" class="block text-gray-700 font-medium">Region</label>
                                    <input type="text" id="region" name="region" value="Region 3" readonly class="w-full px-4 py-2 border rounded-md bg-gray-100">
                                </div>
                                <div>
                                    <label for="country" class="block text-gray-700 font-medium">Country</label>
                                    <input type="text" id="country" name="country" value="Philippines" readonly class="w-full px-4 py-2 border rounded-md bg-gray-100">
                                </div>
                            </div>
                            <div>
                                <label for="zip" class="block text-gray-700 font-medium">ZIP Code</label>
                                <input type="text" id="zip" name="zip" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-400">
                            </div>
                        </div>
                        <button type="submit" class="mt-6 w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-3 px-6 rounded-md hover:opacity-90 transition duration-300">Place Order</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

