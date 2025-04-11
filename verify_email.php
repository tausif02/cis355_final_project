<?php
require 'database.php';
$pdo = Database::connect();

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT * FROM iss_persons WHERE email = :email AND verify_token = :token AND verified = 0");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $update = $pdo->prepare("UPDATE iss_persons SET verified = 1, verify_token = NULL WHERE email = :email");
        $update->bindParam(':email', $email);
        $update->execute();
        echo "Email verified successfully! You can now <a href='login.php'>log in</a>.";
    } else {
        echo "Invalid or expired verification link.";
    }
} else {
    echo "Missing parameters.";
}

Database::disconnect();
?>
