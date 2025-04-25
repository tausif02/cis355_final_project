<?php
require 'database\database.php';
$pdo = Database::connect();

$status = '';
$message = '';

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
        $status = 'success';
        $message = '✅ Email verified successfully! You can now log in.';
    } else {
        $status = 'danger';
        $message = '❌ Invalid or expired verification link.';
    }
} else {
    $status = 'danger';
    $message = '❌ Missing parameters.';
}

Database::disconnect();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg p-4 rounded-4" style="max-width: 500px; width: 100%;">
            <h3 class="text-center mb-4">Email Verification</h3>

            <div class="alert alert-<?= htmlspecialchars($status) ?> text-center" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>

            <?php if ($status === 'success'): ?>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-outline-primary rounded-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="d-grid">
                    <a href="register.php" class="btn btn-outline-secondary rounded-3">
                        <i class="bi bi-person-plus me-1"></i> Register Again
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>

</html>