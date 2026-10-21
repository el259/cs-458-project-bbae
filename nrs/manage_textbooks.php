<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

$databaseConnection = hum_conn_no_login();
if ($databaseConnection === false) {
    $error = 'Unable to connect to the database.';
}

// Create textbook
if ($error === '' && isset($_POST['createTextbook'])) {
    $title = trim($_POST['title'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $language === '') {
        $error = 'Title and language are required.';
    } else {
        $sql = '
            INSERT INTO "TEXTBOOK" (
                "TITLE", "LANGUAGE", "LEVEL", "DESCRIPTION", "CREATED_BY"
            ) VALUES (
                :title, :language, :tb_level, :description, :created_by
            )
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':language', $language);
        oci_bind_by_name($stmt, ':tb_level', $level);
        oci_bind_by_name($stmt, ':description', $description);
        oci_bind_by_name($stmt, ':created_by', $userId);

        if (oci_execute($stmt)) {
            oci_commit($databaseConnection);
            $success = 'Textbook created.';
        } else {
            $e = oci_error($stmt);
            $error = 'Failed to create textbook: ' . ($e['message'] ?? 'unknown');
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

// List textbooks created by this teacher
$textbooks = [];
if ($error === '') {
    $sql = '
        SELECT "TEXTBOOK_ID", "TITLE", "LANGUAGE", "LEVEL"
        FROM "TEXTBOOK"
        WHERE "CREATED_BY" = :user_id
        ORDER BY "TITLE"
    ';
    $stmt = oci_parse($databaseConnection, $sql);
    oci_bind_by_name($stmt, ':user_id', $userId);
    if (oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $textbooks[] = $row;
        }
    }
    oci_free_statement($stmt);
}

oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Textbooks</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Manage Textbooks</h1>

<?php if ($error !== ''): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<h2>Create Textbook</h2>
<form method="post">
    <div>
        <label for="title">Title</label>
        <input type="text" name="title" id="title" required />
    </div>
    <div>
        <label for="language">Language</label>
        <input type="text" name="language" id="language" required placeholder="e.g. Spanish" />
    </div>
    <div>
        <label for="level">Level</label>
        <input type="text" name="level" id="level" placeholder="e.g. A1, Beginner" />
    </div>
    <div>
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="3"></textarea>
    </div>
    <input type="submit" name="createTextbook" value="Create Textbook" />
</form>

<h2>Your Textbooks</h2>
<?php if (count($textbooks) === 0): ?>
    <p>No textbooks yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($textbooks as $tb): ?>
            <li>
                <a href="edit_textbook.php?id=<?= $tb['TEXTBOOK_ID'] ?>">
                    <?= htmlspecialchars($tb['TITLE'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                (<?= htmlspecialchars($tb['LANGUAGE'], ENT_QUOTES, 'UTF-8') ?>
                <?= $tb['LEVEL'] ? ' — ' . htmlspecialchars($tb['LEVEL'], ENT_QUOTES, 'UTF-8') : '' ?>)
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="teacher.php">Back to dashboard</a></p>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
