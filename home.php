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

// Function to get all products
function getProducts($search = '', $category = '') {
  global $db;
  $query = "SELECT p.*, ec.name as event_category_name 
            FROM products p 
            LEFT JOIN event_categories ec ON p.event_category_id = ec.id 
            WHERE 1=1";
  if (!empty($search)) {
      $search = $db->real_escape_string($search);
      $query .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
  }
  if (!empty($category)) {
      $category = $db->real_escape_string($category);
      $query .= " AND p.category = '$category'";
  }
  $query .= " ORDER BY p.created_at DESC";
  $result = $db->query($query);
  return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all event categories
function getEventCategories() {
  global $db;
  $query = "SELECT * FROM event_categories ORDER BY name ASC";
  $result = $db->query($query);
  return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get wishlist items
function getWishlistItems($user_id) {
  global $db;
  $query = "SELECT w.*, p.name, p.price, p.image 
            FROM wishlist w 
            JOIN products p ON w.product_id = p.id 
            WHERE w.user_id = ?";
  $stmt = $db->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_all(MYSQLI_ASSOC);
}

// Updated function to get recent orders grouped by status
function getRecentOrdersGrouped($user_id, $limit = 50) {
    global $db;
    $query = "SELECT o.id, o.total_amount, o.status, o.created_at, 
              GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name, '|||', p.image) SEPARATOR '---') as products
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN products p ON oi.product_id = p.id
              WHERE o.user_id = ? AND o.archived = 0
              GROUP BY o.id
              ORDER BY o.created_at DESC
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);

    $grouped_orders = [
        'all' => $orders,
        'to_pay' => [],
        'to_ship' => [],
        'to_receive' => [],
        'completed' => [],
        'cancelled' => [],
        'return_refund' => []
    ];

    foreach ($orders as $order) {
        switch ($order['status']) {
            case 'pending':
                $grouped_orders['to_pay'][] = $order;
                break;
            case 'processing':
                $grouped_orders['to_ship'][] = $order;
                break;
            case 'shipped':
                $grouped_orders['to_receive'][] = $order;
                break;
            case 'delivered':
                $grouped_orders['completed'][] = $order;
                break;
            case 'cancelled':
                $grouped_orders['cancelled'][] = $order;
                break;
            case 'return_pending':
            case 'return_approved':
            case 'refunded':
                $grouped_orders['return_refund'][] = $order;
                break;
        }
    }

    return $grouped_orders;
}

// Handle wishlist actions
if (isset($_POST['action']) && isset($_POST['product_id'])) {
  $productId = intval($_POST['product_id']);
  $userId = $_SESSION['user_id'];
  
  if ($_POST['action'] === 'add') {
      $stmt = $db->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
      $stmt->bind_param("ii", $userId, $productId);
      $stmt->execute();
  } elseif ($_POST['action'] === 'remove') {
      $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
      $stmt->bind_param("ii", $userId, $productId);
      $stmt->execute();
  }
  exit; // Stop further execution for AJAX requests
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$all_products = getProducts($search, $category);

// Filter products for Christmas and regular sections
$christmas_products = array_filter($all_products, function($product) {
  return !is_null($product['event_category_id']) && strtolower($product['event_category_name']) === 'christmas';
});
$regular_products = array_filter($all_products, function($product) {
  return is_null($product['event_category_id']) || strtolower($product['event_category_name']) !== 'christmas';
});

$wishlist = getWishlistItems($_SESSION['user_id']);
$grouped_orders = getRecentOrdersGrouped($_SESSION['user_id']);

$categories = ['Paper', 'Plastic', 'Metal', 'Glass', 'Electronics', 'Textiles'];
$event_categories = getEventCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wastewise E-commerce - Christmas Special</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
      .christmas-theme {
          background: linear-gradient(to bottom, #1a472a, #2d724a);
      }
      .snow {
          position: absolute;
          width: 10px;
          height: 10px;
          background: white;
          border-radius: 50%;
          filter: blur(1px);
      }
      @keyframes snowfall {
          0% {
              transform: translateY(0) rotate(0deg);
          }
          100% {
              transform: translateY(100vh) rotate(360deg);
          }
      }
      .overflow-x-auto {
          overflow-x: auto;
          scrollbar-width: thin;
          scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
      }
      .overflow-x-auto::-webkit-scrollbar {
          height: 6px;
      }
      .overflow-x-auto::-webkit-scrollbar-track {
          background: transparent;
      }
      .overflow-x-auto::-webkit-scrollbar-thumb {
          background-color: rgba(156, 163, 175, 0.5);
          border-radius: 3px;
      }
  </style>
</head>
<body class="bg-gray-100 font-sans">
  <!-- Sidebar Toggle Button -->
  <button class="fixed top-4 left-4 z-50 bg-green-600 text-white p-2 rounded-full shadow-lg" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
  </button>

  <!-- Sidebar -->
  <nav class="fixed top-0 left-0 h-full w-64 bg-green-800 text-white p-5 transform -translate-x-full transition-transform duration-200 ease-in-out z-40" id="sidebar">
      <div class="flex flex-col h-full">
          <div class="flex-grow">
              <a href="#" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200" onclick="showSection('home')">
                  <i class="fas fa-home mr-2"></i>
                  <span>Home</span>
              </a>
              <a href="#" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200" onclick="showSection('wishlist')">
                  <i class="fas fa-heart mr-2"></i>
                  <span>Wishlist</span>
              </a>
              <a href="cart.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                  <i class="fas fa-shopping-cart mr-2"></i>
                  <span>Cart</span>
              </a>
              <a href="#" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200" onclick="showSection('notifications')">
                  <i class="fas fa-bell mr-2"></i>
                  <span>Notifications</span>
              </a>
          </div>
          <div>
              <span class="block py-2 px-4">
                  <i class="fas fa-user mr-2"></i>
                  <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
              </span>
              <a href="logout.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                  <i class="fas fa-sign-out-alt mr-2"></i>
                  <span>Logout</span>
              </a>
          </div>
      </div>
  </nav>

  <!-- Main Content -->
  <div class="content" id="content">
      <!-- Header with Search -->
      <header class="bg-green-700 text-white py-6 sticky top-0 z-30">
          <div class="container mx-auto px-4">
              <div class="flex flex-col md:flex-row justify-between items-center">
                  <h1 class="text-3xl font-bold mb-4 md:mb-0">Wastewise E-commerce</h1>
                  <form action="" method="GET" class="flex items-center">
                      <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" class="px-4 py-2 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-gray-800">
                      <select name="category" class="px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 text-gray-800">
                          <option value="">All Categories</option>
                          <?php foreach ($categories as $cat): ?>
                              <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                          <?php endforeach; ?>
                      </select>
                      <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-r-lg hover:bg-green-500 transition duration-300">Search</button>
                  </form>
              </div>
          </div>
      </header>

      <!-- Christmas Banner -->
      <div class="christmas-theme text-white py-12 relative overflow-hidden">
          <div class="container mx-auto px-4 relative z-10">
              <h2 class="text-4xl md:text-6xl font-bold text-center mb-4">Christmas Special</h2>
              <p class="text-xl md:text-2xl text-center mb-8">Discover eco-friendly gifts for your loved ones!</p>
              <div class="text-center">
                  <a href="#christmas-products" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-full transition duration-300 inline-block">Shop Now</a>
              </div>
          </div>
          <?php for ($i = 0; $i < 50; $i++): ?>
              <div class="snow" style="left: <?= rand(0, 100); ?>vw; animation: snowfall <?= rand(5, 15); ?>s linear infinite;"></div>
          <?php endfor; ?>
      </div>

      <main class="container mx-auto px-4 py-12">
          <!-- Notifications Section -->
          <section id="notifications" class="mb-16 hidden">
              <h2 class="text-3xl font-bold mb-8 text-center text-green-800">Your Orders</h2>

              <!-- Order Status Tabs -->
              <div class="mb-8 overflow-x-auto">
                  <nav class="flex border-b border-gray-200" aria-label="Order status">
                      <?php
                      $tabs = [
                          'all' => 'All',
                          'to_pay' => 'To Pay',
                          'to_ship' => 'To Ship',
                          'to_receive' => 'To Receive',
                          'completed' => 'Completed',
                          'cancelled' => 'Cancelled',
                          'return_refund' => 'Return Refund'
                      ];
                      ?>
                      <?php foreach ($tabs as $key => $label): ?>
                          <button onclick="switchTab('<?= $key ?>')" 
                                  class="tab-button flex-shrink-0 py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap
                                         <?= $key === 'all' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>"
                                  data-tab="<?= $key ?>">
                              <?= $label ?>
                              <?php if (!empty($grouped_orders[$key])): ?>
                                  <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">
                                      <?= count($grouped_orders[$key]) ?>
                                  </span>
                              <?php endif; ?>
                          </button>
                      <?php endforeach; ?>
                  </nav>
              </div>

              <!-- Order Lists -->
              <?php foreach ($tabs as $key => $label): ?>
                  <div id="<?= $key ?>-orders" class="order-section <?= $key === 'all' ? 'block' : 'hidden' ?>">
                      <?php if (empty($grouped_orders[$key])): ?>
                          <p class="text-center text-gray-600">No orders found in this section.</p>
                      <?php else: ?>
                          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                              <?php foreach ($grouped_orders[$key] as $order): 
                                  $products = explode('---', $order['products']);
                              ?>
                                  <div class="bg-white rounded-lg shadow-md p-6">
                                      <div class="flex justify-between items-start mb-4">
                                          <h4 class="text-xl font-semibold">Order #<?= $order['id'] ?></h4>
                                          <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full
                                              <?php
                                              switch($order['status']) {
                                                  case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                  case 'processing': echo 'bg-blue-100 text-blue-800'; break;
                                                  case 'shipped': echo 'bg-purple-100 text-purple-800'; break;
                                                  case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                                  case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                  case 'return_pending':
                                                  case 'return_approved':
                                                  case 'refunded':
                                                      echo 'bg-orange-100 text-orange-800'; break;
                                                  default: echo 'bg-gray-100 text-gray-800';
                                              }
                                              ?>">
                                              <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                          </span>
                                      </div>
                                      <p class="text-gray-600 mb-2">Date: <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                                      <p class="text-gray-600 mb-4">Total: ₱<?= number_format($order['total_amount'], 2) ?></p>
                                      <div class="mb-4">
                                          <h5 class="font-semibold mb-2">Products:</h5>
                                          <div class="grid grid-cols-2 gap-2">
                                              <?php 
                                              $displayedProducts = array_slice($products, 0, 4);
                                              foreach ($displayedProducts as $product):
                                                  list($productInfo, $productImage) = explode('|||', $product);
                                              ?>
                                                  <div class="flex items-center space-x-2">
                                                      <img src="<?= htmlspecialchars($productImage) ?>" alt="Product" class="w-10 h-10 object-cover rounded">
                                                      <span class="text-sm"><?= htmlspecialchars($productInfo) ?></span>
                                                  </div>
                                              <?php endforeach; ?>
                                          </div>
                                          <?php if (count($products) > 4): ?>
                                              <p class="text-sm text-gray-500 mt-2">and <?= count($products) - 4 ?> more item(s)</p>
                                          <?php endif; ?>
                                      </div>
                                      <div class="flex justify-between items-center">
                                          <a href="order_details.php?id=<?= $order['id'] ?>" class="text-blue-600 hover:text-blue-800">View Details</a>
                                          <?php if ($order['status'] == 'pending'): ?>
                                              <a href="payment.php?order_id=<?= $order['id'] ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Pay Now</a>
                                          <?php elseif ($order['status'] == 'processing' || $order['status'] == 'shipped'): ?>
                                              <button onclick="cancelOrder(<?= $order['id'] ?>)" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Cancel Order</button>
                                          <?php elseif ($order['status'] == 'delivered'): ?>
                                              <button onclick="requestReturn(<?= $order['id'] ?>)" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">Request Return</button>
                                          <?php endif; ?>
                                      </div>
                                  </div>
                              <?php endforeach; ?>
                          </div>
                      <?php endif; ?>
                  </div>
              <?php endforeach; ?>
          </section>

          <!-- Event Products Section -->
          <section id="christmas-products" class="mb-16">
              <h2 class="text-3xl font-bold mb-8 text-center text-green-800">Christmas Collection</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                  <?php if (empty($christmas_products)): ?>
                      <p class="col-span-full text-center text-gray-600">No Christmas products available at the moment.</p>
                  <?php else: ?>
                      <?php foreach ($christmas_products as $product): ?>
                          <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300">
                              <img src="<?= htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/300x300.png?text=No+Image'); ?>" 
                                   alt="<?= htmlspecialchars($product['name']); ?>" 
                                   class="w-full h-48 object-cover">
                              <div class="p-4">
                                  <h3 class="text-xl font-semibold mb-2"><?= htmlspecialchars($product['name']); ?></h3>
                                  <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                  <div class="flex justify-between items-center">
                                      <span class="text-2xl font-bold text-green-600">₱<?= number_format($product['price'], 2); ?></span>
                                      <button onclick="addToCart(<?= $product['id']; ?>)" class="bg-red-500 text-white px-4 py-2 rounded-full hover:bg-red-600 transition duration-300">
                                          Add to Cart
                                      </button>
                                  </div>
                                  <button onclick="toggleWishlist(<?= $product['id']; ?>)" class="mt-2 w-full bg-pink-500 text-white px-4 py-2 rounded-full hover:bg-pink-600 transition duration-300">
                                      <?= in_array($product['id'], array_column($wishlist, 'product_id')) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                                  </button>
                              </div>
                          </div>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
          </section>

          <!-- Regular Products Section -->
          <section id="regular-products">
              <h2 class="text-3xl font-bold mb-8 text-center text-green-800">Our Products</h2>
              
              <!-- Product Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                  <?php if (empty($regular_products)): ?>
                      <p class="col-span-full text-center text-gray-600">No products found.</p>
                  <?php else: ?>
                      <?php foreach ($regular_products as $product): ?>
                          <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300">
                              <img src="<?= htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/300x300.png?text=No+Image'); ?>" 
                                   alt="<?= htmlspecialchars($product['name']); ?>" 
                                   class="w-full h-48 object-cover">
                              <div class="p-4">
                                  <h3 class="text-xl font-semibold mb-2"><?= htmlspecialchars($product['name']); ?></h3>
                                  <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                  <div class="flex justify-between items-center">
                                      <span class="text-2xl font-bold text-green-600">₱<?= number_format($product['price'], 2); ?></span>
                                      <button onclick="addToCart(<?= $product['id']; ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-full hover:bg-blue-600 transition duration-300">
                                          Add to Cart
                                      </button>
                                  </div>
                                  <button onclick="toggleWishlist(<?= $product['id']; ?>)" class="mt-2 w-full bg-pink-500 text-white px-4 py-2 rounded-full hover:bg-pink-600 transition duration-300">
                                      <?= in_array($product['id'], array_column($wishlist, 'product_id')) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                                  </button>
                                  <?php if (!is_null($product['event_category_id']) && strtolower($product['event_category_name']) !== 'christmas'): ?>
                                      <p class="mt-2 text-sm text-gray-500">Event: <?= htmlspecialchars($product['event_category_name']); ?></p>
                                  <?php endif; ?>
                              </div>
                          </div>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
          </section>
      </main>

      <footer class="bg-green-800 text-white py-8">
          <div class="container mx-auto px-4 text-center">
              <p>&copy; 2023 Wastewise E-commerce. All rights reserved.</p>
              <p class="mt-2">Committed to a sustainable future through recycling and eco-friendly shopping.</p>
          </div>
      </footer>
  </div>

  <!-- Quantity Modal -->
  <div id="quantity-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3 text-center">
              <button id="close-modal" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900">
                  <i class="fas fa-times"></i>
              </button>
              <h3 class="text-lg leading-6 font-medium text-gray-900">Select Quantity</h3>
              <div class="mt-2 px-7 py-3">
                  <input type="number" id="quantity-input" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
              </div>
              <div class="items-center px-4 py-3">
                  <button id="add-to-cart-btn" class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                      Add to Cart
                  </button>
              </div>
          </div>
      </div>
  </div>

  <!-- Success Modal -->
  <div id="success-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3 text-center">
              <button id="close-success-modal" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900">
                  <i class="fas fa-times"></i>
              </button>
              <h3 class="text-lg leading-6 font-medium text-gray-900">Successfully Added to Cart</h3>
              <div class="mt-2 px-7 py-3">
                  <p class="text-sm text-gray-500">Your item has been added to the cart.</p>
              </div>
              <div class="items-center px-4 py-3">
                  <a href="cart.php" class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300 inline-block">
                      View Cart
                  </a>
              </div>
          </div>
      </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
      function toggleSidebar() {
          const sidebar = document.getElementById('sidebar');
          sidebar.classList.toggle('-translate-x-full');
      }

      function showSection(sectionId) {
          document.getElementById('regular-products').style.display = sectionId === 'home' ? 'block' : 'none';
          document.getElementById('christmas-products').style.display = sectionId === 'home' ? 'block' : 'none';
          document.getElementById('notifications').style.display = sectionId === 'notifications' ? 'block' : 'none';
      }

      let currentProductId = null;

      function addToCart(productId) {
          currentProductId = productId;
          document.getElementById('quantity-modal').classList.remove('hidden');
      }

      document.getElementById('add-to-cart-btn').addEventListener('click', function() {
          const quantity = document.getElementById('quantity-input').value;
          
          // Show loading state
          this.disabled = true;
          this.innerHTML = 'Adding...';
          
          // AJAX request to add item to cart
          fetch('add_to_cart.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `product_id=${currentProductId}&quantity=${quantity}`
          })
          .then(response => response.json())
          .then(data => {
              // Reset button state
              this.disabled = false;
              this.innerHTML = 'Add to Cart';
              
              if (data.success) {
                  document.getElementById('quantity-modal').classList.add('hidden');
                  document.getElementById('success-modal').classList.remove('hidden');
              } else {
                  alert(data.message || 'Failed to add item to cart. Please try again.');
              }
          })
          .catch(error => {
              // Reset button state
              this.disabled = false;
              this.innerHTML = 'Add to Cart';
              
              console.error('Error:', error);
              alert('An error occurred. Please try again.');
          });
      });

      document.getElementById('close-modal').addEventListener('click', function() {
          document.getElementById('quantity-modal').classList.add('hidden');
      });

      document.getElementById('close-success-modal').addEventListener('click', function() {
          document.getElementById('success-modal').classList.add('hidden');
      });

      function toggleWishlist(productId) {
          const action = event.target.textContent.includes('Add') ? 'add' : 'remove';
          fetch('home.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `action=${action}&product_id=${productId}`
          })
          .then(response => response.text())
          .then(() => {
              location.reload();
          })
          .catch(error => console.error('Error:', error));
      }

      function cancelOrder(orderId) {
        if (confirm('Are you sure you want to cancel this order?')) {
            // Send AJAX request to cancel the order
            fetch('cancel_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order cancelled successfully');
                    location.reload(); // Reload the page to reflect the changes
                } else {
                    alert('Failed to cancel the order. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    }

    function switchTab(tabId) {
        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            if (button.dataset.tab === tabId) {
                button.classList.remove('border-transparent', 'text-gray-500');
                button.classList.add('border-green-500', 'text-green-600');
            } else {
                button.classList.remove('border-green-500', 'text-green-600');
                button.classList.add('border-transparent', 'text-gray-500');
            }
        });

        // Show/hide order sections
        document.querySelectorAll('.order-section').forEach(section => {
            section.classList.toggle('hidden', section.id !== `${tabId}-orders`);
        });
    }

    function requestReturn(orderId) {
        if (confirm('Are you sure you want to request a return for this order?')) {
            fetch('request_return.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Return request submitted successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to submit return request. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    }
  </script>
</body>
</html>
