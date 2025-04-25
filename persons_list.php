<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['admin'] !== "Y") {
    header("Location: login.php");
    exit();
}

require 'database\database.php';
$pdo = Database::connect();

// Fetching all persons
$sort = $_GET['sort'] ?? 'lname';
$order = $_GET['order'] ?? 'ASC';

$allowedSorts = ['id', 'fname', 'lname', 'email', 'mobile', 'admin'];
$sort = in_array($sort, $allowedSorts) ? $sort : 'lname';
$order = $order === 'DESC' ? 'DESC' : 'ASC';

// limit of persons per page
$limit = 10;

// Getting the current page from the query string, default to 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure the page is at least 1

// Fetching the total number of persons
$count_sql = "SELECT COUNT(*) FROM iss_persons";
$total_persons = $pdo->query($count_sql)->fetchColumn();

// Calculating total pages
$total_pages = ceil($total_persons / $limit);

// Ensuring the current page does not exceed the total pages
$page = min($page, $total_pages);

// Calculating the offset for the SQL query
$offset = ($page - 1) * $limit;

// Fetching persons with filtering and pagination
$filter = $_GET['filter'] ?? 'all';

$whereClause = '';
if ($filter === 'admin') {
    $whereClause = "WHERE admin = 'Y'";
} elseif ($filter === 'non-admin') {
    $whereClause = "WHERE admin = 'N'";
}

$sql = "SELECT * FROM iss_persons $whereClause ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$stmt = $pdo->query($sql);
$persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handling Create, Update, Delete operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_person'])) {
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']); // Mobile is optional
        $admin = isset($_POST['admin']) ? 'Y' : 'N';
        $password = trim($_POST['password']);
        $salt = $password; // Use the password itself as the salt
        $pwd_hash = md5("learn" . $password); // Hash "learn" + user password

        // Check if required fields are empty
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

        // Restrict email to @svsu.edu domain
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

        // Find the smallest missing ID
        $id_sql = "SELECT id FROM iss_persons ORDER BY id ASC";
        $id_stmt = $pdo->query($id_sql);
        $existing_ids = $id_stmt->fetchAll(PDO::FETCH_COLUMN);

        $new_id = 1; // Start with ID 1
        foreach ($existing_ids as $existing_id) {
            if ($existing_id == $new_id) {
                $new_id++; // Increment if the ID exists
            } else {
                break; // Found a gap
            }
        }

        // Insert the new user with the calculated ID
        $sql = "INSERT INTO iss_persons (id, fname, lname, email, mobile, pwd_hash, pwd_salt, admin, verified) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $new_id,
            $fname,
            $lname,
            $email,
            !empty($mobile) ? $mobile : '', // Insert an empty string if mobile is empty
            $pwd_hash,
            $salt,
            $admin,
            0 // unverified status
        ]);

        header("Location: persons_list.php");
        exit();
    }

    if (isset($_POST['update_person'])) {
        $id = $_POST['id'];
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        $admin = isset($_POST['admin']) ? 'Y' : 'N';

        // Checking if the password field is provided
        if (!empty($_POST['password'])) {
            $password = trim($_POST['password']);
            $salt = $password; // Using the password as the salt
            $pwd_hash = md5("learn" . $password); // Hash "learn" + user password

            // Updating all fields including password
            $sql = "UPDATE iss_persons SET fname=?, lname=?, email=?, mobile=?, pwd_hash=?, pwd_salt=?, admin=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fname, $lname, $email, $mobile, $pwd_hash, $salt, $admin, $id]);
        } else {
            // Updating only non-password fields
            $sql = "UPDATE iss_persons SET fname=?, lname=?, email=?, mobile=?, admin=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fname, $lname, $email, $mobile, $admin, $id]);
        }

        header("Location: persons_list.php");
        exit();
    }

    if (isset($_POST['delete_person'])) {
        $id = $_POST['id'];
        $sql = "DELETE FROM iss_persons WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        header("Location: persons_list.php");
        exit();
    }

    if (isset($_POST['verify_person'])) {
        $id = $_POST['id'];

        // Fetching the user's first and last name
        $sql = "SELECT fname, lname FROM iss_persons WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($person) {
            // Updating the user's verified status in the database
            $sql = "UPDATE iss_persons SET verified='1' WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // Setting the success message with the user's name
            $_SESSION['success_message'] = "{$person['fname']} {$person['lname']} has been verified successfully.";
        } else {
            $_SESSION['error_message'] = "User not found.";
        }

        header("Location: persons_list.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Query the database for the user
        $stmt = $pdo->prepare("SELECT id, admin, pwd_hash, pwd_salt, verified FROM iss_persons WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Checking if the account is verified
            if ($user['verified'] == 0) {
                $error = "Please verify your email before logging in.";
            } else {
                // Verifying the password
                $hashed_password = md5("learn" . $password); // Hash "learn" + user password

                if ($hashed_password === $user['pwd_hash']) {
                    // Setting session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['admin'] = $user['admin'];

                    // Redirecting to issues list
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
    <title>Persons List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .custom-btn,
        .btn-primary,
        .btn-secondary,
        .btn-info,
        .btn-warning,
        .btn-danger {
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
        }

        .custom-btn:hover,
        .btn-primary:hover,
        .btn-secondary:hover,
        .btn-info:hover,
        .btn-warning:hover,
        .btn-danger:hover {
            box-shadow: 0px 6px 8px rgba(0, 0, 0, 0.3);
            filter: brightness(85%);
            transform: scale(1.1);
        }

        .custom-btn:active,
        .btn-primary:active,
        .btn-secondary:active,
        .btn-info:active,
        .btn-warning:active,
        .btn-danger:active {
            box-shadow: inset 0px 4px 6px rgba(0, 0, 0, 0.2);
            transform: translateY(2px);
        }
    </style>
</head>

<body>
    <div class="container mt-3">
        <h2 class="text-center">Persons List</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="success-message" class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- Filter Dropdown -->
            <form method="GET" class="d-flex align-items-center">
                <label for="filter" class="me-2">Filter:</label>
                <select name="filter" id="filter" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    <option value="all" <?= isset($_GET['filter']) && $_GET['filter'] === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="admin" <?= isset($_GET['filter']) && $_GET['filter'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="non-admin" <?= isset($_GET['filter']) && $_GET['filter'] === 'non-admin' ? 'selected' : ''; ?>>Non-Admin</option>
                </select>
            </form>

            <!-- Buttons Section -->
            <div>
                <!-- Add Person Button -->
                <button class="btn btn-outline-success me-2 custom-btn" data-bs-toggle="modal" data-bs-target="#addPersonModal" title="Add Person">
                    <i class="fas fa-user-plus"></i>
                </button>

                <!-- Issues List Button -->
                <a href="issues_list.php" class="btn btn-outline-primary me-2 custom-btn" title="Go to Issues List">
                    <i class="fas fa-list"></i>
                </a>

                <!-- Logout Button -->
                <a href="logout.php" class="btn btn-outline-danger custom-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
        <table class="table table-striped table-sm mt-2">
            <thead class="table-dark align-middle text-center">
                <tr>
                    <th>
                        <a href="persons_list.php?sort=id&order=<?= $sort === 'id' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            ID <?= $sort === 'id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="persons_list.php?sort=fname&order=<?= $sort === 'fname' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            First Name <?= $sort === 'fname' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="persons_list.php?sort=lname&order=<?= $sort === 'lname' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            Last Name <?= $sort === 'lname' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="persons_list.php?sort=email&order=<?= $sort === 'email' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            Email <?= $sort === 'email' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="persons_list.php?sort=mobile&order=<?= $sort === 'mobile' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            Mobile <?= $sort === 'mobile' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="persons_list.php?sort=admin&order=<?= $sort === 'admin' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                            Admin <?= $sort === 'admin' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($persons as $person): ?>
                    <tr>
                        <td class="align-middle text-center"><?= htmlspecialchars($person['id']); ?><?= htmlspecialchars($person['id']); ?></td>
                        <td class="align-middle text-center"><?= htmlspecialchars($person['fname']); ?></td>
                        <td class="align-middle text-center"><?= htmlspecialchars($person['lname']); ?></td>
                        <td class="align-middle text-center"><?= htmlspecialchars($person['email']); ?></td>
                        <td class="align-middle text-center"><?= htmlspecialchars($person['mobile']); ?></td>
                        <td class="align-middle text-center">
                            <?php if ($person['admin'] === 'Y'): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">User</span>
                            <?php endif; ?>
                        </td>

                        <td class="align-middle text-center">
                            <!-- Read Button -->
                            <button class="btn btn-outline-info btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#readPersonModal<?= $person['id']; ?>" title="Read">
                                <i class="fas fa-eye"></i>
                            </button>

                            <!-- Edit Button -->
                            <button class="btn btn-outline-warning btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#updatePersonModal<?= $person['id']; ?>" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>

                            <!-- Delete Button -->
                            <button class="btn btn-outline-danger btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#deletePersonModal<?= $person['id']; ?>" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>

                            <!-- Verify Button -->
                            <?php if ($person['verified'] !== '1'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $person['id']; ?>">
                                    <button type="submit" name="verify_person" class="btn btn-outline-success btn-sm custom-btn" title="Verify">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm custom-btn" disabled title="Verified">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Read Modal -->
                    <div class="modal fade" id="readPersonModal<?= $person['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content shadow-sm border-0 rounded-3">
                                <div class="modal-header bg-info text-white border-0">
                                    <h5 class="modal-title">Person Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow:none;"></button>
                                </div>
                                <div class="modal-body px-4 py-3">
                                    <div class="mb-2"><strong>ID:</strong> <?= htmlspecialchars($person['id']); ?></div>
                                    <div class="mb-2"><strong>First Name:</strong> <?= htmlspecialchars($person['fname']); ?></div>
                                    <div class="mb-2"><strong>Last Name:</strong> <?= htmlspecialchars($person['lname']); ?></div>
                                    <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($person['email']); ?></div>
                                    <div class="mb-2"><strong>Mobile:</strong> <?= !empty($person['mobile']) ? htmlspecialchars($person['mobile']) : '—'; ?></div>
                                    <div class="mb-2"><strong>Admin:</strong> <?= $person['admin'] === 'Y' ? 'Yes' : 'No'; ?></div>
                                    <div><strong>Verified:</strong> <?= $person['verified'] === '1' ? 'Yes' : 'No'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update Modal -->
                    <div class="modal fade" id="updatePersonModal<?= $person['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content shadow-sm border-0 rounded-3">
                                <div class="modal-header bg-warning text-white border-0">
                                    <h5 class="modal-title">Update Person</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow:none;"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $person['id']; ?>">

                                        <div class="mb-2">
                                            <input type="text" name="fname" class="form-control border-0 shadow-sm rounded" placeholder="First Name" value="<?= htmlspecialchars($person['fname']); ?>" required>
                                        </div>

                                        <div class="mb-2">
                                            <input type="text" name="lname" class="form-control border-0 shadow-sm rounded" placeholder="Last Name" value="<?= htmlspecialchars($person['lname']); ?>" required>
                                        </div>

                                        <div class="mb-2">
                                            <input type="email" name="email" id="update-email-<?= $person['id']; ?>" class="form-control border-0 shadow-sm rounded" placeholder="example@svsu.edu" value="<?= htmlspecialchars($person['email']); ?>" required>
                                            <div id="update-email-error-<?= $person['id']; ?>" class="text-danger small mt-1" style="display: none;">Email must be in the format @svsu.edu.</div>
                                        </div>

                                        <div class="mb-2">
                                            <input type="text" name="mobile" id="update-mobile-<?= $person['id']; ?>" class="form-control border-0 shadow-sm rounded" placeholder="(123) 456-7890" maxlength="14" value="<?= htmlspecialchars($person['mobile']); ?>">
                                            <div id="update-mobile-error-<?= $person['id']; ?>" class="text-danger small mt-1" style="display: none;">Invalid phone number format.</div>
                                        </div>

                                        <div class="mb-2">
                                            <input type="password" name="password" class="form-control border-0 shadow-sm rounded" placeholder="New Password (optional)">
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="admin" id="update-admin-<?= $person['id']; ?>" <?= $person['admin'] === 'Y' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="update-admin-<?= $person['id']; ?>">Enable Admin</label>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" name="update_person" class="btn btn-outline-warning">
                                                <i class="fas fa-save me-1"></i> Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Modal -->
                    <div class="modal fade" id="deletePersonModal<?= $person['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content shadow-sm border-0 rounded-3">
                                <div class="modal-header bg-danger text-white border-0">
                                    <h5 class="modal-title">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                                </div>
                                <div class="modal-body px-4 py-3">
                                    <p class="mb-3">Are you sure you want to delete <strong><?= htmlspecialchars($person['fname'] . ' ' . $person['lname']); ?></strong>?</p>
                                    <form method="POST" class="d-flex justify-content-between">
                                        <input type="hidden" name="id" value="<?= $person['id']; ?>">
                                        <button type="submit" name="delete_person" class="btn btn-outline-danger">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- Previous Page Link -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="persons_list.php?page=<?= $page - 1 ?>&sort=<?= $sort ?>&order=<?= $order ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>

                <!-- Page Number Links -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="persons_list.php?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page Link -->
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="persons_list.php?page=<?= $page + 1 ?>&sort=<?= $sort ?>&order=<?= $order ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addPersonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content shadow-sm border-0 rounded-3">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title">Add New Person</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow:none;"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-2">
                            <input type="text" name="fname" class="form-control border-0 shadow-sm rounded" placeholder="First Name" required>
                        </div>

                        <div class="mb-2">
                            <input type="text" name="lname" class="form-control border-0 shadow-sm rounded" placeholder="Last Name" required>
                        </div>

                        <div class="mb-2">
                            <input type="email" name="email" id="add-email" class="form-control mb-2" placeholder="example@svsu.edu" required>
                            <div id="add-email-error" class="text-danger small mt-1" style="display: none;">Invalid or already registered email.</div>
                        </div>

                        <div class="mb-2">
                            <input type="text" name="mobile" id="add-mobile" class="form-control border-0 shadow-sm rounded" placeholder="(123) 456-7890" maxlength="14">
                            <div id="add-mobile-error" class="text-danger small mt-1" style="display: none;">Invalid phone number format.</div>
                        </div>

                        <div class="mb-2">
                            <input type="password" name="password" id="add-password" class="form-control border-0 shadow-sm rounded" placeholder="Password" required>
                        </div>

                        <div class="mb-2">
                            <input type="password" name="confirm_password" id="add-confirm-password" class="form-control border-0 shadow-sm rounded" placeholder="Confirm Password" required>
                            <div id="add-password-error" class="text-danger small mt-1" style="display: none;">Passwords do not match.</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="admin" id="add-admin">
                            <label class="form-check-label" for="add-admin">Enable Admin</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="create_person" class="btn btn-outline-success">
                                <i class="fas fa-user-plus me-1"></i> Add Person
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.modal').forEach(modal => {
            let originalValues = {};

            modal.addEventListener('show.bs.modal', function() {
                const form = modal.querySelector('form');
                if (!form) return;

                originalValues = {};
                form.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.type !== 'submit') {
                        originalValues[input.name] = input.value;
                    }
                });
            });

            modal.addEventListener('hide.bs.modal', function(e) {
                const form = modal.querySelector('form');
                if (!form) return;

                let changed = false;
                form.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.name in originalValues && input.value !== originalValues[input.name]) {
                        changed = true;
                    }
                });

                if (changed) {
                    const confirmReset = confirm("You have unsaved changes. Discard them?");
                    if (!confirmReset) {
                        e.preventDefault();
                    } else {
                        form.querySelectorAll('input, select, textarea').forEach(input => {
                            if (input.name in originalValues) {
                                input.value = originalValues[input.name];
                            }
                        });
                    }
                }
            });

            modal.addEventListener('hidden.bs.modal', function() {
                if (Object.keys(originalValues).length > 0) {
                    location.reload();
                }
            });
        });
    </script>

    <script>
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease';
                successMessage.style.opacity = '0';
                setTimeout(() => successMessage.remove(), 500);
            }, 1000);
        }
    </script>

    <script>
        // Email validation
        const addEmailInput = document.getElementById('add-email');
        const addEmailError = document.getElementById('add-email-error');

        addEmailInput.addEventListener('blur', function() {
            const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;
            if (!emailPattern.test(addEmailInput.value)) {
                addEmailError.style.display = 'block';
            } else {
                addEmailError.style.display = 'none';
            }
        });

        addEmailInput.addEventListener('input', function() {
            const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;
            if (addEmailError.style.display === 'block' && emailPattern.test(addEmailInput.value)) {
                addEmailError.style.display = 'none'; // Hide error message when corrected
            }
        });

        // Password confirmation validation
        const addPasswordInput = document.getElementById('add-password');
        const addConfirmPasswordInput = document.getElementById('add-confirm-password');
        const addPasswordError = document.getElementById('add-password-error');

        addConfirmPasswordInput.addEventListener('blur', function() {
            if (addPasswordInput.value !== addConfirmPasswordInput.value) {
                addPasswordError.style.display = 'block';
            } else {
                addPasswordError.style.display = 'none';
            }
        });

        addConfirmPasswordInput.addEventListener('input', function() {
            if (addPasswordError.style.display === 'block' && addPasswordInput.value === addConfirmPasswordInput.value) {
                addPasswordError.style.display = 'none'; // Hide error message when corrected
            }
        });
    </script>

    <script>
        function formatPhoneNumber(inputElement, errorElementId) {
            inputElement.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove all non-numeric characters
                let formattedValue = '';

                if (value.length > 0) formattedValue += '(' + value.slice(0, 3);
                if (value.length >= 4) formattedValue += ') ' + value.slice(3, 6);
                if (value.length >= 7) formattedValue += '-' + value.slice(6, 10);

                e.target.value = formattedValue;
            });

            inputElement.addEventListener('blur', function() {
                const errorElement = document.getElementById(errorElementId);
                if (!/^\(\d{3}\) \d{3}-\d{4}$/.test(inputElement.value) && inputElement.value.length > 0) {
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }
            });
        }

        // Applying formatting and validation to Add Person modal
        const addMobileInput = document.getElementById('add-mobile');
        formatPhoneNumber(addMobileInput, 'add-mobile-error');

        // Applying formatting and validation to Update Person modal
        const updateMobileInput = document.getElementById('update-mobile');
        formatPhoneNumber(updateMobileInput, 'update-mobile-error');
    </script>

    <script>
        // Refreshing the page when the Update Person modal is closed
        const updatePersonModals = document.querySelectorAll('[id^="updatePersonModal"]');
        updatePersonModals.forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function() {
                location.reload(); // Refresh the page
            });
        });
    </script>

    <script>
        // Email validation for Update Person modals
        document.querySelectorAll('[id^="updatePersonModal"]').forEach(modal => {
            const emailInput = modal.querySelector('[id^="update-email-"]');
            const emailError = modal.querySelector('[id^="update-email-error-"]');

            emailInput.addEventListener('blur', function() {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;
                if (!emailPattern.test(emailInput.value)) {
                    emailError.style.display = 'block';
                } else {
                    emailError.style.display = 'none';
                }
            });

            emailInput.addEventListener('input', function() {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;
                if (emailError.style.display === 'block' && emailPattern.test(emailInput.value)) {
                    emailError.style.display = 'none'; // Hide error message when corrected
                }
            });
        });
    </script>

    <script>
        // Mobile number validation for Update Person modals
        document.querySelectorAll('[id^="updatePersonModal"]').forEach(modal => {
            const mobileInput = modal.querySelector('[id^="update-mobile-"]');
            const mobileError = modal.querySelector('[id^="update-mobile-error-"]');

            mobileInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove all non-numeric characters
                let formattedValue = '';

                if (value.length > 0) formattedValue += '(' + value.slice(0, 3);
                if (value.length >= 4) formattedValue += ') ' + value.slice(3, 6);
                if (value.length >= 7) formattedValue += '-' + value.slice(6, 10);

                e.target.value = formattedValue;
            });

            mobileInput.addEventListener('blur', function() {
                const mobilePattern = /^\(\d{3}\) \d{3}-\d{4}$/;
                if (!mobilePattern.test(mobileInput.value) && mobileInput.value.length > 0) {
                    mobileError.style.display = 'block';
                } else {
                    mobileError.style.display = 'none';
                }
            });
        });
    </script>

    <script>
        document.getElementById('add-email').addEventListener('blur', function() {
            const email = this.value;
            const errorDiv = document.getElementById('add-email-error');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;

            // checking the format
            if (!emailPattern.test(email)) {
                errorDiv.textContent = "Email must be in the format @svsu.edu.";
                errorDiv.style.display = "block";
                return;
            }

            // checking if it's already taken
            fetch(`check_email.php?email=${encodeURIComponent(email)}`)
                .then(response => response.text())
                .then(status => {
                    if (status === 'taken') {
                        errorDiv.textContent = "This email is already registered.";
                        errorDiv.style.display = "block";
                    } else {
                        errorDiv.style.display = "none";
                    }
                });
        });
    </script>
    <script>
        document.querySelectorAll('[id^="update-email-"]').forEach(input => {
            input.addEventListener('blur', function() {
                const email = this.value;
                const personId = this.id.split('update-email-')[1];
                const errorDiv = document.getElementById('update-email-error-' + personId);
                const saveBtn = document.querySelector(`#updatePersonModal${personId} button[name="update_person"]`);
                const emailPattern = /^[a-zA-Z0-9._%+-]+@svsu\.edu$/;

                if (!emailPattern.test(email)) {
                    errorDiv.textContent = "Email must be in the format @svsu.edu.";
                    errorDiv.style.display = "block";
                    saveBtn.disabled = true;
                    return;
                }

                fetch(`check_email.php?email=${encodeURIComponent(email)}&id=${personId}`)
                    .then(response => response.text())
                    .then(status => {
                        if (status === 'taken') {
                            errorDiv.textContent = "This email is already registered.";
                            errorDiv.style.display = "block";
                            saveBtn.disabled = true;
                        } else {
                            errorDiv.style.display = "none";
                            saveBtn.disabled = false;
                        }
                    });
            });

            // re-enabling button on input if fixed
            input.addEventListener('input', function() {
                const personId = this.id.split('update-email-')[1];
                const saveBtn = document.querySelector(`#updatePersonModal${personId} button[name="update_person"]`);
                saveBtn.disabled = false;
            });
        });
    </script>
    <script>
        document.querySelectorAll('[id^="update-mobile-"]').forEach(input => {
            input.addEventListener('blur', function() {
                const mobile = this.value.trim();
                const personId = this.id.split('update-mobile-')[1];
                const errorDiv = document.getElementById('update-mobile-error-' + personId);
                const saveBtn = document.querySelector(`#updatePersonModal${personId} button[name="update_person"]`);
                const mobilePattern = /^\(\d{3}\) \d{3}-\d{4}$/;

                if (mobile.length > 0 && !mobilePattern.test(mobile)) {
                    errorDiv.textContent = "Invalid phone number format. Use (123) 456-7890";
                    errorDiv.style.display = "block";
                    saveBtn.disabled = true;
                } else {
                    errorDiv.style.display = "none";
                    saveBtn.disabled = false;
                }
            });
        });
    </script>
</body>

</html>