<?php
// Database connection parameters
$hostname = 'localhost';
$username = 'root';
$password = ''; // Empty password as specified
$database = 'lennytest';

// Create connection
$conn = new mysqli($hostname, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");
?>
