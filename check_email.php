<?php
require 'database/database.php';
$pdo = Database::connect();

if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    $current_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM iss_persons WHERE email = ? AND id != ?");
    $stmt->execute([$email, $current_id]);
    $count = $stmt->fetchColumn();

    echo $count > 0 ? 'taken' : 'available';
}
Database::disconnect();
