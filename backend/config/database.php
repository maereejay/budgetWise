<?php
function connectDB() {
$host = "localhost";
$user = "root";
$pass = "";
$database = "budgetWise";

$conn = new mysqli($host, $user, $pass, $database);
if($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}  
return $conn;
}

