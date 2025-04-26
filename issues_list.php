<?php
ob_start();
session_start();
date_default_timezone_set('America/New_York'); // local timezone

if (!isset($_SESSION['user_id'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

if (!isset($_GET['filter']) || !in_array($_GET['filter'], ['open', 'closed', 'all'])) {
    $_GET['filter'] = 'open'; // Setting the default filter to 'open'
}

$filter = $_GET['filter']; // Using the filter from the query string

require 'database\database.php';

$pdo = Database::connect();
$error_message = "";

// Fetching persons for dropdown list
$persons_sql = "SELECT id, fname, lname FROM iss_persons ORDER BY lname ASC";
$persons_stmt = $pdo->query($persons_sql);
$persons = $persons_stmt->fetchAll(PDO::FETCH_ASSOC);

$comments = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("Form submitted: " . print_r($_POST, true));
}

// Handling issue operations (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newFileName = null;

    if ($_FILES['pdf_attachment']['size'] > 0) {
        $fileTmpPath = $_FILES['pdf_attachment']['tmp_name'];
        $fileName = $_FILES['pdf_attachment']['name'];
        $fileSize = $_FILES['pdf_attachment']['size'];
        $fileType = $_FILES['pdf_attachment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileExtension !== 'pdf') {
            $error_message = "Only PDF files are allowed.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error_message = "File size exceeds the 2MB limit.";
        } else {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = './uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                die("Error moving file");
            }
        }
    }

    if (isset($_POST['create_issue'])) {
        $short_description = htmlspecialchars(trim($_POST['short_description']));
        $long_description = trim($_POST['long_description']);
        $open_date = $_POST['open_date'];
        $close_date = $_POST['close_date'];
        $priority = $_POST['priority'];
        $org = trim($_POST['org']);
        $project = trim($_POST['project']);
        $per_id = $_POST['per_id'];
        $created_by = $_SESSION['user_id']; // Logged-in user ID

        $sql = "INSERT INTO iss_issues (short_description, long_description, open_date, close_date, priority, org, project, per_id, created_by, pdf_attachment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$short_description, $long_description, $open_date, $close_date, $priority, $org, $project, $per_id, $created_by, $newFileName]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            die("Database error: " . $e->getMessage());
        }

        // Calculating the page where the new issue will appear
        $count_sql = "SELECT COUNT(*) FROM iss_issues";
        $total_issues = $pdo->query($count_sql)->fetchColumn();
        $issues_per_page = 10; // Same as $limit
        $new_issue_page = ceil($total_issues / $issues_per_page);

        // Redirecting to the page where the new issue is saved
        header("Location: issues_list.php?page=$new_issue_page&filter=" . urlencode($filter) . "&sort=" . urlencode($sort) . "&order=" . urlencode($order));
        exit();
    }

    if (isset($_POST['update_issue'])) {
        if (!($_SESSION['admin'] == "Y" || $_SESSION['user_id'] == $_POST['per_id'] || $_SESSION['user_id'] == $_POST['created_by'])) {
            header("Location: issues_list.php");
            exit();
        }


        $id = $_POST['id'];
        $short_description = trim($_POST['short_description']);
        $long_description = trim($_POST['long_description']);
        $open_date = $_POST['open_date'];
        $close_date = $_POST['close_date'];
        $priority = $_POST['priority'];
        $org = trim($_POST['org']);
        $project = trim($_POST['project']);
        $per_id = !empty($_POST['per_id']) ? $_POST['per_id'] : null;

        $sql = "UPDATE iss_issues 
        SET short_description=?, long_description=?, open_date=?, close_date=?, priority=?, org=?, project=?, per_id=? 
        WHERE id=?";

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                $short_description,
                $long_description,
                $open_date,
                $close_date,
                $priority,
                $org,
                $project,
                $per_id,
                $id
            ]);
            error_log("Issue update success. Rows affected: " . $stmt->rowCount());
        } catch (PDOException $e) {
            die("Database error during update: " . $e->getMessage());
        }

        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
        $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
        $current_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
        $issue_id = $id;

        header("Location: issues_list.php?page=$current_page&filter=$current_filter&sort=$current_sort&order=$current_order&open_issue_id=$issue_id");
        exit();
    }


    if (isset($_POST['delete_issue'])) {
        // Checking if the user is authorized to delete the issue
        $id = $_POST['id'];
        $per_id = $_POST['per_id'];

        // Ensuring the user is either an admin or the creator/person responsible for the issue
        $sql = "SELECT created_by, per_id FROM iss_issues WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$issue || (!($_SESSION['admin'] === "Y" || $_SESSION['user_id'] == $issue['created_by'] || $_SESSION['user_id'] == $issue['per_id']))) {
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
            $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
            $current_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

            // Getting the current number of issues to validate if the page is now empty after deletion
            $count_sql = "SELECT COUNT(*) FROM iss_issues";
            if ($current_filter === 'open') {
                $count_sql .= " WHERE (close_date IS NULL OR close_date > NOW())";
            } elseif ($current_filter === 'closed') {
                $count_sql .= " WHERE close_date != '0000-00-00' AND close_date < NOW()";
            }
            $total_issues = $pdo->query($count_sql)->fetchColumn();
            $issues_per_page = 10;
            $max_page = max(1, ceil($total_issues / $issues_per_page));

            // If current page is now empty, step back a page
            if ($current_page > $max_page) {
                $current_page = $max_page;
            }

            header("Location: issues_list.php?page=$current_page&filter=$current_filter&sort=$current_sort&order=$current_order");
            exit();
        }

        try {
            // Deleting the issue from the database
            $sql = "DELETE FROM iss_issues WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // Redirecting back to the issues list
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
            $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
            $current_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

            // Getting the current number of issues to validate if the page is now empty after deletion
            $count_sql = "SELECT COUNT(*) FROM iss_issues";
            if ($current_filter === 'open') {
                $count_sql .= " WHERE (close_date IS NULL OR close_date > NOW())";
            } elseif ($current_filter === 'closed') {
                $count_sql .= " WHERE close_date != '0000-00-00' AND close_date < NOW()";
            }
            $total_issues = $pdo->query($count_sql)->fetchColumn();
            $issues_per_page = 10;
            $max_page = max(1, ceil($total_issues / $issues_per_page));

            // If current page is now empty, step back a page
            if ($current_page > $max_page) {
                $current_page = $max_page;
            }

            header("Location: issues_list.php?page=$current_page&filter=$current_filter&sort=$current_sort&order=$current_order");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }

    if (isset($_POST['create_comment'])) {
        $iss_id = $_POST['iss_id'];
        $short_comment = htmlspecialchars(trim($_POST['short_comment']));
        $per_id = $_SESSION['user_id'];
        $posted_date = date('Y-m-d H:i:s', time());

        // Inserting comment into the database
        $sql = "INSERT INTO iss_comments (iss_id, per_id, short_comment, posted_date) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$iss_id, $per_id, $short_comment, $posted_date]);

            // Counting total comments to find the last page number
            $count_sql = "SELECT COUNT(*) FROM iss_comments WHERE iss_id = ?";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute([$iss_id]);
            $total_comments = $count_stmt->fetchColumn();
            $comments_per_page = 5;
            $last_comment_page = ceil($total_comments / $comments_per_page);

            // Redirecting to the last comment page and open the modal
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
            $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
            $current_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

            header("Location: issues_list.php?page=$current_page&filter=$current_filter&sort=$current_sort&order=$current_order&open_issue_id=$iss_id&comment_page=1");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }


    if (isset($_POST['update_comment'])) {
        $comment_id = $_POST['comment_id'];
        $short_comment = htmlspecialchars(trim($_POST['short_comment']));

        // Updating the comment in the database
        // Permission check before updating
        $comment_check_sql = "SELECT per_id, iss_id FROM iss_comments WHERE id = ?";
        $comment_check_stmt = $pdo->prepare($comment_check_sql);
        $comment_check_stmt->execute([$comment_id]);
        $comment_info = $comment_check_stmt->fetch(PDO::FETCH_ASSOC);

        $issue_sql = "SELECT created_by, per_id FROM iss_issues WHERE id = ?";
        $issue_stmt = $pdo->prepare($issue_sql);
        $issue_stmt->execute([$comment_info['iss_id']]);
        $issue_info = $issue_stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $_SESSION['admin'] !== "Y" &&
            $_SESSION['user_id'] != $comment_info['per_id'] &&
            $_SESSION['user_id'] != $issue_info['created_by'] &&
            $_SESSION['user_id'] != $issue_info['per_id']
        ) {
            die("You do not have permission to update this comment.");
        }

        // Proceeding to update
        $sql = "UPDATE iss_comments SET short_comment = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$short_comment, $comment_id]);
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
        // Fetching the issue ID associated with the comment
        $issue_sql = "SELECT iss_id FROM iss_comments WHERE id = ?";
        $issue_stmt = $pdo->prepare($issue_sql);
        $issue_stmt->execute([$comment_id]);
        $issue_id = $issue_stmt->fetchColumn();

        // Redirecting with the open_issue_id parameter
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
        $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
        $current_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
        $current_comment_page = isset($_GET['comment_page']) ? (int)$_GET['comment_page'] : 1;

        header("Location: issues_list.php?page=$current_page&filter=$current_filter&sort=$current_sort&order=$current_order&open_issue_id=$issue_id&comment_page=$current_comment_page");
        exit();
    }

    if (isset($_POST['delete_comment']) && isset($_POST['comment_id'])) {
        ob_clean(); // clearing previous output
        header('Content-Type: application/json');

        $comment_id = $_POST['comment_id'];

        // Validating comment and permissions
        $check_sql = "SELECT per_id, iss_id FROM iss_comments WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$comment_id]);
        $comment_info = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment_info) {
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit();
        }

        $issue_sql = "SELECT created_by, per_id FROM iss_issues WHERE id = ?";
        $issue_stmt = $pdo->prepare($issue_sql);
        $issue_stmt->execute([$comment_info['iss_id']]);
        $issue_info = $issue_stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $_SESSION['admin'] !== "Y" &&
            $_SESSION['user_id'] != $comment_info['per_id'] &&
            $_SESSION['user_id'] != $issue_info['created_by'] &&
            $_SESSION['user_id'] != $issue_info['per_id']
        ) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit();
        }

        // Deleting the comment from the database
        $delete_stmt = $pdo->prepare("DELETE FROM iss_comments WHERE id = ?");
        try {
            $delete_stmt->execute([$comment_id]);

            // calculating updated pagination
            $iss_id = $comment_info['iss_id'];
            $count_sql = "SELECT COUNT(*) FROM iss_comments WHERE iss_id = ?";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute([$iss_id]);
            $total_comments = $count_stmt->fetchColumn();
            $comments_per_page = 5;
            $new_total_pages = max(1, ceil($total_comments / $comments_per_page));

            echo json_encode([
                'success' => true,
                'open_issue_id' => $iss_id,
                'comment_page' => $new_total_pages
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
        }

        exit();
    }
}
// Updating issues with invalid person IDs
$sql = "UPDATE iss_issues 
        SET per_id = NULL 
        WHERE per_id NOT IN (SELECT id FROM iss_persons)";
$pdo->exec($sql);

// all issues filter
// Setting the default filter to 'open' if not explicitly provided
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
error_log("Filter value: " . $filter); // Logs the filter value to the PHP error log
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Modifying the ORDER BY clause for sorting priority
$allowedSorts = ['id', 'short_description', 'open_date', 'close_date', 'priority', 'responsible'];
$sort = in_array($sort, $allowedSorts) ? $sort : 'open_date';
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

if ($sort === 'priority') {
    if ($order === 'ASC') {
        $order_by_clause = "FIELD(i.priority, 'Low', 'Medium', 'High')";
    } else {
        $order_by_clause = "FIELD(i.priority, 'High', 'Medium', 'Low')";
    }
} elseif ($sort === 'responsible') {
    $order_by_clause = "responsible.lname $order, responsible.fname $order";
} else {
    $order_by_clause = "i.$sort $order";
}


$limit = 10; // Number of issues per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensuring page is at least 1

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetching total count for pagination
if ($filter === 'open') {
    $count_sql = "SELECT COUNT(*) FROM iss_issues WHERE (close_date IS NULL OR close_date > NOW())";
} elseif ($filter === 'closed') {
    $count_sql = "SELECT COUNT(*) FROM iss_issues 
                  WHERE close_date < NOW() 
                  AND close_date != '0000-00-00'";
} else {
    $count_sql = "SELECT COUNT(*) FROM iss_issues";
}

// Debugging the count SQL query
error_log("Count SQL: " . $count_sql);

$total_issues = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_issues / $limit);

// Ensuring the current page is within valid range
$page = min($page, $total_pages); // Ensuring page does not exceed total pages

// Calculating offset for SQL query
$offset = ($page - 1) * $limit;
$offset = max(0, $offset); // Ensuring offset is non-negative

error_log("Total Issues: " . $total_issues);
error_log("Total Pages: " . $total_pages);
error_log("Current Page: " . $page);
error_log("Offset: " . $offset);
error_log("Page: $page, Offset: $offset, Limit: $limit, Total Pages: $total_pages");
error_log("Page: $page, Offset: $offset, Limit: $limit, Total Pages: $total_pages");

// Adjusting the SQL query based on the filter
$sql = "SELECT i.*, 
               creator.fname AS creator_fname, creator.lname AS creator_lname,
               responsible.fname AS responsible_fname, responsible.lname AS responsible_lname
        FROM iss_issues i
        LEFT JOIN iss_persons creator ON i.created_by = creator.id
        LEFT JOIN iss_persons responsible ON i.per_id = responsible.id
        WHERE (i.short_description LIKE :search OR i.long_description LIKE :search)";

if ($filter === 'open') {
    $sql .= " AND (i.close_date IS NULL OR i.close_date = '0000-00-00' OR i.close_date > NOW())";
} elseif ($filter === 'closed') {
    $sql .= " AND i.close_date != '0000-00-00' AND i.close_date < NOW()";
}

if (!empty($_GET['person_filter'])) {
    $sql .= " AND i.per_id = :person_filter";
}

$sql .= " ORDER BY $order_by_clause LIMIT :limit OFFSET :offset";


$stmt = $pdo->prepare($sql);
$params = [
    'search' => "%$search%",
    'limit' => $limit,
    'offset' => $offset,
];

if (!empty($_GET['person_filter'])) {
    $params['person_filter'] = $_GET['person_filter'];
}

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', $params['search'], PDO::PARAM_STR);
$stmt->bindValue(':limit', $params['limit'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $params['offset'], PDO::PARAM_INT);

if (isset($params['person_filter'])) {
    $stmt->bindValue(':person_filter', $params['person_filter'], PDO::PARAM_INT);
}

$stmt->execute();
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debugging the SQL query
error_log("SQL Query: $sql");
error_log("Parameters: " . print_r($params, true));
error_log("Issues: " . print_r($issues, true));

?>

<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issues List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-3">
        <?php if (isset($_GET['comment_deleted']) && $_GET['comment_deleted'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Comment deleted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <h2 class="text-center">Issues List</h2>

        <!-- "+" Button to Add Issue -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- Filter Dropdown -->
            <form method="GET" class="d-flex align-items-center">
                <label for="filter" class="me-2">Filter:</label>
                <select name="filter" id="filter" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : ''; ?>>All Issues</option>
                    <option value="open" <?= $filter === 'open' ? 'selected' : ''; ?>>Open Issues</option>
                    <option value="closed" <?= $filter === 'closed' ? 'selected' : ''; ?>>Closed Issues</option>
                </select>

                <label for="person_filter" class="me-2">Person:</label>
                <select name="person_filter" id="person_filter" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    <option value="">-- All Persons --</option>
                    <?php foreach ($persons as $person): ?>
                        <option value="<?= $person['id']; ?>" <?= isset($_GET['person_filter']) && $_GET['person_filter'] == $person['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($person['lname'] . ', ' . $person['fname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Buttons Section -->
            <div>
                <!-- Add Issue Button -->
                <button class="btn btn-outline-success me-2 custom-btn" data-bs-toggle="modal" data-bs-target="#addIssueModal" title="Add Issue">
                    <i class="fas fa-plus"></i>
                </button>

                <!-- Manage Persons Button -->
                <?php if ($_SESSION['admin'] === "Y"): ?>
                    <a href="persons_list.php" class="btn btn-outline-primary me-2 custom-btn" title="Manage Persons">
                        <i class="fas fa-users"></i>
                    </a>
                <?php endif; ?>

                <!-- Logout Button -->
                <a href="logout.php" class="btn btn-outline-danger custom-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>


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

            textarea.form-control:focus {
                box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
            }

            .card ul.list-group {
                border-radius: 0;
            }

            .card ul.list-group li {
                border: none;
                border-bottom: 1px solid #dee2e6;
            }

            .btn-outline-success i {
                transition: transform 0.2s ease;
            }

            .btn-outline-success:hover i {
                transform: translateX(2px);
            }
        </style>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="table-responsive rounded shadow-sm bg-white p-3">
            <table class="table table-hover align-middle text-center">
                <thead class="table-dark text-center align-middle">
                    <tr>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=id&order=<?= ($sort === 'id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                ID <?= $sort === 'id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=short_description&order=<?= ($sort === 'short_description' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                Short Description <?= $sort === 'short_description' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=responsible&order=<?= ($sort === 'responsible' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                Responsible <?= $sort === 'responsible' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=open_date&order=<?= ($sort === 'open_date' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                Open Date <?= $sort === 'open_date' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=close_date&order=<?= ($sort === 'close_date' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                Close Date <?= $sort === 'close_date' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="issues_list.php?filter=<?= $filter ?>&sort=priority&order=<?= ($sort === 'priority' && $order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">
                                Priority <?= $sort === 'priority' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-white">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue): ?>
                        <?php
                        error_log("Fetching comments for issue ID: " . $issue['id']);
                        // Fetching paginated comments for the issue
                        $comments_per_page = 5;
                        $comment_page = isset($_GET['comment_page']) && $_GET['open_issue_id'] == $issue['id'] ? (int)$_GET['comment_page'] : 1;
                        $comment_page = max(1, $comment_page);
                        $comment_offset = ($comment_page - 1) * $comments_per_page;

                        // Getting total comments for pagination
                        $count_sql = "SELECT COUNT(*) FROM iss_comments WHERE iss_id = ?";
                        $count_stmt = $pdo->prepare($count_sql);
                        $count_stmt->execute([$issue['id']]);
                        $total_comments = $count_stmt->fetchColumn();
                        $total_comment_pages = ceil($total_comments / $comments_per_page);

                        // fetching only current page comments
                        $comments_sql = "SELECT c.*, p.fname, p.lname 
                 FROM iss_comments c
                 JOIN iss_persons p ON c.per_id = p.id
                 WHERE c.iss_id = :issue_id
                 ORDER BY c.posted_date DESC
                 LIMIT :limit OFFSET :offset";

                        $comments_stmt = $pdo->prepare($comments_sql);
                        $comments_stmt->bindValue(':issue_id', $issue['id'], PDO::PARAM_INT);
                        $comments_stmt->bindValue(':limit', $comments_per_page, PDO::PARAM_INT);
                        $comments_stmt->bindValue(':offset', $comment_offset, PDO::PARAM_INT);
                        $comments_stmt->execute();
                        $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
                        error_log("Comments fetched: " . print_r($comments, true));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($issue['id']); ?></td>
                            <td><?= htmlspecialchars($issue['short_description']); ?></td>
                            <td>
                                <?php
                                if (!empty($issue['responsible_fname']) && !empty($issue['responsible_lname'])) {
                                    echo htmlspecialchars($issue['responsible_lname'] . ', ' . $issue['responsible_fname']);
                                } else {
                                    echo "-";
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($issue['open_date']); ?></td>
                            <td><?= htmlspecialchars($issue['close_date']); ?></td>

                            <?php
                            $priority = $issue['priority'];
                            switch ($priority) {
                                case 'High':
                                    $badgeClass = 'bg-danger';
                                    $dotColor = 'bg-danger';
                                    break;
                                case 'Medium':
                                    $badgeClass = 'bg-warning text-dark';
                                    $dotColor = 'bg-warning';
                                    break;
                                case 'Low':
                                    $badgeClass = 'bg-success';
                                    $dotColor = 'bg-success';
                                    break;
                                default:
                                    $badgeClass = 'bg-secondary';
                                    $dotColor = 'bg-secondary';
                                    break;
                            }
                            ?>

                            <td class="text-start">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="dot-indicator <?= $dotColor ?>"></span>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($priority); ?></span> <!-- This line was missing -->
                                </span>
                            </td>

                            <td>
                                <!-- Read Button -->
                                <button class="btn btn-outline-info btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#readIssue<?= $issue['id']; ?>" title="Read" aria-label="Read Issue <?= $issue['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($_SESSION['user_id'] == $issue['per_id'] || $_SESSION['user_id'] == $issue['created_by'] || $_SESSION['admin'] == "Y") { ?>
                                    <!-- Update Button -->
                                    <button class="btn btn-outline-warning btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#updateIssue<?= $issue['id']; ?>" title="Update">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- Delete Button -->
                                    <button class="btn btn-outline-danger btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#deleteIssue<?= $issue['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>

                        <!-- Create Issue Modal -->
                        <div class="modal fade" id="addIssueModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content shadow-sm border-0 rounded-3">
                                    <div class="modal-header bg-success text-white border-0">
                                        <h5 class="modal-title">Add New Issue</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-2">
                                                <input type="text" name="short_description" class="form-control border-0 shadow-sm rounded" placeholder="Short Description" required>
                                            </div>

                                            <div class="mb-2">
                                                <textarea name="long_description" class="form-control border-0 shadow-sm rounded" placeholder="Long Description" rows="3"></textarea>
                                            </div>

                                            <div class="mb-2">
                                                <input type="date" name="open_date" class="form-control border-0 shadow-sm rounded" value="<?= date('Y-m-d'); ?>" required>
                                            </div>

                                            <div class="mb-2">
                                                <input type="date" name="close_date" class="form-control border-0 shadow-sm rounded">
                                            </div>

                                            <div class="mb-2">
                                                <select name="priority" class="form-select border-0 shadow-sm rounded" required>
                                                    <option value="">-- Select Priority --</option>
                                                    <option value="High">High</option>
                                                    <option value="Medium">Medium</option>
                                                    <option value="Low">Low</option>
                                                </select>
                                            </div>

                                            <div class="mb-2">
                                                <input type="text" name="org" class="form-control border-0 shadow-sm rounded" placeholder="Organization">
                                            </div>

                                            <div class="mb-2">
                                                <input type="text" name="project" class="form-control border-0 shadow-sm rounded" placeholder="Project">
                                            </div>

                                            <div class="mb-2">
                                                <select name="per_id" class="form-select border-0 shadow-sm rounded">
                                                    <option value="">-- Select Person --</option>
                                                    <?php foreach ($persons as $person): ?>
                                                        <option value="<?= $person['id']; ?>">
                                                            <?= htmlspecialchars($person['lname'] . ', ' . $person['fname']) . ' (' . $person['id'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <input type="file" name="pdf_attachment" class="form-control border-0 shadow-sm rounded" accept="application/pdf">
                                            </div>

                                            <div class="d-grid">
                                                <button type="submit" name="create_issue" class="btn btn-outline-success">
                                                    <i class="fas fa-plus me-1"></i> Add Issue
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Read Modal -->
                        <div class="modal fade" id="readIssue<?= $issue['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content shadow-sm border-0 rounded-3">
                                    <div class="modal-header bg-info text-white border-0">
                                        <h5 class="modal-title">Issue Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                                    </div>
                                    <div class="modal-body px-4 py-3">
                                        <div class="mb-2"><strong>ID:</strong> <?= htmlspecialchars($issue['id']); ?></div>
                                        <div class="mb-2"><strong>Short Description:</strong> <?= htmlspecialchars($issue['short_description']); ?></div>
                                        <div class="mb-2"><strong>Long Description:</strong> <?= htmlspecialchars($issue['long_description']); ?></div>
                                        <div class="mb-2"><strong>Open Date:</strong> <?= htmlspecialchars($issue['open_date']); ?></div>
                                        <div class="mb-2"><strong>Close Date:</strong> <?= htmlspecialchars($issue['close_date']); ?></div>
                                        <div class="mb-2"><strong>Priority:</strong>
                                            <span class="badge <?= $issue['priority'] === 'High' ? 'bg-danger' : ($issue['priority'] === 'Medium' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                                <?= htmlspecialchars($issue['priority']); ?>
                                            </span>
                                        </div>
                                        <div class="mb-2"><strong>Organization:</strong> <?= htmlspecialchars($issue['org']); ?></div>
                                        <div class="mb-2"><strong>Project:</strong> <?= htmlspecialchars($issue['project']); ?></div>
                                        <div class="mb-3"><strong>Person:</strong>
                                            <?php
                                            if (!empty($issue['responsible_lname']) && !empty($issue['responsible_fname'])) {
                                                echo htmlspecialchars($issue['responsible_lname'] . ', ' . $issue['responsible_fname']);
                                            } else {
                                                echo "-";
                                            }
                                            ?>
                                        </div>

                                        <!-- Comments Section -->
                                        <h5 class="mb-3 mt-4">Comments</h5>
                                        <div class="card border-0 shadow-sm mb-3" style="max-height: 300px; overflow-y: auto;">
                                            <ul class="list-group list-group-flush">
                                                <?php if (!empty($comments)): ?>
                                                    <?php foreach ($comments as $comment): ?>
                                                        <li class="list-group-item">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <strong class="<?= $_SESSION['user_id'] == $comment['per_id'] ? 'text-success' : ''; ?>">
                                                                        <?= htmlspecialchars($comment['lname'] . ', ' . $comment['fname']); ?>
                                                                    </strong>
                                                                    <span><?= nl2br(htmlspecialchars($comment['short_comment'])); ?></span>
                                                                </div>
                                                                <div class="text-muted small text-end" style="white-space: nowrap;">
                                                                    <span class="comment-time" data-time="<?= htmlspecialchars($comment['posted_date']); ?>">
                                                                        <?= date('M j, Y g:i A', strtotime($comment['posted_date'])); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 d-flex gap-2">
                                                                <!-- Read -->
                                                                <button class="btn btn-outline-info btn-sm"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#readCommentModal"
                                                                    data-comment-id="<?= $comment['id']; ?>"
                                                                    data-commenter="<?= htmlspecialchars($comment['lname'] . ', ' . $comment['fname']); ?>"
                                                                    data-text="<?= htmlspecialchars($comment['short_comment']); ?>"
                                                                    data-posted="<?= date('M j, Y g:i A', strtotime($comment['posted_date'])); ?>"
                                                                    title="Read Comment">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <?php if ($_SESSION['user_id'] == $comment['per_id'] || $_SESSION['admin'] == "Y"): ?>
                                                                    <!-- Edit -->
                                                                    <button class="btn btn-outline-warning btn-sm"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#editCommentModal"
                                                                        data-comment-id="<?= $comment['id']; ?>"
                                                                        data-text="<?= htmlspecialchars($comment['short_comment']); ?>"
                                                                        title="Edit Comment">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button> <button class="btn btn-outline-danger btn-sm delete-comment-btn" data-comment-id="<?= $comment['id']; ?>" title="Delete Comment">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>

                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <li class="list-group-item text-muted text-center">No comments yet.</li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>

                                        <!-- Add Comment Form -->
                                        <form method="POST" class="bg-light p-3 rounded shadow-sm">
                                            <input type="hidden" name="iss_id" value="<?= $issue['id']; ?>">
                                            <div class="position-relative">
                                                <textarea name="short_comment"
                                                    class="form-control border-0 shadow-sm rounded pe-5"
                                                    placeholder="Write a comment..."
                                                    rows="1"
                                                    style="resize: none; outline: none;"
                                                    required></textarea>
                                                <button type="submit"
                                                    name="create_comment"
                                                    class="btn btn-outline-success position-absolute top-50 end-0 translate-middle-y me-2"
                                                    style="z-index: 5;"
                                                    title="Send">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>


                                            <!-- Pagination Controls -->
                                            <nav aria-label="Comment Pagination">
                                                <ul class="pagination justify-content-center">
                                                    <!-- Previous Page Link -->
                                                    <li class="page-item <?= $comment_page <= 1 ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="issues_list.php?page=<?= $page ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>&open_issue_id=<?= $issue['id']; ?>&comment_page=<?= $comment_page - 1 ?>" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>

                                                    <!-- Page Number Links -->
                                                    <?php for ($i = 1; $i <= $total_comment_pages; $i++): ?>
                                                        <li class="page-item <?= $i == $comment_page ? 'active' : '' ?>">
                                                            <a class="page-link" href="issues_list.php?page=<?= $page ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>&open_issue_id=<?= $issue['id']; ?>&comment_page=<?= $i ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <!-- Next Page Link -->
                                                    <li class="page-item <?= $comment_page >= $total_comment_pages ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="issues_list.php?page=<?= $page ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>&open_issue_id=<?= $issue['id']; ?>&comment_page=<?= $comment_page + 1 ?>" aria-label="Next">

                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $comments_count_sql = "SELECT COUNT(*) FROM iss_comments WHERE iss_id = ?";
                        $comments_count_stmt = $pdo->prepare($comments_count_sql);
                        $comments_count_stmt->execute([$issue['id']]);
                        $total_comments_for_issue = $comments_count_stmt->fetchColumn();
                        $comments_per_page = 5;
                        $total_comment_pages = ceil($total_comments_for_issue / $comments_per_page);

                        // Determining current comment page from query string, default to 1
                        $comment_page = isset($_GET['comment_page']) ? (int)$_GET['comment_page'] : 1;
                        $comment_page = max(1, min($total_comment_pages, $comment_page));
                        ?>
        </div>
    </div>
    </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateIssue<?= $issue['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content shadow-sm border-0 rounded-3">
                <div class="modal-header bg-warning text-dark border-0">
                    <h5 class="modal-title">Update Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $issue['id']; ?>">
                        <input type="hidden" name="created_by" value="<?= $issue['created_by']; ?>">

                        <div class="mb-2">
                            <input type="text" name="short_description" class="form-control border-0 shadow-sm rounded" value="<?= htmlspecialchars($issue['short_description']); ?>" required placeholder="Short Description">
                        </div>

                        <div class="mb-2">
                            <textarea name="long_description" class="form-control border-0 shadow-sm rounded" rows="3" placeholder="Long Description"><?= htmlspecialchars($issue['long_description']); ?></textarea>
                        </div>

                        <div class="mb-2">
                            <input type="date" name="open_date" class="form-control border-0 shadow-sm rounded" value="<?= $issue['open_date']; ?>" readonly>
                        </div>

                        <div class="mb-2">
                            <input type="date" name="close_date" class="form-control border-0 shadow-sm rounded" value="<?= $issue['close_date']; ?>">
                        </div>

                        <div class="mb-2">
                            <select name="priority" class="form-select border-0 shadow-sm rounded" required>
                                <option value="High" <?= $issue['priority'] === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Medium" <?= $issue['priority'] === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="Low" <?= $issue['priority'] === 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <input type="text" name="org" class="form-control border-0 shadow-sm rounded" value="<?= htmlspecialchars($issue['org']); ?>" placeholder="Organization">
                        </div>

                        <div class="mb-2">
                            <input type="text" name="project" class="form-control border-0 shadow-sm rounded" value="<?= htmlspecialchars($issue['project']); ?>" placeholder="Project">
                        </div>

                        <div class="mb-3">
                        <select name="per_id" class="form-select border-0 shadow-sm rounded"
    <?= (!($_SESSION['admin'] === "Y" || $_SESSION['user_id'] == $issue['created_by'])) ? 'disabled' : '' ?>>
                                <option value="">-- Select Person --</option>
                                <?php foreach ($persons as $person): ?>
                                    <option value="<?= $person['id']; ?>" <?= $issue['per_id'] == $person['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($person['lname'] . ', ' . $person['fname']); ?>
                                    </option>
                                    <?php if ($_SESSION['user_id'] != $issue['created_by']): ?>
                                        <div class="text-muted small mt-1">Only the issue creator can update the person responsible.</div>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="text-end">
                            <button type="submit" name="update_issue" class="btn btn-outline-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Delete Issue Modal -->
    <div class="modal fade" id="deleteIssue<?= $issue['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content shadow-sm border-0 rounded-3">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title">Delete Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this issue?</p>
                    <p><strong>ID:</strong> <?= htmlspecialchars($issue['id']); ?></p>
                    <p><strong>Short Description:</strong> <?= htmlspecialchars($issue['short_description']); ?></p>

                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $issue['id']; ?>">
                        <input type="hidden" name="per_id" value="<?= $issue['per_id']; ?>">

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="submit" name="delete_issue" class="btn btn-outline-danger">Delete</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


<?php endforeach; ?>
<?php if (empty($issues)): ?>
    <tr>
        <td colspan="6" class="text-center">No issues found for this page.</td>
    </tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="issues_list.php?page=<?= max(1, $page - 1) ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="issues_list.php?page=<?= $i ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="issues_list.php?page=<?= min($total_pages, $page + 1) ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const openIssueId = urlParams.get('open_issue_id');

        if (openIssueId) {
            const issueModal = new bootstrap.Modal(document.getElementById(`readIssue${openIssueId}`));
            issueModal.show();

            // Removing open_issue_id and comment_page from the URL
            const newUrl = window.location.pathname + window.location.search.replace(/([&?](open_issue_id|comment_page)=[^&]*)/g, '');
            window.history.replaceState({}, document.title, newUrl);
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const commentTimes = document.querySelectorAll('.comment-time');

        commentTimes.forEach(el => {
            const time = el.getAttribute('data-time');
            const friendlyTime = dayjs(time).fromNow();
            el.textContent = friendlyTime;
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-comment-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');

                if (confirm('Are you sure you want to delete this comment?')) {
                    fetch('issues_list.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                delete_comment: true,
                                comment_id: commentId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const currentUrl = new URL(window.location.href);
                                currentUrl.searchParams.set('open_issue_id', data.open_issue_id);
                                currentUrl.searchParams.set('comment_page', data.comment_page);
                                currentUrl.searchParams.set('comment_deleted', 1);
                                window.location.href = currentUrl.toString();


                                window.location.href = currentUrl.toString();
                            } else {
                                alert(data.message || 'Failed to delete the comment.');
                            }
                        })
                        .catch(error => {
                            console.error('Error during fetch:', error);
                            alert('An error occurred while deleting the comment.');
                        });
                }
            });
        });
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-bs-target');
                const modalEl = document.querySelector(targetId);
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('JavaScript loaded');
        document.querySelectorAll('.custom-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                console.log('Button clicked:', this);
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
        const textareas = document.querySelectorAll('textarea[name="short_comment"]');
        textareas.forEach(textarea => {
            textarea.setAttribute('style', 'height:auto;overflow-y:hidden;');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/relativeTime.js"></script>
<script>
    dayjs.extend(dayjs_plugin_relativeTime);
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('.alert-success.alert-dismissible');

        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease';
                successMessage.style.opacity = '0';

                setTimeout(() => {
                    if (successMessage && successMessage.parentNode) {
                        successMessage.remove();
                    }
                }, 500); // waiting for fade to complete
            }, 1000); // initial delay
        }
    });
</script>
<script>
    document.addEventListener('hidden.bs.modal', function() {
        // Only removing backdrop/body state if no modals are open
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style = '';
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Populating Read Comment Modal
        const readModal = document.getElementById('readCommentModal');
        readModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-comment-id');
            const commenter = button.getAttribute('data-commenter');
            const text = button.getAttribute('data-text');
            const posted = button.getAttribute('data-posted');

            const content = `
      <p><strong>Comment ID:</strong> ${id}</p>
      <p><strong>Commenter:</strong> ${commenter}</p>
      <p><strong>Comment:</strong> ${text}</p>
      <p><strong>Posted:</strong> ${posted}</p>
    `;
            document.getElementById('readCommentContent').innerHTML = content;
            document.addEventListener('DOMContentLoaded', function() {
                // Manually showing read modal with backdrop inside parent modal
                document.querySelectorAll('[data-bs-target="#readCommentModal"]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const modal = new bootstrap.Modal(document.getElementById('readCommentModal'), {
                            backdrop: 'static',
                            keyboard: true,
                            focus: true
                        });
                        modal.show();
                    });
                });

                // Intercepting Edit Comment Button Clicks
                document.querySelectorAll('[data-bs-target="#editCommentModal"]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const modal = new bootstrap.Modal(document.getElementById('editCommentModal'), {
                            backdrop: 'static',
                            keyboard: true,
                            focus: true
                        });
                        modal.show();
                    });
                });
            });

        });

        // Populating Edit Comment Modal
        const editModal = document.getElementById('editCommentModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-comment-id');
            const text = button.getAttribute('data-text');

            document.getElementById('editCommentId').value = id;
            document.getElementById('editCommentText').value = text;
        });
    });
</script>

</body>

</html>
<!-- Global Read Comment Modal -->
<div class="modal fade" id="readCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow-sm border-0 rounded-3">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Comment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="readCommentContent">
            </div>
        </div>
    </div>
</div>

<!-- Global Edit Comment Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow-sm border-0 rounded-3">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Edit Comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="comment_id" id="editCommentId">
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea name="short_comment" id="editCommentText" class="form-control border-0 shadow-sm rounded" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_comment" class="btn btn-outline-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php Database::disconnect();
ob_end_flush(); ?>