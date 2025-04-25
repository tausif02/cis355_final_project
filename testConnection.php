<?php
require 'database\database.php'; 
$pdo = Database::connect();
echo "Database connection successful!";
Database::disconnect();
?>