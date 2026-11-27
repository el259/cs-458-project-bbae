<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$userId = $_SESSION['user_id'];
$classroomId = intval($_GET['id'] ?? ($_SESSION['classroomId'] ?? 0));
$error = '';
$drills = [];

if ($classroomId > 0) {
    $databaseConnection = hum_conn_no_login();

    // Membership check
    $verifySql = '
        SELECT 1
        FROM "CLASSROOM_MEMBERSHIP"
        WHERE "CLASSROOM_ID" = :classroom_id
          AND "USER_ID" = :user_id
    ';
    $verifyStmt = oci_parse($databaseConnection, $verifySql);
    oci_bind_by_name($verifyStmt, ':classroom_id', $classroomId);
    oci_bind_by_name($verifyStmt, ':user_id', $userId);
    $ok = oci_execute($verifyStmt) && oci_fetch_assoc($verifyStmt) !== false;
    oci_free_statement($verifyStmt);

    if ($ok) {
        $sql = '
            SELECT d."DRILL_ID", d."TITLE", d."DESCRIPTION"
            FROM "DRILL" d
            JOIN "CLASSROOM_DRILL" cd ON cd."DRILL_ID" = d."DRILL_ID"
            WHERE cd."CLASSROOM_ID" = :classroom_id
            ORDER BY d."TITLE"
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':classroom_id', $classroomId);
        if (oci_execute($stmt)) {
            while ($row = oci_fetch_assoc($stmt)) {
                $drills[] = $row;
            }
        }
        oci_free_statement($stmt);
    } else {
        $error = 'You do not have access to this classroom.';
    }
    oci_close($databaseConnection);
} else {
    $error = 'No classroom selected.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Drills</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/global.css" />
    <link rel="stylesheet" href="css/take.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Drills</h1>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($drills) === 0): ?>
    <p>No drills assigned to this classroom yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($drills as $d): ?>
            <li>
                <a href="drill.php?id=<?= $d['DRILL_ID'] ?>&classroom=<?= $classroomId ?>">
                    <?= htmlspecialchars($d['TITLE'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="teacher.php">Back to classroom</a></p>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
