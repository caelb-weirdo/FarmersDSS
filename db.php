<?php
declare(strict_types=1);

session_start();

$host = 'localhost';
$db   = 'farmer_dss';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to check if logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['full_name']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: logout.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

function requireAdmin() {
    if (!isAdmin()) {
        die("Unauthorized access. Admin privileges required.");
    }
}
?>
