<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id']))
{
    header('Location: signin.php');
    exit();
}

$error = '';
$success = '';
$userId = $_SESSION['user_id'];
$databaseConnection = hum_conn_no_login();

// Archive or restore a classroom the current teacher owns
if (isset($_POST['classroomId']) && isset($_POST['action']))
{
    $classroomId = intval($_POST['classroomId']);
    $action = $_POST['action'];

    if ($classroomId <= 0 || ($action !== 'archive' && $action !== 'restore'))
    {
        $error = 'Invalid request.';
    }
    else
    {
        $newActive = ($action === 'archive') ? 'N' : 'Y';

        $updateSql = '
            UPDATE "CLASSROOM" c
            SET c."IS_ACTIVE" = :is_active
            WHERE c."CLASSROOM_ID" = :classroom_id
              AND EXISTS (
                  SELECT 1
                  FROM "CLASSROOM_MEMBERSHIP" m
                  WHERE m."CLASSROOM_ID" = c."CLASSROOM_ID"
                    AND m."USER_ID" = :user_id
                    AND m."ROLE" = \'teacher\'
              )
        ';
        $updateStatement = oci_parse($databaseConnection, $updateSql);
        oci_bind_by_name($updateStatement, ':is_active', $newActive);
        oci_bind_by_name($updateStatement, ':classroom_id', $classroomId);
        oci_bind_by_name($updateStatement, ':user_id', $userId);

        if (!oci_execute($updateStatement, OCI_NO_AUTO_COMMIT))
        {
            $e = oci_error($updateStatement);
            oci_rollback($databaseConnection);
            $error = 'Unable to update the classroom: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
        }
        else
        {
            oci_commit($databaseConnection);
            $success = ($action === 'archive')
                ? 'Classroom archived.'
                : 'Classroom restored.';
        }
        oci_free_statement($updateStatement);
    }
}

// Load the teacher's classrooms (active and archived)
$classrooms = [];
$listSql = '
    SELECT c."CLASSROOM_ID", c."CLASSROOM_NAME", c."IS_ACTIVE"
    FROM "CLASSROOM" c
    JOIN "CLASSROOM_MEMBERSHIP" m
      ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
    WHERE m."USER_ID" = :user_id
      AND m."ROLE" = \'teacher\'
    ORDER BY c."IS_ACTIVE" DESC, c."CLASSROOM_NAME"
';
$listStatement = oci_parse($databaseConnection, $listSql);
oci_bind_by_name($listStatement, ':user_id', $userId);

if (oci_execute($listStatement))
{
    while ($row = oci_fetch_assoc($listStatement))
    {
        $classrooms[] = [
            'id' => $row['CLASSROOM_ID'],
            'name' => $row['CLASSROOM_NAME'],
            'active' => $row['IS_ACTIVE'] === 'Y'
        ];
    }
}
oci_free_statement($listStatement);
oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Archive Classrooms</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Archive Classrooms</h1>

<?php if ($error !== ''): ?>
    <p><?= $error ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if (count($classrooms) === 0): ?>
    <p>You have no classrooms.</p>
<?php else: ?>
    <?php foreach ($classrooms as $classroom): ?>
        <div>
            <strong><?= htmlspecialchars($classroom['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($classroom['active']): ?>
                <em>(active)</em>
                <form method="post" action="archive_classrooms.php" style="display:inline;">
                    <input type="hidden" name="classroomId" value="<?= $classroom['id'] ?>" />
                    <input type="hidden" name="action" value="archive" />
                    <input type="submit" value="Archive" />
                </form>
            <?php else: ?>
                <em>(archived)</em>
                <form method="post" action="archive_classrooms.php" style="display:inline;">
                    <input type="hidden" name="classroomId" value="<?= $classroom['id'] ?>" />
                    <input type="hidden" name="action" value="restore" />
                    <input type="submit" value="Restore" />
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="submit" name="goback" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
