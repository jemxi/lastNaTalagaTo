<?php
session_start();

// Determine if the user was an admin
$was_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Destroy the session
session_destroy();

// Redirect based on user type
if ($was_admin) {
    header("Location: admin_login.php");
} else {
    header("Location: login.php");
}
exit();

