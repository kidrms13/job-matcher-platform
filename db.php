<?php
// db.php - Handles the connection to the MySQL database
$host = 'localhost';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password is empty
$database = 'jobmatcher_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>