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

// Function to get cart items
function getCartItems($user_id) {
    global $db;
    $query = "SELECT ci.*, p.name, p.price, p.image 
              FROM cart_items ci 
              JOIN products p ON ci.product_id = p.id 
              WHERE ci.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if ($_POST['action'] === 'remove') {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
        } elseif ($_POST['action'] === 'update') {
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
            if ($quantity > 0) {
                $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("iii", $quantity, $user_id, $product_id);
                $stmt->execute();
            } else {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: cart.php");
        exit();
    }
}

$cart_items = getCartItems($user_id);

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Global Styles */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 5.5rem;
            background-color: #2f855a;
            z-index: 50;
        }

        /* Sidebar */
        #sidebar {
            position: fixed;
            top: 10%;
            left: 0;
            width: 16rem;
            height: calc(100% - 4rem);
            background-color: #2f855a;
            color: white;
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 40;
        }

        body.sidebar-open #sidebar {
            transform: translateX(0);
        }

        /* Main Content */
        main {
            margin-top: 5.5rem;
            margin-left: 0;
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
            transition: margin-left 0.3s ease-in-out;
        }

        body.sidebar-open main {
            margin-left: 16rem;
        }

        /* Footer */
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #2f855a;
            color: white;
            text-align: center;
            padding: 1rem 0;
            z-index: 30;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Header -->
    <header class="bg-green-700 text-white py-6 sticky top-0 z-30">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <h1 class="text-3xl font-bold mb-4 md:mb-0">Wastewise E-commerce</h1>
            </div>
        </div>
    </header>

    <!-- Sidebar Toggle Button -->
    <button class="fixed top-4 left-4 z-50 bg-green-600 text-white p-2 rounded-full shadow-lg" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav id="sidebar" class="fixed bg-green-800 text-white p-5">
        <div class="flex flex-col h-full">
            <!-- Add the Welcome message at the top -->
            <div class="flex items-center justify-center py-2 mb-4">
                <span class="bg-green-900 text-white py-2 px-4 rounded-full text-center">
                    <i class="fas fa-user mr-2"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
            
            <!-- Sidebar links -->
            <div class="flex-grow mt-4"> <!-- Add margin for spacing -->
                <a href="home.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="my_orders.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-shopping-bag mr-2"></i> My Orders
                </a>
                <a href="cart.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-shopping-cart mr-2"></i> Cart
                </a>
            </div>

            <!-- Logout link -->
            <div>
                <a href="logout.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <h1 class="text-3xl font-bold mb-8 text-center text-green-800">Your Shopping Cart</h1>
        <?php if (empty($cart_items)): ?>
            <div class="text-center">
                <p class="text-xl text-gray-600 mb-4">Your cart is empty.</p>
                <a href="home.php" class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition duration-300">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Product</th>
                            <th class="py-3 px-6 text-center">Quantity</th>
                            <th class="py-3 px-6 text-center">Price</th>
                            <th class="py-3 px-6 text-center">Total</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php foreach ($cart_items as $item): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-12 h-12 object-cover mr-3">
                                        <span class="font-medium"><?= htmlspecialchars($item['name']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <form action="cart.php" method="POST" class="flex justify-center items-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="w-16 text-center border rounded-md">
                                        <button type="submit" class="ml-2 text-blue-600 hover:text-blue-900">Update</button>
                                    </form>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    ₱<?= number_format($item['price'], 2) ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    ₱<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <form action="cart.php" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-8 flex flex-col md:flex-row justify-between items-center">
                <a href="home.php" class="bg-gray-500 text-white px-6 py-2 rounded-full hover:bg-gray-600 transition duration-300 mb-4 md:mb-0">Continue Shopping</a>
                <div class="text-right">
                    <p class="text-xl font-semibold mb-2">Total: ₱<?= number_format($total, 2) ?></p>
                    <a href="proceed_checkout.php" class="bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition duration-300">Proceed to Checkout</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Wastewise E-commerce. All rights reserved.</p>
            <p>Committed to a sustainable future through recycling and eco-friendly shopping.</p>
        </div>
    </footer>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }
    </script>
</body>
</html>
