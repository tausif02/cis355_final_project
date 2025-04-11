<?php
$default_password = "learn";
$hash = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salt = trim($_POST['salt']);
    $combined = $default_password . $salt;
    $hash = md5($combined);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MD5 Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>MD5 Hash Generator (like md5hashgenerator.com)</h2>
        <form method="POST" action="generate_hash.php" class="mt-3">
            <div class="mb-3">
                <label for="salt" class="form-label">Salt:</label>
                <input type="text" class="form-control" id="salt" name="salt" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate MD5 Hash</button>
        </form>

        <?php if (!empty($hash)): ?>
            <div class="mt-4">
                <h4>Generated MD5 Hash of "learn + salt":</h4>
                <p><strong><?= htmlspecialchars($hash); ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
