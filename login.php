<?php
session_start();
require 'database.php'; // Corrected path
$pdo = Database::connect();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $salt = trim($_POST['password']); // Treat the password field as the "salt"

    if (!empty($email) && !empty($salt)) {
        try {
            // Prepare SQL statement
            $stmt = $pdo->prepare("SELECT * FROM iss_persons WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user['verified'] == 0) {
                    $error = "Your account is not verified. Please check your email.";
                } else {
                    // Extract values
                    $id = $user['id'];
                    $fname = $user['fname'];
                    $lname = $user['lname'];
                    $stored_hash = $user['pwd_hash'];
                    $admin = $user['admin'];

                    // Generate the hash using learn + salt
                    $generated_hash = md5("learn" . $salt);

                    // Debugging output (optional, remove in production)
                    error_log("Entered Salt: " . $salt);
                    error_log("Generated Hash: " . $generated_hash);
                    error_log("Stored Hash: " . $stored_hash);
                    
                    if ($generated_hash === $stored_hash) {
                        // Authentication successful, set session variables
                        $_SESSION['user_id'] = $id; // this is checked to verify login
                        $_SESSION['user_name'] = $fname . ' ' . $lname;
                        $_SESSION['email'] = $email;
                        $_SESSION['admin'] = $admin;

                        // Close connection
                        Database::disconnect();

                        header("Location: issues_list.php");
                        exit();
                    } else {
                        $error = "Invalid email or password.";
                        session_destroy();
                    }
                }
            } else {
                $error = "Invalid email or password.";
                session_destroy();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            session_destroy();
        }
    } else {
        $error = "Please enter both email and password.";
        session_destroy();
    }
}

// Close database connection
Database::disconnect();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
    <h2>Login</h2>
    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <form method="POST" action="login.php" class="px-3 py-4">
        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="example@svsu.edu" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
