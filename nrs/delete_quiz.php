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

$classroomId = intval($_GET['id'] ?? ($_SESSION['classroomId'] ?? 0));

if ($classroomId <= 0)
{
    $error = 'No classroom selected. Open this page from a classroom.';
}
else
{
    $databaseConnection = hum_conn_no_login();

    // Verify teacher membership
    $verifySql = '
        SELECT c."CLASSROOM_ID", c."CLASSROOM_NAME"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" m
          ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
        WHERE c."CLASSROOM_ID" = :classroom_id
          AND m."USER_ID" = :user_id
          AND m."ROLE" = \'teacher\'
    ';
    $verifyStatement = oci_parse($databaseConnection, $verifySql);
    oci_bind_by_name($verifyStatement, ':classroom_id', $classroomId);
    oci_bind_by_name($verifyStatement, ':user_id', $userId);

    $classroomName = null;
    if (!oci_execute($verifyStatement) || ($row = oci_fetch_assoc($verifyStatement)) === false)
    {
        $error = 'Classroom not found or you do not have permission.';
        $classroomId = 0;
    }
    else
    {
        $classroomName = $row['CLASSROOM_NAME'];
    }
    oci_free_statement($verifyStatement);

    // Delete a quiz (and its attempts)
    if ($error === '' && isset($_POST['quizId']) && isset($_POST['confirmDelete']))
    {
        $quizId = intval($_POST['quizId']);

        // Confirm the quiz belongs to this classroom
        $checkSql = '
            SELECT "QUIZ_ID"
            FROM "QUIZ"
            WHERE "QUIZ_ID" = :quiz_id
              AND "CLASSROOM_ID" = :classroom_id
        ';
        $checkStatement = oci_parse($databaseConnection, $checkSql);
        oci_bind_by_name($checkStatement, ':quiz_id', $quizId);
        oci_bind_by_name($checkStatement, ':classroom_id', $classroomId);

        if (!oci_execute($checkStatement) || oci_fetch_assoc($checkStatement) === false)
        {
            $error = 'Quiz not found.';
        }
        else
        {
            $deleteAttemptsSql = '
                DELETE FROM "QUIZ_ATTEMPT"
                WHERE "QUIZ_ID" = :quiz_id
            ';
            $deleteAttemptsStatement = oci_parse($databaseConnection, $deleteAttemptsSql);
            oci_bind_by_name($deleteAttemptsStatement, ':quiz_id', $quizId);

            $deleteQuizSql = '
                DELETE FROM "QUIZ"
                WHERE "QUIZ_ID" = :quiz_id
                  AND "CLASSROOM_ID" = :classroom_id
            ';
            $deleteQuizStatement = oci_parse($databaseConnection, $deleteQuizSql);
            oci_bind_by_name($deleteQuizStatement, ':quiz_id', $quizId);
            oci_bind_by_name($deleteQuizStatement, ':classroom_id', $classroomId);

            $ok = oci_execute($deleteAttemptsStatement, OCI_NO_AUTO_COMMIT)
                && oci_execute($deleteQuizStatement, OCI_NO_AUTO_COMMIT);

            if (!$ok)
            {
                $e = oci_error($databaseConnection);
                oci_rollback($databaseConnection);
                $error = 'Unable to delete the quiz: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
            }
            else
            {
                oci_commit($databaseConnection);
                $success = 'Quiz deleted.';
            }

            oci_free_statement($deleteAttemptsStatement);
            oci_free_statement($deleteQuizStatement);
        }
        oci_free_statement($checkStatement);
    }

    // List remaining quizzes
    $quizzes = [];
    if ($classroomId > 0)
    {
        $listSql = '
            SELECT "QUIZ_ID", "TITLE"
            FROM "QUIZ"
            WHERE "CLASSROOM_ID" = :classroom_id
            ORDER BY "TITLE"
        ';
        $listStatement = oci_parse($databaseConnection, $listSql);
        oci_bind_by_name($listStatement, ':classroom_id', $classroomId);

        if (oci_execute($listStatement))
        {
            while ($q = oci_fetch_assoc($listStatement))
            {
                $quizzes[] = [
                    'id' => $q['QUIZ_ID'],
                    'title' => $q['TITLE']
                ];
            }
        }
        oci_free_statement($listStatement);
    }

    oci_close($databaseConnection);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Delete Quiz</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Delete Quiz</h1>
<?php if (!empty($classroomName)): ?>
    <p>Classroom: <?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<p>Deleting a quiz permanently removes it and all student attempts. This cannot be undone.</p>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($classroomId > 0): ?>
    <?php if (count($quizzes) === 0): ?>
        <p>No quizzes in this classroom.</p>
    <?php else: ?>
        <?php foreach ($quizzes as $quiz): ?>
            <div>
                <strong><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <form method="post" action="delete_quiz.php?id=<?= $classroomId ?>" style="display:inline;"
                      onsubmit="return confirm('Permanently delete this quiz and all attempts? This cannot be undone.');">
                    <input type="hidden" name="quizId" value="<?= $quiz['id'] ?>" />
                    <input type="hidden" name="confirmDelete" value="1" />
                    <input type="submit" value="Delete" />
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="hidden" name="id" value="<?= $classroomId ?>" />
    <input type="submit" name="goback" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
