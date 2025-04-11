<?php
// filepath: c:\xampp\htdocs\CS355\Final\testConnection.php
require 'database.php'; 
$pdo = Database::connect();
echo "Database connection successful!";
Database::disconnect();
?>