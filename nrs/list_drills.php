<?php
session_start();
require __DIR__ . '/auth.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$userId = $_SESSION['user_id'];
$classroomId = intval($_GET['id'] ?? ($_SESSION['classroomId'] ?? 0));
$error = '';
$drills = [];

echo '<pre>';
echo "Session user_id: " . var_export($userId, true) . "\n";
echo "Classroom ID: " . var_export($classroomId, true) . "\n\n";

if ($classroomId > 0) {
    $databaseConnection = hum_conn_no_login();

    // Diagnostic using the same bind names as teacher.php
    $diagSql = '
        SELECT "CLASSROOM_ID", "USER_ID", "ROLE"
        FROM "CLASSROOM_MEMBERSHIP"
        WHERE "USER_ID" = :user_id
    ';
    $diagStmt = oci_parse($databaseConnection, $diagSql);
    oci_bind_by_name($diagStmt, ':user_id', $userId);
    echo "Memberships for user $userId:\n";
    if (oci_execute($diagStmt)) {
        $found = false;
        while ($row = oci_fetch_assoc($diagStmt)) {
            $found = true;
            echo "  CLASSROOM_ID=" . $row['CLASSROOM_ID'] . " ROLE=" . ($row['ROLE'] ?? '') . "\n";
        }
        if (!$found) {
            echo "  (none found)\n";
        }
    } else {
        $e = oci_error($diagStmt);
        echo "Diag query failed: " . ($e['message'] ?? 'unknown') . "\n";
    }
    oci_free_statement($diagStmt);
    echo "\n";

    // Membership check with safe bind names
    $verifySql = '
        SELECT 1
        FROM "CLASSROOM_MEMBERSHIP"
        WHERE "CLASSROOM_ID" = :classroom_id
          AND "USER_ID" = :user_id
    ';
    $verifyStmt = oci_parse($databaseConnection, $verifySql);
    oci_bind_by_name($verifyStmt, ':classroom_id', $classroomId);
    oci_bind_by_name($verifyStmt, ':user_id', $userId);
    $execOk = oci_execute($verifyStmt);
    if (!$execOk) {
        $e = oci_error($verifyStmt);
        echo "Verify query failed: " . ($e['message'] ?? 'unknown') . "\n";
    }
    $ok = $execOk && oci_fetch_assoc($verifyStmt) !== false;
    oci_free_statement($verifyStmt);

    echo "Membership check result: " . ($ok ? 'PASS' : 'FAIL') . "\n";
    echo '</pre>';

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
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="take.css" />
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
