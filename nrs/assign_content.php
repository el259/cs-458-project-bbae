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
$drillId = intval($_GET['drill_id'] ?? $_POST['drill_id'] ?? 0);
$textbookId = intval($_GET['textbook_id'] ?? $_POST['textbook_id'] ?? 0);
$preselectedClassroom = intval($_GET['classroom_id'] ?? 0);
$error = '';
$success = '';

$databaseConnection = hum_conn_no_login();

// Load teacher's classrooms (quoted style + safe bind names)
$classrooms = [];
$sql = '
    SELECT c."CLASSROOM_ID", c."CLASSROOM_NAME"
    FROM "CLASSROOM" c
    JOIN "CLASSROOM_MEMBERSHIP" m
      ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
    WHERE m."USER_ID" = :user_id
      AND m."ROLE" = \'teacher\'
    ORDER BY c."CLASSROOM_NAME"
';
$stmt = oci_parse($databaseConnection, $sql);
oci_bind_by_name($stmt, ':user_id', $userId);
if (oci_execute($stmt)) {
    while ($row = oci_fetch_assoc($stmt)) {
        $classrooms[] = $row;
    }
} else {
    $e = oci_error($stmt);
    $error = 'Classroom query failed: ' . ($e['message'] ?? 'unknown');
}
oci_free_statement($stmt);

// Assign
if (isset($_POST['assign']) && isset($_POST['classroom_id'])) {
    $cid = intval($_POST['classroom_id']);
    if ($drillId > 0) {
        $sql = 'INSERT INTO "CLASSROOM_DRILL" ("CLASSROOM_ID", "DRILL_ID") VALUES (:classroom_id, :drill_id)';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':classroom_id', $cid);
        oci_bind_by_name($stmt, ':drill_id', $drillId);
    } elseif ($textbookId > 0) {
        $sql = 'INSERT INTO "CLASSROOM_TEXTBOOK" ("CLASSROOM_ID", "TEXTBOOK_ID") VALUES (:classroom_id, :textbook_id)';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':classroom_id', $cid);
        oci_bind_by_name($stmt, ':textbook_id', $textbookId);
    } else {
        $error = 'Nothing to assign. Open this page from a drill or textbook.';
    }

    if ($error === '' && isset($stmt)) {
        if (@oci_execute($stmt)) {
            oci_commit($databaseConnection);
            $success = 'Assigned successfully.';
        } else {
            $e = oci_error($stmt);
            $error = 'Assignment failed: ' . ($e['message'] ?? 'already assigned or error');
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assign Content</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Assign to Classroom</h1>

<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<?php if ($drillId > 0): ?>
    <p>Assigning drill #<?= $drillId ?></p>
<?php elseif ($textbookId > 0): ?>
    <p>Assigning textbook #<?= $textbookId ?></p>
<?php else: ?>
    <p>Select a classroom, then choose what to assign from the drill or textbook pages.</p>
<?php endif; ?>

<?php if (count($classrooms) === 0): ?>
    <p>You have no classrooms (or the query failed).</p>
<?php else: ?>
    <form method="post">
        <?php if ($drillId > 0): ?>
            <input type="hidden" name="drill_id" value="<?= $drillId ?>" />
        <?php endif; ?>
        <?php if ($textbookId > 0): ?>
            <input type="hidden" name="textbook_id" value="<?= $textbookId ?>" />
        <?php endif; ?>

        <label>Classroom</label>
        <select name="classroom_id" required>
            <?php foreach ($classrooms as $c): ?>
                <option value="<?= $c['CLASSROOM_ID'] ?>" <?= $c['CLASSROOM_ID'] == $preselectedClassroom ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['CLASSROOM_NAME'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" name="assign" value="Assign" />
    </form>
<?php endif; ?>

<p><a href="teacher.php">Back to dashboard</a></p>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
