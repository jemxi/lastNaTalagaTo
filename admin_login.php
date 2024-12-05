<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $db->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Both username and password are required.";
    } else {
        // Hash the password using SHA256 to match the stored hash
        $hashed_password = hash('sha256', $password);
        
        $stmt = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ? AND is_admin = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if ($hashed_password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = 1;
                header("Location: admin_panel.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <span class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-blue-100">
                    <i class="fas fa-user-shield text-3xl text-blue-600"></i>
                </span>
            </div>
            <h2 class="text-4xl font-bold text-gray-800">Admin Login</h2>
            <p class="text-gray-600 mt-2">Access Wastewise admin panel</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6" autocomplete="off">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Admin Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-user-shield text-gray-400"></i>
                    </span>
                    <input type="text" id="username" name="username" 
                           class="w-full pl-10 pr-3 py-2 rounded-lg border-2 border-gray-200 outline-none focus:border-blue-500"
                           required autocomplete="off">
                </div>
            </div>

            <div>
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-lock text-gray-400"></i>
                    </span>
                    <input type="password" id="password" name="password" 
                           class="w-full pl-10 pr-3 py-2 rounded-lg border-2 border-gray-200 outline-none focus:border-blue-500"
                           required autocomplete="new-password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Sign in to Admin Panel
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to User Login
            </a>
        </div>
    </div>
</body>
</html>

