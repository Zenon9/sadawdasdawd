<?php
$servername = "localhost";
$username = "root";
$password = ""; // set your MySQL password
$dbname = "school_inventory";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>