<?php
session_start();
require 'database\database.php';
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
    $phone = trim($_POST['phone']); // Phone number (optional)

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
                $salt = trim($password); // Password field (salt)
                $pwd_hash = md5("learn" . $salt); // Generate hash using learn + salt(password)
                $token = bin2hex(random_bytes(16)); // Email verification token
                $stmt = $pdo->prepare("INSERT INTO iss_persons (fname, lname, email, mobile, pwd_hash, pwd_salt, admin, verified, verify_token) 
                                       VALUES (:fname, :lname, :email, :mobile, :pwd_hash, :pwd_salt, 'N', 0, :verify_token)");
                $stmt->bindParam(':fname', $fname);
                $stmt->bindParam(':lname', $lname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':mobile', $phone);
                $stmt->bindParam(':pwd_hash', $pwd_hash);
                $stmt->bindParam(':pwd_salt', $salt);
                $stmt->bindParam(':verify_token', $token);
                if ($stmt->execute()) {
                    // Generate verification link
                    $verification_link = "http://localhost/CS355/Final/verify_email.php?email=" . urlencode($email) . "&token=" . urlencode($token);
                    // Simulating sending an email for verification
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
if (isset($_POST['create_person'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $admin = isset($_POST['admin']) ? 'Y' : 'N';
    $password = trim($_POST['password']);
    $salt = $password; // Using password itself as the salt
    $pwd_hash = md5("learn" . $password); // Hash "learn" + user_password
    // Checking if required fields are empty
    if (empty($fname)) {
        $_SESSION['error_message'] = "First Name cannot be empty.";
        header("Location: persons_list.php");
        exit();
    }
    if (empty($lname)) {
        $_SESSION['error_message'] = "Last Name cannot be empty.";
        header("Location: persons_list.php");
        exit();
    }
    if (empty($email)) {
        $_SESSION['error_message'] = "Email cannot be empty.";
        header("Location: persons_list.php");
        exit();
    }
    // Restricting email to @svsu.edu domain
    if (!preg_match('/@svsu\.edu$/i', $email)) {
        $_SESSION['error_message'] = "You must use an @svsu.edu email address.";
        header("Location: persons_list.php");
        exit();
    }
    if (empty($password)) {
        $_SESSION['error_message'] = "Password cannot be empty.";
        header("Location: persons_list.php");
        exit();
    }
    // smallest missing ID
    $id_sql = "SELECT id FROM iss_persons ORDER BY id ASC";
    $id_stmt = $pdo->query($id_sql);
    $existing_ids = $id_stmt->fetchAll(PDO::FETCH_COLUMN);

    $new_id = 1; // ID 1
    foreach ($existing_ids as $existing_id) {
        if ($existing_id == $new_id) {
            $new_id++;
        } else {
            break; // gap
        }
    }
    // Inserting new user with the calculated ID
    $sql = "INSERT INTO iss_persons (id, fname, lname, email, mobile, pwd_hash, pwd_salt, admin) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_id, $fname, $lname, $email, $mobile, $pwd_hash, $salt, $admin]);

    header("Location: persons_list.php");
    exit();
}
Database::disconnect();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
</head>

<body class="bg-light">

    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg p-4 rounded-4" style="max-width: 500px; width: 100%;">
            <h3 class="text-center mb-3">Register New User</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-center" role="alert">
                    <?php echo htmlspecialchars($success); ?><br />
                    <a href="<?php echo htmlspecialchars($verification_link); ?>" class="btn btn-outline-success mt-2">Verify Now</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label for="fname" class="form-label">First Name</label>
                    <input type="text" class="form-control shadow-sm rounded-3" id="fname" name="fname" required />
                </div>

                <div class="mb-3">
                    <label for="lname" class="form-label">Last Name</label>
                    <input type="text" class="form-control shadow-sm rounded-3" id="lname" name="lname" required />
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control shadow-sm rounded-3" id="email" name="email" placeholder="example@svsu.edu" required />
                    </div>
                    <div id="email-error" class="form-text text-danger d-none">Email must end in @svsu.edu.</div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control shadow-sm rounded-3" id="password" name="password" required />
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Verify Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control shadow-sm rounded-3" id="confirm_password" name="confirm_password" required />
                    </div>
                    <div id="password-error" class="form-text text-danger d-none">Passwords do not match.</div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number (optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="text" class="form-control shadow-sm rounded-3" id="phone" name="phone" placeholder="(xxx) xxx-xxxx" maxlength="14" />
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-success rounded-3">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </button>
                </div>
            </form>

            <div class="mt-3 text-center">
                <p>Already have an account? <a href="login.php">Login</a></p>
                <p><a href="forgot_password.php">Forgot password?</a></p>
                <p><a href="help.php">Get help here</a></p>
            </div>
        </div>
    </div>

    <script>
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordError = document.getElementById('password-error');
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('email-error');

        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formatted = '';
            if (value.length > 0) formatted += '(' + value.slice(0, 3);
            if (value.length >= 4) formatted += ') ' + value.slice(3, 6);
            if (value.length >= 7) formatted += '-' + value.slice(6, 10);
            e.target.value = formatted;
        });

        phoneInput.addEventListener('keydown', function(e) {
            if (["Backspace", "Delete", "Tab"].includes(e.key) || e.key.startsWith("Arrow")) return;
            if (!/\d/.test(e.key)) e.preventDefault();
        });

        confirmPasswordInput.addEventListener('blur', function() {
            passwordError.classList.toggle('d-none', passwordInput.value === confirmPasswordInput.value);
        });

        confirmPasswordInput.addEventListener('input', function() {
            if (!passwordError.classList.contains('d-none') && passwordInput.value === confirmPasswordInput.value) {
                passwordError.classList.add('d-none');
            }
        });

        emailInput.addEventListener('blur', function() {
            const pattern = /^[^@]+@svsu\.edu$/i;
            emailError.classList.toggle('d-none', pattern.test(emailInput.value));
        });

        emailInput.addEventListener('input', function() {
            const pattern = /^[^@]+@svsu\.edu$/i;
            if (!emailError.classList.contains('d-none') && pattern.test(emailInput.value)) {
                emailError.classList.add('d-none');
            }
        });
        emailInput.addEventListener('blur', function() {
            const pattern = /^[^@]+@svsu\.edu$/i;
            const email = emailInput.value.trim();

            if (!pattern.test(email)) {
                emailError.textContent = 'Email must end in @svsu.edu.';
                emailError.classList.remove('d-none');
                return;
            }

            fetch(`verify_email.php?email=${encodeURIComponent(email)}`)
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'taken') {
                        emailError.textContent = 'This email is already registered.';
                        emailError.classList.remove('d-none');
                    } else {
                        emailError.classList.add('d-none');
                        emailError.textContent = 'Email must end in @svsu.edu.'; 
                    }
                })
                .catch(() => {
                    emailError.textContent = 'Could not check email. Try again.';
                    emailError.classList.remove('d-none');
                });
        });
    </script>
</body>

</html>