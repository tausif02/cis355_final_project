<?php
session_start();
require 'database.php';
$pdo = Database::connect();

$error = '';
$success = '';
$verification_link = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $phone = trim($_POST['phone']); // Optional phone number

    if (!empty($fname) && !empty($lname) && !empty($email) && !empty($password) && !empty($confirm_password)) {
        if (!preg_match('/@svsu\.edu$/i', $email)) {
            $error = "You must register with an @svsu.edu email address.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!empty($phone) && !preg_match('/^\(\d{3}\) \d{3}-\d{4}$/', $phone)) {
            $error = "Phone number must be in the format (xxx) xxx-xxxx.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM iss_persons WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "An account with that email already exists.";
            } else {
                $salt = trim($password); // Use the password field as the salt
                $pwd_hash = md5("learn" . $salt); // Generate hash using learn + salt
                $token = bin2hex(random_bytes(16)); // Email verification token

                $stmt = $pdo->prepare("INSERT INTO iss_persons (fname, lname, email, mobile, pwd_hash, pwd_salt, admin, verified, verify_token) 
                                       VALUES (:fname, :lname, :email, :mobile, :pwd_hash, :pwd_salt, 0, 0, :verify_token)");
                $stmt->bindParam(':fname', $fname);
                $stmt->bindParam(':lname', $lname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':mobile', $phone); // Bind phone number
                $stmt->bindParam(':pwd_hash', $pwd_hash);
                $stmt->bindParam(':pwd_salt', $salt); // Store the salt for reference
                $stmt->bindParam(':verify_token', $token);

                if ($stmt->execute()) {
                    // Generate verification link
                    $verification_link = "http://localhost/CS355/Final/verify_email.php?email=" . urlencode($email) . "&token=" . urlencode($token);

                    // Simulate sending an email (replace this with actual email-sending logic)
                    $message = "Click the following link to verify your email: " . $verification_link;
                    @mail($email, "Verify Your Email", $message);

                    $success = "Registration successful! Please verify your email using the link below.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
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
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <h2>Register New User</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
        <p><a href="<?php echo htmlspecialchars($verification_link); ?>">Click here to verify your email</a></p>
    <?php endif; ?>

    <form method="POST" action="register.php" class="px-3 py-4">
    <div class="mb-3">
        <label for="fname" class="form-label">First Name:</label>
        <input type="text" class="form-control" id="fname" name="fname" required>
    </div>

    <div class="mb-3">
        <label for="lname" class="form-label">Last Name:</label>
        <input type="text" class="form-control" id="lname" name="lname" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email:</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="example@svsu.edu" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password:</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>

    <div class="mb-3">
        <label for="confirm_password" class="form-label">Verify Password:</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>

    <div class="mb-3">
        <label for="phone" class="form-label">Phone Number (optional):</label>
        <input type="text" class="form-control" id="phone" name="phone" placeholder="(xxx) xxx-xxxx" maxlength="14">
    </div>

    <button type="submit" class="btn btn-success">Register</button>
</form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
    <p>Forgot your password? <a href="forgot_password.php">Reset it here</a></p>
    <p>Need help? <a href="help.php">Get help here</a></p>
    

</div>

<script>
    const phoneInput = document.getElementById('phone');

    phoneInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove non-numeric characters
        let formattedValue = '';

        if (value.length > 0) formattedValue += '(' + value.slice(0, 3);
        if (value.length >= 4) formattedValue += ') ' + value.slice(3, 6);
        if (value.length >= 7) formattedValue += '-' + value.slice(6, 10);

        e.target.value = formattedValue;
    });

    phoneInput.addEventListener('keydown', function (e) {
        // Allow backspace, delete, tab, and arrow keys
        if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || e.key.startsWith('Arrow')) {
            return;
        }

        // Prevent entering non-numeric characters
        if (!/\d/.test(e.key)) {
            e.preventDefault();
        }
    });
</script>

</body>
</html>
