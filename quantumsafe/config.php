<?php
// config.php - Database connection for QuantumSafe AI

$host = "localhost";       // Database host (usually localhost in XAMPP/WAMP)
$user = "root";            // MySQL username (default: root)
$pass = "";                // MySQL password (default is empty in XAMPP)
$db   = "quantumsafe";     // Database name you created

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Optional: set charset for safety
$conn->set_charset("utf8mb4");
?>
