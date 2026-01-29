<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$timezone = date_default_timezone_set("America/Toronto");

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "moolabs";

// Create mysqli connection (keeping existing code)
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if(mysqli_connect_errno()) {
    echo "Failed to connect: " . mysqli_connect_errno();
}

// Create PDO connection
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    echo "PDO Connection failed: " . $e->getMessage();
}
?>