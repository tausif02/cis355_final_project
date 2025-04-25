<?php
session_start();
require 'database\database.php';
$pdo = Database::connect();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, admin, pwd_hash, pwd_salt, verified FROM iss_persons WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['verified'] == 0) {
                $error = "Please verify your email before logging in.";
            } else {
                $hashed_password = md5("learn" . $password);

                if ($hashed_password === $user['pwd_hash']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['admin'] = $user['admin'];
                    header("Location: issues_list.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

Database::disconnect();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg p-4 rounded-4" style="max-width: 400px; width: 100%;">
            <h3 class="text-center mb-4">Login</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                        <input type="email" class="form-control shadow-sm rounded-3" id="email" name="email" placeholder="example@svsu.edu" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control shadow-sm rounded-3" id="password" name="password" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary rounded-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </button>
                </div>
            </form>

            <div class="mt-3 text-center">
                <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>

</html>