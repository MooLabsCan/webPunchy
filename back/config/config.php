<?php
ob_start();

// Only start a session if none exists
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

if (isset($_GET['userTimezone'])) { // Assuming the timezone is sent via GET
	$timezone = $_GET['userTimezone'];

	// Validate the timezone before using it
	if (in_array($timezone, timezone_identifiers_list())) {
		$_SESSION['userTimezone'] = $timezone; // Save to session
		date_default_timezone_set($timezone); // Set as default timezone
	} else {
		// Invalid timezone, fallback to the server's default
		$timezone = date_default_timezone_get();
		date_default_timezone_set($timezone);
	}
} elseif (isset($_SESSION['userTimezone'])) {
	// If the timezone is already stored in the session, use it
	$timezone = $_SESSION['userTimezone'];
	date_default_timezone_set($timezone);
} else {
	// Fallback to server's default timezone
	$timezone = date_default_timezone_get();
	date_default_timezone_set($timezone);
}
// MySQLi connection
$con = mysqli_connect("localhost", "root", "", "punchy");


// PDO connection
try {

// Increase memory limit
	ini_set('memory_limit', '4024M'); // Sets memory limit to 1024MB
	$dsn = "mysql:host=localhost;dbname=mywords";
	$pdo = new PDO($dsn, "root", "");
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
	echo "PDO connection failed: " . $e->getMessage();
}

if (mysqli_connect_errno()) {
	echo "Failed to connect: " . mysqli_connect_errno();
}
?>
