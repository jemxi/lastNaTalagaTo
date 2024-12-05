<?php
// Ensure this file is included and not accessed directly
if (!defined('ADMIN_PANEL')) {
    die('Direct access not permitted');
}

// Function to get users with pagination and filtering
function getUsers($page = 1, $limit = 10, $search = '') {
    global $db;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT * FROM users WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE 1=1";
    
    if (!empty($search)) {
        $search = $db->real_escape_string($search);
        $query .= " AND (username LIKE '%$search%' OR email LIKE '%$search%')";
        $countQuery .= " AND (username LIKE '%$search%' OR email LIKE '%$search%')";
    }
    
    // Fix the LIMIT clause syntax
    $query .= " ORDER BY created_at DESC LIMIT {$offset}, {$limit}";
    
    $result = $db->query($query);
    if (!$result) {
        die("Database query failed: " . $db->error);
    }
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    $countResult = $db->query($countQuery);
    if (!$countResult) {
        die("Count query failed: " . $db->error);
    }
    $totalUsers = $countResult->fetch_assoc()['total'];
    
    return [
        'users' => $users,
        'total' => $totalUsers
    ];
}

// Function to get total number of active users
function getTotalActiveUsers() {
    global $db;
    $result = $db->query("SELECT COUNT(*) as total FROM users");
    if (!$result) {
        die("Count query failed: " . $db->error);
    }
    return $result->fetch_assoc()['total'];
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if ($_POST['action'] == 'delete' && $user_id > 0) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $message = "User deleted successfully.";
        } elseif ($_POST['action'] == 'toggle_admin' && $user_id > 0) {
            $stmt = $db->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $message = "User admin status toggled successfully.";
        }
    }
}

// Get current page number from URL
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get users with pagination
$usersData = getUsers($page, 10, $search);
$users = $usersData['users'];
$totalUsers = $usersData['total'];
$totalPages = ceil($totalUsers / 10);

$totalActiveUsers = getTotalActiveUsers();
?>

<div class="container mx-auto px-4">
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4">User Management</h2>
        
        <!-- User Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-green-700">Total Users</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo $totalUsers; ?></p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-700">Active Users</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $totalActiveUsers; ?></p>
            </div>
        </div>

        <!-- Search Form -->
        <form action="" method="GET" class="mb-6">
            <input type="hidden" name="page" value="user_management">
            <div class="flex gap-2">
                <input type="text" name="search" placeholder="Search users..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       class="flex-1 border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                    Search
                </button>
            </div>
        </form>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Username
                        </th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Email
                        </th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Registered
                        </th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Admin
                        </th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            No users found.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-200">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-200">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-200">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-200">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['is_admin'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $user['is_admin'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-200 text-sm font-medium">
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline-block mr-2">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <button type="submit" class="text-blue-600 hover:text-blue-900">
                                            <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-gray-400">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=user_management&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50
                              <?php echo $page === $i ? 'bg-green-50 text-green-600' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

