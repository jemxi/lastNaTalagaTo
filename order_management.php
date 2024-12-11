<?php
// Ensure this file is included and not accessed directly
if (!defined('ADMIN_PANEL')) {
    die('Direct access not permitted');
}

// Function to get orders with pagination and filtering
function getOrders($page = 1, $limit = 10, $search = '') {
    global $db;
    $offset = ($page - 1) * $limit;

    $query = "SELECT o.*, u.username, 
              GROUP_CONCAT(DISTINCT p.name SEPARATOR '|||') as product_names,
              GROUP_CONCAT(DISTINCT p.image SEPARATOR '|||') as product_images,
              GROUP_CONCAT(DISTINCT oi.quantity SEPARATOR '|||') as product_quantities
              FROM orders o 
              JOIN users u ON o.user_id = u.id
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN products p ON oi.product_id = p.id
              WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";

    if (!empty($search)) {
        $search = $db->real_escape_string($search);
        $query .= " AND (o.id LIKE '%$search%' OR u.username LIKE '%$search%' OR o.name LIKE '%$search%' OR o.email LIKE '%$search%')";
        $countQuery .= " AND (o.id LIKE '%$search%' OR u.username LIKE '%$search%' OR o.name LIKE '%$search%' OR o.email LIKE '%$search%')";
    }

    $query .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT {$offset}, {$limit}";

    $result = $db->query($query);
    if (!$result) {
        die("Database query failed: " . $db->error);
    }
    $orders = $result->fetch_all(MYSQLI_ASSOC);

    $countResult = $db->query($countQuery);
    if (!$countResult) {
        die("Count query failed: " . $db->error);
    }
    $totalOrders = $countResult->fetch_assoc()['total'];

    return [
        'orders' => $orders,
        'total' => $totalOrders
    ];
}

// Function to get order details
function getOrderDetails($orderId) {
    global $db;
    $query = "SELECT oi.*, p.name as product_name, p.image as product_image 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Add a new function to handle cancellation approval
function approveCancellation($orderId) {
    global $db;
    $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'cancellation_pending'");
    $stmt->bind_param("i", $orderId);
    return $stmt->execute();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = $db->real_escape_string($_POST['new_status']);

    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $orderId);

    if ($stmt->execute()) {
        $successMessage = "Order status updated successfully.";
    } else {
        $errorMessage = "Failed to update order status.";
    }
}

// Handle cancellation approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_cancellation'])) {
    $orderId = intval($_POST['order_id']);
    if (approveCancellation($orderId)) {
        $successMessage = "Cancellation approved successfully.";
    } else {
        $errorMessage = "Failed to approve cancellation.";
    }
}

// Get current page number from URL
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get orders with pagination
$ordersData = getOrders($page, 10, $search);
$orders = $ordersData['orders'];
$totalOrders = $ordersData['total'];
$totalPages = ceil($totalOrders / 10);

?>

<div class="container mx-auto px-4">
    <h2 class="text-2xl font-bold mb-4">Order Management</h2>

    <?php if (isset($successMessage)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $successMessage; ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $errorMessage; ?></span>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <form action="" method="GET" class="mb-6">
        <input type="hidden" name="page" value="order_management">
        <div class="flex gap-2">
            <input type="text" name="search" placeholder="Search orders..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   class="flex-1 border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                Search
            </button>
        </div>
    </form>

    <!-- Orders Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Order ID</th>
                    <th class="py-3 px-6 text-left">Customer</th>
                    <th class="py-3 px-6 text-left">Products</th>
                    <th class="py-3 px-6 text-left">Total Amount</th>
                    <th class="py-3 px-6 text-left">Status</th>
                    <th class="py-3 px-6 text-left">Date</th>
                    <th class="py-3 px-6 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                <?php foreach ($orders as $order): 
                    $productNames = explode('|||', $order['product_names']);
                    $productImages = explode('|||', $order['product_images']);
                    $productQuantities = explode('|||', $order['product_quantities']);
                ?>
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left whitespace-nowrap">
                        <?php echo htmlspecialchars($order['id']); ?>
                    </td>
                    <td class="py-3 px-6 text-left">
                        <?php echo htmlspecialchars($order['name']); ?><br>
                        <span class="text-gray-500"><?php echo htmlspecialchars($order['email']); ?></span>
                    </td>
                    <td class="py-3 px-6 text-left">
                        <div class="flex flex-col space-y-2">
                            <?php for ($i = 0; $i < min(count($productNames), 3); $i++): ?>
                                <div class="flex items-center space-x-2">
                                    <img src="<?php echo htmlspecialchars($productImages[$i]); ?>" alt="<?php echo htmlspecialchars($productNames[$i]); ?>" class="w-10 h-10 object-cover rounded">
                                    <span><?php echo htmlspecialchars($productNames[$i]) . ' (x' . $productQuantities[$i] . ')'; ?></span>
                                </div>
                            <?php endfor; ?>
                            <?php if (count($productNames) > 3): ?>
                                <span class="text-sm text-gray-500">and <?php echo count($productNames) - 3; ?> more...</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="py-3 px-6 text-left">
                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                    </td>
                    <td class="py-3 px-6 text-left">
                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php
                            switch($order['status']) {
                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'processing': echo 'bg-blue-100 text-blue-800'; break;
                                case 'shipped': echo 'bg-purple-100 text-purple-800'; break;
                                case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                case 'cancellation_pending': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td class="py-3 px-6 text-left">
                        <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                    </td>
                    <td class="py-3 px-6 text-left">
                        <button onclick="showOrderDetails(<?php echo $order['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-2">
                            View Details
                        </button>
                        <button onclick="showUpdateStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" class="text-green-600 hover:text-green-900">
                            Update Status
                        </button>
                        <?php if ($order['status'] === 'cancellation_pending'): ?>
                            <button onclick="approveCancellation(<?php echo $order['id']; ?>)" class="text-red-600 hover:text-red-900 ml-2">
                                Approve Cancellation
                            </button>
                        <?php endif; ?>
                        <?php if ($order['status'] === 'cancelled'): ?>
                            <button onclick="archiveOrder(<?php echo $order['id']; ?>)" class="text-gray-600 hover:text-gray-900">
                                Archive
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-4 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=order_management&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50
                          <?php echo $page === $i ? 'bg-green-50 text-green-600' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
    </div>
    <?php endif; ?>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Order Details
                    </h3>
                    <div class="mt-2" id="orderDetailsContent">
                        <!-- Order details will be populated here -->
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeOrderDetails()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Update Order Status
                    </h3>
                    <div class="mt-2">
                        <form id="updateStatusForm" method="POST">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" id="updateOrderId" name="order_id" value="">
                            <select name="new_status" id="newStatus" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="cancellation_pending">Cancellation Pending</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="submitUpdateStatus()">
                        Update
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeUpdateStatus()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showOrderDetails(orderId) {
    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            let content = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p><strong>Order ID:</strong> ${data.order.id}</p>
                        <p><strong>Customer:</strong> ${data.order.name}</p>
                        <p><strong>Email:</strong> ${data.order.email}</p>
                        <p><strong>Phone:</strong> ${data.order.phone}</p>
                    </div>
                    <div>
                        <p><strong>Address:</strong> ${data.order.address}, ${data.order.barangay}, ${data.order.city}, ${data.order.province}, ${data.order.region}, ${data.order.country}</p>
                        <p><strong>ZIP:</strong> ${data.order.zip}</p>
                        <p><strong>Total Amount:</strong> ₱${parseFloat(data.order.total_amount).toFixed(2)}</p>
                        <p><strong>Status:</strong> <span class="px-2 py-1 rounded-full text-sm font-semibold ${getStatusColor(data.order.status)}">${data.order.status}</span></p>
                        <p><strong>Date:</strong> ${new Date(data.order.created_at).toLocaleString()}</p>
                    </div>
                </div>
                <h4 class="font-bold mt-4 mb-2">Order Items:</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">`;
            data.items.forEach(item => {
                content += `
                    <div class="flex items-center space-x-4 border p-2 rounded">
                        <img src="${item.product_image}" alt="${item.product_name}" class="w-16 h-16 object-cover rounded">
                        <div>
                            <p class="font-semibold">${item.product_name}</p>
                            <p>Quantity: ${item.quantity}</p>
                            <p>Price: ₱${parseFloat(item.price).toFixed(2)}</p>
                        </div>
                    </div>`;
            });
            content += '</div>';
            document.getElementById('orderDetailsContent').innerHTML = content;
            document.getElementById('orderDetailsModal').classList.remove('hidden');
        })
        .catch(error => console.error('Error:', error));
}

function getStatusColor(status) {
    switch(status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'shipped': return 'bg-purple-100 text-purple-800';
        case 'delivered': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        case 'cancellation_pending': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function closeOrderDetails() {
    document.getElementById('orderDetailsModal').classList.add('hidden');
}

function showUpdateStatus(orderId, currentStatus) {
    document.getElementById('updateOrderId').value = orderId;
    document.getElementById('newStatus').value = currentStatus;
    document.getElementById('updateStatusModal').classList.remove('hidden');
}

function closeUpdateStatus() {
    document.getElementById('updateStatusModal').classList.add('hidden');
}

function submitUpdateStatus() {
    document.getElementById('updateStatusForm').submit();
    setTimeout(() => {
        location.reload();
    }, 500);
}

function archiveOrder(orderId) {
    if (confirm('Are you sure you want to archive this order?')) {
        fetch('archive_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `order_id=${orderId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order archived successfully');
                location.reload();
            } else {
                alert('Failed to archive the order. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

function approveCancellation(orderId) {
    if (confirm('Are you sure you want to approve this cancellation?')) {
        fetch('approve_cancellation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `order_id=${orderId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cancellation approved successfully');
                location.reload();
            } else {
                alert('Failed to approve cancellation. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>
