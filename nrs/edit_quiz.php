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
$userId = $_SESSION['user_id'];

// Resolve classroom the same way as the other teacher pages
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

    // Load a quiz into the session and hand off to the creator
    if ($error === '' && isset($_POST['quizId']))
    {
        $quizId = intval($_POST['quizId']);

        $loadSql = '
            SELECT "QUIZ_ID", "TITLE", "QUIZ_JSON",
                   TO_CHAR("DUE_AT", \'YYYY-MM-DD"T"HH24:MI\') AS "DUE_AT"
            FROM "QUIZ"
            WHERE "QUIZ_ID" = :quiz_id
              AND "CLASSROOM_ID" = :classroom_id
        ';
        $loadStatement = oci_parse($databaseConnection, $loadSql);
        oci_bind_by_name($loadStatement, ':quiz_id', $quizId);
        oci_bind_by_name($loadStatement, ':classroom_id', $classroomId);

        if (!oci_execute($loadStatement) || ($quiz = oci_fetch_assoc($loadStatement)) === false)
        {
            $error = 'Quiz not found.';
        }
        else
        {
            $json = $quiz['QUIZ_JSON'];
            if (is_object($json))
            {
                $json = $json->load();
                $quiz['QUIZ_JSON']->free();
            }

            $decoded = json_decode($json, true);
            $questions = is_array($decoded['questions'] ?? null) ? $decoded['questions'] : [];

            $_SESSION['quiz'] = [
                'name' => $quiz['TITLE'],
                'questions' => $questions,
                'editing' => -1,
                'classroom_id' => $classroomId,
                'due_at' => $quiz['DUE_AT'] ?: null,
                'quiz_id' => intval($quiz['QUIZ_ID']),
                'pdf' => $decoded['pdf'] ?? ''
            ];

            oci_free_statement($loadStatement);
            oci_close($databaseConnection);
            header('Location: create_quiz.php');
            exit();
        }
        oci_free_statement($loadStatement);
    }

    // List quizzes for this classroom
    $quizzes = [];
    if ($error === '' || $classroomId > 0)
    {
        $listSql = '
            SELECT "QUIZ_ID", "TITLE",
                   TO_CHAR("DUE_AT", \'YYYY-MM-DD"T"HH24:MI\') AS "DUE_AT"
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
                    'title' => $q['TITLE'],
                    'due' => $q['DUE_AT']
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
    <title>Edit Quiz</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Edit Quiz</h1>
<?php if (!empty($classroomName)): ?>
    <p>Classroom: <?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($classroomId > 0): ?>
    <?php if (count($quizzes) === 0): ?>
        <p>No quizzes in this classroom.</p>
    <?php else: ?>
        <form method="post" action="edit_quiz.php?id=<?= $classroomId ?>">
            <?php foreach ($quizzes as $quiz): ?>
                <div>
                    <button type="submit" class="classroom-link" name="quizId" value="<?= $quiz['id'] ?>">
                        <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <label>Due: <?= $quiz['due'] ? htmlspecialchars($quiz['due'], ENT_QUOTES, 'UTF-8') : 'No due date' ?></label>
                </div>
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="hidden" name="id" value="<?= $classroomId ?>" />
    <input type="submit" name="goback" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
