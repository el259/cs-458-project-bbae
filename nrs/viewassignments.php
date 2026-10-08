<?php
session_start();
require __DIR__ . '/auth.php';
requireAccount('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if(isset($_POST['classroomId'])) {
    $_SESSION['classroomId'] = htmlspecialchars($_POST['classroomId'], ENT_QUOTES, 'UTF-8');
}
if(!isset($_SESSION['classroomId'])) {
    header('Location: student.php');
    exit();
}

$classroomId = intval($_SESSION['classroomId'] ?? -1);
$classroom = null;
$assignments = [];
$error = '';

if ($classroomId <= 0) {
    $error = 'Classroom not found.';
} else {
    $databaseConnection = hum_conn_no_login();
    $classroomSql = '
        SELECT c."CLASSROOM_NAME"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" m
          ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
        WHERE c."CLASSROOM_ID" = :classroom_id
          AND m."USER_ID" = :user_id
    ';
    $classroomStatement = oci_parse($databaseConnection, $classroomSql);
    $userId = $_SESSION['user_id'];
    oci_bind_by_name($classroomStatement, ':classroom_id', $classroomId);
    oci_bind_by_name($classroomStatement, ':user_id', $userId);

    if (!oci_execute($classroomStatement)) {
        $error = 'Unable to load the classroom.';
    } else {
        $classroom = oci_fetch_assoc($classroomStatement);
        if ($classroom === false) {
            $error = 'Classroom not found.';
        } else {
            $assignmentsSQL = '
                SELECT QUIZ.QUIZ_ID, QUIZ.TITLE, QUIZ.DUE_AT
                FROM QUIZ
                WHERE QUIZ.CLASSROOM_ID = :classroom_id
                ORDER BY QUIZ.DUE_AT
            ';
            $assignmentsStatement = oci_parse($databaseConnection, $assignmentsSQL);
            oci_bind_by_name($assignmentsStatement, ':classroom_id', $classroomId);
            if(oci_execute($assignmentsStatement, OCI_DEFAULT)) {
                while ($assignment = oci_fetch_assoc($assignmentsStatement)) {
                    $assignments[] = [
                        'id' => $assignment['QUIZ_ID'],
                        'title' => $assignment['TITLE'],
                        'due' => $assignment['DUE_AT']
                    ];
                }
            }

            oci_free_statement($assignmentsStatement);
        }
    }

    oci_free_statement($classroomStatement);
    oci_close($databaseConnection);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Classroom</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
    <link rel="stylesheet" href="classroom.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<?php if ($error !== ''): ?>
    <h2>Classroom</h2>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <h2><?= htmlspecialchars($classroom['CLASSROOM_NAME'], ENT_QUOTES, 'UTF-8') ?></h2>

    <h3>Assignments</h3>
    <?php if (count($assignments) === 0): ?>
        <p>No assignments yet.</p>
    <?php else: ?>
        <form action="quiz.php" method="post">
            <?php foreach ($assignments as $assignment): 
                $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $assignment['due']); ?>
                <div>
                    <button type="submit" name="quizId" id="<?= $assignment['id'] ?>" value="<?= $assignment['id'] ?>"><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></button>
                    <label for="<?= $assignment['id'] ?>">Due on: <?= htmlspecialchars($date->format('F j, Y, g:i A'), ENT_QUOTES, 'UTF-8') ?></label>
                </div>
            <?php endforeach; ?>
        </form>
    <?php endif; 
        if(!empty($_SESSION['teacher'])) { ?>
    <div class="quiz-actions">
        <form method="get" action="create.php">
            <input type="submit" value="Make quiz (Broken)" />
        </form> 
        <form method="get" action="404.php">
            <input type="submit" value="Edit quiz" />
        </form>
        <form method="get" action="404.php">
            <input type="submit" value="Delete quiz" />
        </form>
    </div>
    <?php } ?>
<?php endif; ?>

<form method="get" action="student.php">
    <input type="submit" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
