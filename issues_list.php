<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    session_destroy();
    header("Location: login.php");
    exit(); 
}
require 'database.php'; // Corrected path

$pdo = Database::connect();
$error_message = "";

// Fetch persons for dropdown list
$persons_sql = "SELECT id, fname, lname FROM iss_persons ORDER BY lname ASC";
$persons_stmt = $pdo->query($persons_sql);
$persons = $persons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle issue operations (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // echo "this is a test of file uploads" ; print_r($_FILES); exit(); // checkpoint
    if($_FILES['pdf_attachment']['size'] > 0) {

        $fileTmpPath = $_FILES['pdf_attachment']['tmp_name'];
        $fileName    = $_FILES['pdf_attachment']['name'];
        $fileSize    = $_FILES['pdf_attachment']['size'];
        $fileType    = $_FILES['pdf_attachment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileExtension !== 'pdf') {
            $error_message = "Only PDF files are allowed.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error_message = "File size exceeds the 2MB limit.";
        }

        $newFileName = MD5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = './uploads/';
        $dest_path = $uploadFileDir . $newFileName;
        // if uploads directory does not exist, create it
        if(!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $attachmentPath = $dest_path;
        } else {
            die("error moving file");
        }

    } // end pdf attachment
    
    if (isset($_POST['create_issue'])) {
        $short_description = htmlspecialchars(trim($_POST['short_description']));
        $long_description = trim($_POST['long_description']);
        $open_date = $_POST['open_date'];
        $close_date = $_POST['close_date'];
        $priority = $_POST['priority'];
        $org = trim($_POST['org']);
        $project = trim($_POST['project']);
        $per_id = $_POST['per_id'];
        // $newFileName is PDF attachment
        // $attachmentPath is the entire path

        $sql = "INSERT INTO iss_issues (short_description, long_description, 
            open_date, close_date, priority, org, project, per_id, 
            pdf_attachment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$short_description, $long_description, 
                $open_date, $close_date, $priority, $org, $project, $per_id,
                $newFileName]);
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

        header("Location: issues_list.php");
        exit();
    }

    if (isset($_POST['update_issue'])) {
        if( !( $_SESSION['admin'] == "Y" || $_SESSION['user_id'] == $_POST['per_id'] ) ) {
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
        $per_id = $_POST['per_id'];

        $sql = "UPDATE iss_issues SET short_description=?, long_description=?, open_date=?, close_date=?, priority=?, org=?, project=?, per_id=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$short_description, $long_description, $open_date, $close_date, $priority, $org, $project, $per_id, $id]);
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

        header("Location: issues_list.php");
        exit();
    }

    if (isset($_POST['delete_issue'])) {
        if( !( $_SESSION['admin'] == "Y" || $_SESSION['user_id'] == $_POST['per_id'] ) ) {
            header("Location: issues_list.php"); 
            exit();
        }
        $id = $_POST['id'];
        $sql = "DELETE FROM iss_issues WHERE id=?";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

        header("Location: issues_list.php");
        exit();
    }
}


// Fetch all issues with filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'open_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Modify the ORDER BY clause for priority sorting
$sql_sort = $sort;
if ($sort === 'priority') {
    if ($order === 'ASC') {
        $sql_sort = "FIELD(priority, 'Low', 'Medium', 'High')";
    } elseif ($order === 'DESC') {
        $sql_sort = "FIELD(priority, 'High', 'Medium', 'Low')";
    }
}

$limit = 10; // Number of issues per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($filter === 'open') {
    $sql = "SELECT * FROM iss_issues 
            WHERE (close_date IS NULL OR close_date > NOW()) 
            AND (short_description LIKE :search OR long_description LIKE :search) 
            ORDER BY $sql_sort $order LIMIT $limit OFFSET $offset";
} else {
    $sql = "SELECT * FROM iss_issues 
            WHERE (short_description LIKE :search OR long_description LIKE :search) 
            ORDER BY $sql_sort $order LIMIT $limit OFFSET $offset";
}

$stmt = $pdo->prepare($sql);
$stmt->execute(['search' => "%$search%"]);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total count for pagination
$count_sql = $filter === 'open' 
    ? "SELECT COUNT(*) FROM iss_issues WHERE (close_date IS NULL OR close_date > NOW())"
    : "SELECT COUNT(*) FROM iss_issues";
$total_issues = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_issues / $limit);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISS2: Issues List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-3">
        <h2 class="text-center">Issues List</h2>

        <!-- "+" Button to Add Issue -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <h3>
                <a href="issues_list.php?filter=open" class="btn btn-primary <?= $filter === 'open' ? 'active' : '' ?>">Open Issues</a>
                <a href="issues_list.php?filter=all" class="btn btn-secondary <?= $filter === 'all' ? 'active' : '' ?>">All Issues</a>
            </h3>
            <div>
                <button class="btn btn-success me-2 custom-btn" data-bs-toggle="modal" data-bs-target="#addIssueModal" title="Add Issue">
                    <i class="fas fa-plus"></i> <!-- Font Awesome Plus Icon -->
                </button>
                <a href="logout.php" class="btn btn-danger custom-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> <!-- Font Awesome Logout Icon -->
                </a>
            </div>
        </div>

        <style>
    /* Consolidated button styles */
    .custom-btn, .btn-primary, .btn-secondary, .btn-info, .btn-warning, .btn-danger {
        transition: box-shadow 0.2s ease, transform 0.2s ease;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2); /* Default shadow */
    }

    .custom-btn:hover, .btn-primary:hover, .btn-secondary:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover {
        box-shadow: 0px 6px 8px rgba(0, 0, 0, 0.3); /* Slightly deeper shadow on hover */
        filter: brightness(85%); /* Darken the button slightly */
        transform: scale(1.1); /* Enlarge the button slightly */
    }

    .custom-btn:active, .btn-primary:active, .btn-secondary:active, .btn-info:active, .btn-warning:active, .btn-danger:active {
        box-shadow: inset 0px 4px 6px rgba(0, 0, 0, 0.2); /* Inset shadow on click */
        transform: translateY(2px); /* Slight downward movement */
    }
</style>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

        <table class="table table-striped table-sm mt-2">
        <thead class="table-dark">
        <tr>
                    <th><a href="issues_list.php?filter=<?= $filter ?>&sort=id&order=<?= $sort === 'id' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">ID</a></th>
                    <th><a href="issues_list.php?filter=<?= $filter ?>&sort=short_description&order=<?= $sort === 'short_description' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">Short Description</a></th>
                    <th><a href="issues_list.php?filter=<?= $filter ?>&sort=open_date&order=<?= $sort === 'open_date' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">Open Date</a></th>
                    <th><a href="issues_list.php?filter=<?= $filter ?>&sort=close_date&order=<?= $sort === 'close_date' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white" style="text-decoration: none;">Close Date</a></th>
                    <th>
   <a href="issues_list.php?filter=<?= $filter ?>&sort=priority&order=<?= $sort === 'priority' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" 
       class="text-white" style="text-decoration: none;">
        Priority
    </a>
</th>
                    <th class="text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $issue) : ?>
                    <tr>
                        <td><?= htmlspecialchars($issue['id']); ?></td>
                        <td><?= htmlspecialchars($issue['short_description']); ?></td>
                        <td><?= htmlspecialchars($issue['open_date']); ?></td>
                        <td><?= htmlspecialchars($issue['close_date']); ?></td>
                        <td><?= htmlspecialchars($issue['priority']); ?></td>
                        <td>
    <!-- Read Button -->
    <button class="btn btn-info btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#readIssue<?= $issue['id']; ?>" title="Read" aria-label="Read Issue <?= $issue['id']; ?>">
        <i class="fas fa-eye"></i> <!-- Font Awesome Eye Icon -->
    </button>
    <?php if ($_SESSION['user_id'] == $issue['per_id'] || $_SESSION['admin'] == "Y") { ?>
        <!-- Update Button -->
        <button class="btn btn-warning btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#updateIssue<?= $issue['id']; ?>" title="Update">
            <i class="fas fa-edit"></i> <!-- Font Awesome Edit Icon -->
        </button>
        <!-- Delete Button -->
        <button class="btn btn-danger btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#deleteIssue<?= $issue['id']; ?>" title="Delete">
            <i class="fas fa-trash"></i> <!-- Font Awesome Trash Icon -->
        </button>
    <?php } ?>
</td>
                    </tr>

                    <!-- Create Modal -->
                    <div class="modal fade" id="addIssueModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">Add New Issue</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <label for="short_description">Short Description</label>
                                        <input type="text" name="short_description" class="form-control mb-2" required>

                                        <label for="long_description">Long Description</label>
                                        <textarea name="long_description" class="form-control mb-2"></textarea>

                                        <label for="open_date">Open Date</label>
                                        <input type="date" name="open_date" class="form-control mb-2" value="<?= date('Y-m-d'); ?>" required>

                                        <label for="close_date">Close Date</label>
                                        <input type="date" name="close_date" class="form-control mb-2">

                                        <label for="priority">Priority</label>
                                        <select name="priority" class="form-control mb-2" required>
                                            <option value="">-- Select Priority --</option>
                                            <option value="High">High</option>
                                            <option value="Medium">Medium</option>
                                            <option value="Low">Low</option>
                                        </select>

                                        <label for="org">Org</label>
                                        <input type="text" name="org" class="form-control mb-2">

                                        <label for="project">Project</label>
                                        <input type="text" name="project" class="form-control mb-2">

                                        <label for="per_id">Person Responsible</label>
                                        <select name="per_id" class="form-control mb-3">
                                            <option value="">-- Select Person --</option>
                                            <?php foreach ($persons as $person): ?>
                                                <option value="<?= $person['id']; ?>">
                                                    <?= htmlspecialchars($person['lname'] . ', ' . $person['fname']) . ' (' . $person['id'] .  ') '; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <label for="pdf_attachment">PDF</label>
                                        <input type="file" name="pdf_attachment" class="form-control mb-2"
                                            accept="application/pdf" />

                                        <button type="submit" name="create_issue" class="btn btn-success">Add Issue</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Read Modal -->
                    <div class="modal fade" id="readIssue<?= $issue['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Issue Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>ID:</strong> <?= htmlspecialchars($issue['id']); ?></p>
                                    <p><strong>Short Description:</strong> <?= htmlspecialchars($issue['short_description']); ?></p>
                                    <p><strong>Long Description:</strong> <?= htmlspecialchars($issue['long_description']); ?></p>
                                    <p><strong>Open Date:</strong> <?= htmlspecialchars($issue['open_date']); ?></p>
                                    <p><strong>Close Date:</strong> <?= htmlspecialchars($issue['close_date']); ?></p>
                                    <p><strong>Priority:</strong> <?= htmlspecialchars($issue['priority']); ?></p>
                                    <p><strong>Organization:</strong> <?= htmlspecialchars($issue['org']); ?></p>
                                    <p><strong>Project:</strong> <?= htmlspecialchars($issue['project']); ?></p>
                                    <p><strong>Person:</strong> <?= htmlspecialchars($issue['per_id']); ?></p>
                                    
                                    
                                    <?php
                                        $com_iss_id = $issue['id'];
                                        // Fetch comments this particular issue: gpcorser
                                        $comments_sql = "SELECT c.*, p.fname, p.lname 
                                                         FROM iss_comments c 
                                                         JOIN iss_persons p ON c.per_id = p.id 
                                                         WHERE c.iss_id = :iss_id";
                                        $comments_stmt = $pdo->prepare($comments_sql);
                                        $comments_stmt->bindParam(':iss_id', $issue['id'], PDO::PARAM_INT);
                                        $comments_stmt->execute();
                                        $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                    ?>
<?php foreach ($comments as $comment) : ?>
    <div style="font-family: monospace;">
        <span style="display:inline-block; width: 180px;">
            <?= htmlspecialchars($comment['lname'] . ", " . $comment['fname']) ?>
        </span>
        <span style="display:inline-block; width: 300px;">
            <?= htmlspecialchars($comment['short_comment']) ?>
        </span>
        <span style="display:inline-block; width: 140px;">
            <?= htmlspecialchars($comment['posted_date']) ?>
        </span>
        <span style="display:inline-block; width: 150px;">
            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#readIssue<?= $comment['id']; ?>" title="Read">
                <i class="fas fa-eye"></i> <!-- Font Awesome Read Icon -->
            </button>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updateIssue<?= $comment['id']; ?>" title="Update">
                <i class="fas fa-edit"></i> <!-- Font Awesome Edit Icon -->
            </button>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteIssue<?= $comment['id']; ?>" title="Delete">
                <i class="fas fa-trash"></i> <!-- Font Awesome Trash Icon -->
            </button>
        </span>
    </div>
<?php endforeach; ?>

<h5>Comments:</h5>
<?php foreach ($comments as $comment): ?>
    <p><strong><?= htmlspecialchars($comment['lname'] . ', ' . $comment['fname']); ?>:</strong> <?= htmlspecialchars($comment['short_comment']); ?></p>
<?php endforeach; ?>

<?php if (!empty($issue['pdf_attachment'])): ?>
    <p><strong>Attachment:</strong> <a href="uploads/<?= htmlspecialchars($issue['pdf_attachment']); ?>" target="_blank">Download PDF</a></p>
<?php endif; ?>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update Modal -->
                    <div class="modal fade" id="updateIssue<?= $issue['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Issue</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $issue['id']; ?>">
                                        <label for="short_description">Short Description</label>
                                        <input type="text" name="short_description" class="form-control mb-2" value="<?= htmlspecialchars($issue['short_description']); ?>" required>
                                        <label for="long_description">Long Description</label>
                                        <textarea name="long_description" class="form-control mb-2"><?= htmlspecialchars($issue['long_description']); ?></textarea>
                                        <label for="open_date">Open Date</label>
                                        <input type="date" name="open_date" class="form-control mb-2" value="<?= $issue['open_date']; ?>" readonly>
                                        <label for="close_date">Close Date</label>
                                        <input type="date" name="close_date" class="form-control mb-2" value="<?= $issue['close_date']; ?>">
                                        <label for="priority">Priority</label>
                                        <select name="priority" class="form-control mb-2" required>
                                            <option value="">-- Select Priority --</option>
                                            <option value="High">High</option>
                                            <option value="Medium">Medium</option>
                                            <option value="Low">Low</option>
                                        </select>
                                        <label for="org">Org</label>
                                        <input type="text" name="org" class="form-control mb-2" value="<?= $issue['org']; ?>">
                                        <label for="project">Project</label>
                                        <input type="text" name="project" class="form-control mb-2" value="<?= $issue['project']; ?>">
                                        <label for="per_id">Person Responsible</label>
                                        <input type="number" name="per_id" class="form-control mb-2" value="<?= $issue['per_id']; ?>">
                                        <button type="submit" name="update_issue" class="btn btn-primary">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Modal -->
                    <div class="modal fade" id="deleteIssue<?= $issue['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete this issue?</p>
                                    <p><strong>ID:</strong> <?= htmlspecialchars($issue['id']); ?></p>
                                    <p><strong>Short Description:</strong> <?= htmlspecialchars($issue['short_description']); ?></p>
                                    <p><strong>Long Description:</strong> <?= htmlspecialchars($issue['long_description']); ?></p>
                                    <p><strong>Open Date:</strong> <?= htmlspecialchars($issue['open_date']); ?></p>
                                    <p><strong>Close Date:</strong> <?= htmlspecialchars($issue['close_date']); ?></p>
                                    <p><strong>Priority:</strong> <?= htmlspecialchars($issue['priority']); ?></p>
                                    <p><strong>Organization:</strong> <?= htmlspecialchars($issue['org']); ?></p>
                                    <p><strong>Project:</strong> <?= htmlspecialchars($issue['project']); ?></p>
                                    <p><strong>Person:</strong> <?= htmlspecialchars($issue['per_id']); ?></p>

                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $issue['id']; ?>">
                                        <button type="submit" name="delete_issue" class="btn btn-danger">Delete</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="issues_list.php?page=<?= $i ?>&filter=<?= $filter ?>&sort=<?= $sort ?>&order=<?= $order ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php Database::disconnect(); ?>
