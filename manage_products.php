<?php
// Ensure this file is included and not accessed directly
if (!defined('ADMIN_PANEL')) {
  die('Direct access not permitted');
}

// Function to get all products
function getProducts() {
  global $db;
  $result = $db->query("SELECT p.*, ec.name as event_category_name FROM products p LEFT JOIN event_categories ec ON p.event_category_id = ec.id ORDER BY p.created_at DESC");
  return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all event categories
function getEventCategories() {
  global $db;
  $result = $db->query("SELECT * FROM event_categories ORDER BY name ASC");
  return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to add a new product
function addProduct($name, $description, $price, $stock, $category, $image_path, $event_category_id) {
  global $db;

  // Check for existing product with the same name and category
  $check_stmt = $db->prepare("SELECT id FROM products WHERE name = ? AND category = ?");
  $check_stmt->bind_param("ss", $name, $category);
  $check_stmt->execute();
  $check_stmt->store_result();

  if ($check_stmt->num_rows > 0) {
      return "Product already exists!";
  }

  // Proceed to add the product if no duplicate exists
  $stmt = $db->prepare("INSERT INTO products (name, description, price, stock, category, image, event_category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssdissi", $name, $description, $price, $stock, $category, $image_path, $event_category_id);
  return $stmt->execute();
}

// Function to add a new event category
function addEventCategory($name, $description) {
  global $db;
  $stmt = $db->prepare("INSERT INTO event_categories (name, description) VALUES (?, ?)");
  $stmt->bind_param("ss", $name, $description);
  return $stmt->execute();
}

// Function to update a product
function updateProduct($id, $name, $description, $price, $stock, $category, $image_path, $event_category_id) {
  global $db;
  $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, image = ?, event_category_id = ? WHERE id = ?");
  $stmt->bind_param("ssdissii", $name, $description, $price, $stock, $category, $image_path, $event_category_id, $id);
  return $stmt->execute();
}

// Function to delete a product
function deleteProduct($id) {
    global $db;
    
    try {
        // Start transaction
        $db->begin_transaction();
        
        // First delete related order items
        $stmt = $db->prepare("DELETE FROM order_items WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Then delete the product
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // If we get here, commit the transaction
        $db->commit();
        return true;
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $db->rollback();
        error_log("Error deleting product: " . $e->getMessage());
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['add_event_category'])) {
      $result = addEventCategory($_POST['event_name'], $_POST['event_description']);
      if ($result) {
          $_SESSION['success_message'] = "Event category added successfully.";
      } else {
          $_SESSION['error_message'] = "Failed to add event category.";
      }
  } elseif (isset($_POST['add_product']) || isset($_POST['update_product'])) {
      $name = $_POST['name'];
      $description = $_POST['description'];
      $price = $_POST['price'];
      $stock = $_POST['stock'];
      $category = $_POST['category'];
      $event_category_id = $_POST['event_category_id'] ?: null;

      $target_dir = "../uploads/";
      if (!is_dir($target_dir)) {
          mkdir($target_dir, 0777, true);
      }

      $image_file = $_FILES['image'];
      $image_path = null;

      if ($image_file['size'] > 0) {
          $image_name = basename($image_file["name"]);
          $target_file = $target_dir . $image_name;
          $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

          // Check if image file is an actual image
          $check = getimagesize($image_file["tmp_name"]);
          if($check !== false) {
              // Allow certain file formats
              if($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
                  if (move_uploaded_file($image_file["tmp_name"], $target_file)) {
                      $image_path = $target_file;
                  } else {
                      $_SESSION['error_message'] = "Sorry, there was an error uploading your file.";
                  }
              } else {
                  $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
              }
          } else {
              $_SESSION['error_message'] = "File is not an image.";
          }
      }

      if (!isset($_SESSION['error_message'])) {
          if (isset($_POST['add_product'])) {
              $result = addProduct($name, $description, $price, $stock, $category, $image_path, $event_category_id);
              if ($result === true) {
                  $_SESSION['success_message'] = "Product added successfully.";
              } else {
                  $_SESSION['error_message'] = $result;
              }
          } elseif (isset($_POST['update_product'])) {
              $id = $_POST['product_id'];
              if (!$image_path) {
                  // If no new image was uploaded, keep the existing image
                  $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
                  $stmt->bind_param("i", $id);
                  $stmt->execute();
                  $result = $stmt->get_result();
                  $product = $result->fetch_assoc();
                  $image_path = $product['image'];
              }
              $result = updateProduct($id, $name, $description, $price, $stock, $category, $image_path, $event_category_id);
              if ($result) {
                  $_SESSION['success_message'] = "Product updated successfully.";
              } else {
                  $_SESSION['error_message'] = "Failed to update product.";
              }
          }
      }
  } elseif (isset($_POST['delete_product'])) {
      $id = $_POST['product_id'];
      $result = deleteProduct($id);
      if ($result) {
          $_SESSION['success_message'] = "Product deleted successfully.";
      } else {
          $_SESSION['error_message'] = "Failed to delete product.";
      }
  }
  
  // Redirect to prevent form resubmission
  header("Location: " . $_SERVER['PHP_SELF'] . "?page=manage_products");
  exit;
}

$products = getProducts();
$event_categories = getEventCategories();
?>

<div class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold mb-8">Manage Products and Events</h1>

  <?php if (isset($_SESSION['success_message'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
      </div>
      <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
      </div>
      <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- Add Event Category Form -->
  <div class="bg-white p-6 rounded-lg shadow-md mb-8">
      <h2 class="text-2xl font-semibold mb-4">Add New Event Category</h2>
      <form action="" method="POST">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <input type="text" name="event_name" placeholder="Event Category Name" required class="border p-2 rounded">
              <textarea name="event_description" placeholder="Event Category Description" class="border p-2 rounded" required></textarea>
          </div>
          <button type="submit" name="add_event_category" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Add Event Category</button>
      </form>
  </div>

  <!-- Add/Edit Product Form -->
  <div class="bg-white p-6 rounded-lg shadow-md mb-8">
      <h2 class="text-2xl font-semibold mb-4" id="productFormTitle">Add New Product</h2>
      <form action="" method="POST" enctype="multipart/form-data" id="productForm">
          <input type="hidden" name="product_id" id="productId">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <input type="text" name="name" id="productName" placeholder="Product Name" required class="border p-2 rounded">
              <input type="number" name="price" id="productPrice" placeholder="Price" step="0.01" required class="border p-2 rounded">
              <input type="number" name="stock" id="productStock" placeholder="Stock" required class="border p-2 rounded">
              <select name="category" id="productCategory" class="border p-2 rounded">
                  <option value="Paper">Paper</option>
                  <option value="Plastic">Plastic</option>
                  <option value="Metal">Metal</option>
                  <option value="Glass">Glass</option>
                  <option value="Electronics">Electronics</option>
                  <option value="Textiles">Textiles</option>
              </select>
              <textarea name="description" id="productDescription" placeholder="Description" class="border p-2 rounded col-span-2" required></textarea>
              <input type="file" name="image" id="productImage" accept="image/*" class="border p-2 rounded col-span-2">
              <select name="event_category_id" id="productEventCategory" class="border p-2 rounded">
                  <option value="">Select Event Category (Optional)</option>
                  <?php foreach ($event_categories as $event_category): ?>
                      <option value="<?= $event_category['id']; ?>"><?= htmlspecialchars($event_category['name']); ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <button type="submit" name="add_product" id="submitProductBtn" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add Product</button>
      </form>
  </div>

  <!-- Product List -->
  <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-semibold mb-4">Product List</h2>
      <div class="overflow-x-auto">
          <table class="min-w-full leading-normal">
              <thead>
                  <tr class="bg-gray-200">
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Image</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Price</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Stock</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Event Category</th>
                      <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($products as $product): ?>
                  <tr>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                          <img src="<?= $product['image']; ?>" alt="Product Image" class="w-20 h-20 object-cover">
                      </td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($product['name']); ?></td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">₱<?= number_format($product['price'], 2); ?></td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $product['stock']; ?></td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $product['category']; ?></td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $product['event_category_name'] ?? 'N/A'; ?></td>
                      <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                          <button onclick="editProduct(<?= htmlspecialchars(json_encode($product)); ?>)" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 mr-2">Edit</button>
                          <form onsubmit="return confirmDelete();" method="POST" class="inline-block">
                              <input type="hidden" name="delete_product" value="1">
                              <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                              <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Delete</button>
                          </form>
                      </td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      </div>
  </div>
</div>

<script>
function editProduct(product) {
    document.getElementById('productFormTitle').innerText = 'Edit Product';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productStock').value = product.stock;
    document.getElementById('productCategory').value = product.category;
    document.getElementById('productDescription').value = product.description;
    document.getElementById('productEventCategory').value = product.event_category_id || '';
    document.getElementById('submitProductBtn').innerText = 'Update Product';
    document.getElementById('submitProductBtn').name = 'update_product';
    document.getElementById('productForm').scrollIntoView({behavior: 'smooth'});
}

function confirmDelete() {
    return confirm('Are you sure you want to delete this product?');
}
</script>

