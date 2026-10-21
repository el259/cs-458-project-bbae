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
$databaseConnection = hum_conn_no_login();

$drills = [];
$sql = '
    SELECT "DRILL_ID", "TITLE", "DESCRIPTION"
    FROM "DRILL"
    WHERE "CREATED_BY" = :uid
    ORDER BY "TITLE"
';
$stmt = oci_parse($databaseConnection, $sql);
oci_bind_by_name($stmt, ':uid', $userId);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $drills[] = $row;
}
oci_free_statement($stmt);
oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Drills</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Manage Drills</h1>

<p><a href="create_drill.php">Create new drill</a></p>

<?php if (count($drills) === 0): ?>
    <p>No drills yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($drills as $d): ?>
            <li>
                <strong><?= htmlspecialchars($d['TITLE'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if ($d['DESCRIPTION']): ?>
                    — <?= htmlspecialchars($d['DESCRIPTION'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <br />
                <a href="assign_content.php?drill_id=<?= $d['DRILL_ID'] ?>">Assign to classroom</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="teacher.php">Back to dashboard</a></p>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
