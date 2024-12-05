<?php
// Database connection parameters
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'wastewise';

// Create connection
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Set charset to utf8mb4
$db->set_charset("utf8mb4");

// Optionally set timezone
date_default_timezone_set('Asia/Manila');
