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

// "Delete" currently only hides the classroom from view (IS_ACTIVE = 'N').
// Permanent removal will be handled later on the database side.
if (isset($_POST['classroomId']) && isset($_POST['confirmDelete']))
{
    $classroomId = intval($_POST['classroomId']);

    if ($classroomId <= 0)
    {
        $error = 'Invalid classroom.';
    }
    else
    {
        $updateSql = '
            UPDATE "CLASSROOM" c
            SET c."IS_ACTIVE" = \'N\'
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
        oci_bind_by_name($updateStatement, ':classroom_id', $classroomId);
        oci_bind_by_name($updateStatement, ':user_id', $userId);

        if (!oci_execute($updateStatement, OCI_NO_AUTO_COMMIT))
        {
            $e = oci_error($updateStatement);
            oci_rollback($databaseConnection);
            $error = 'Unable to hide the classroom: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
        }
        else
        {
            oci_commit($databaseConnection);
            $success = 'Classroom hidden from view.';
        }
        oci_free_statement($updateStatement);
    }
}

// Load the teacher's active classrooms
$classrooms = [];
$listSql = '
    SELECT c."CLASSROOM_ID", c."CLASSROOM_NAME"
    FROM "CLASSROOM" c
    JOIN "CLASSROOM_MEMBERSHIP" m
      ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
    WHERE m."USER_ID" = :user_id
      AND m."ROLE" = \'teacher\'
      AND c."IS_ACTIVE" = \'Y\'
    ORDER BY c."CLASSROOM_NAME"
';
$listStatement = oci_parse($databaseConnection, $listSql);
oci_bind_by_name($listStatement, ':user_id', $userId);

if (oci_execute($listStatement))
{
    while ($row = oci_fetch_assoc($listStatement))
    {
        $classrooms[] = [
            'id' => $row['CLASSROOM_ID'],
            'name' => $row['CLASSROOM_NAME']
        ];
    }
}
oci_free_statement($listStatement);
oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Delete Classrooms</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Delete Classrooms</h1>
<p>Deleting a classroom hides it from your list. It is not permanently removed yet — permanent deletion will be handled later on the database side. You can restore a hidden classroom from the Archive page.</p>

<?php if ($error !== ''): ?>
    <p><?= $error ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if (count($classrooms) === 0): ?>
    <p>You have no active classrooms.</p>
<?php else: ?>
    <?php foreach ($classrooms as $classroom): ?>
        <div>
            <strong><?= htmlspecialchars($classroom['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <form method="post" action="delete_classrooms.php" style="display:inline;"
                  onsubmit="return confirm('Hide this classroom from view? You can restore it later from Archive.');">
                <input type="hidden" name="classroomId" value="<?= $classroom['id'] ?>" />
                <input type="hidden" name="confirmDelete" value="1" />
                <input type="submit" value="Delete" />
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="submit" name="goback" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
