<?php
session_start();

$db_host = 'localhost';
$db_user = 'YOUR_DB_USER';
$db_pass = 'YOUR_DB_PASSWORD';
$db_name = 'YOUR_DB_NAME';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Database Connection Failed: " . $conn->connect_error); }

// Auto-patch Users Table
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS shop_name VARCHAR(100) DEFAULT 'My Shop'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS gateway_type VARCHAR(20) DEFAULT 'fampay'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS api_key VARCHAR(100) DEFAULT ''");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS webhook_url VARCHAR(255) DEFAULT ''");

// Auto-patch Orders Table
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS txn_note VARCHAR(10) DEFAULT ''");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'direct'");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS success_time TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'success', 'failed') DEFAULT 'pending'");
?>
