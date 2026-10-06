<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$classroomId = intval($_GET['id'] ?? 0);
$classroom = null;
$students = [];
$assignments = [];
$error = '';

if ($classroomId <= 0) {
    $error = 'Classroom not found.';
} else {
    $databaseConnection = hum_conn_no_login();
    $classroomSql = '
        SELECT c."CLASSROOM_NAME", c."PASSCODE"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" m
          ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
        WHERE c."CLASSROOM_ID" = :classroom_id
          AND m."USER_ID" = :user_id
          AND m."ROLE" = \'teacher\'
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
            $studentSql = '
                SELECT u."FIRST_NAME", u."EMAIL"
                FROM "CLASSROOM_MEMBERSHIP" m
                JOIN "APP_USER" u ON u."USER_ID" = m."USER_ID"
                WHERE m."CLASSROOM_ID" = :classroom_id
                  AND m."ROLE" = \'student\'
                ORDER BY u."EMAIL"
            ';
            $studentStatement = oci_parse($databaseConnection, $studentSql);
            oci_bind_by_name($studentStatement, ':classroom_id', $classroomId);
            oci_execute($studentStatement);
            while ($student = oci_fetch_assoc($studentStatement)) {
                $students[] = $student['FIRST_NAME'];
            }

            $assignmentSql = '
                SELECT q."TITLE"
                FROM "CLASSROOM_ASSIGNMENT" a
                JOIN "QUIZ" q ON q."QUIZ_ID" = a."QUIZ_ID"
                WHERE a."CLASSROOM_ID" = :classroom_id
                ORDER BY q."TITLE"
            ';
            $assignmentStatement = oci_parse($databaseConnection, $assignmentSql);
            oci_bind_by_name($assignmentStatement, ':classroom_id', $classroomId);
            oci_execute($assignmentStatement);
            while ($assignment = oci_fetch_assoc($assignmentStatement)) {
                $assignments[] = $assignment['TITLE'];
            }

            oci_free_statement($assignmentStatement);
            oci_free_statement($studentStatement);
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
    <link rel="stylesheet" href="classroom.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<?php if ($error !== ''): ?>
    <h2>Classroom</h2>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <h2><?= htmlspecialchars($classroom['CLASSROOM_NAME'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p>Passcode:
        <?php if ($classroom['PASSCODE'] === null || $classroom['PASSCODE'] === ''): ?>
            <em>None</em>
        <?php else: ?>
            <strong><?= htmlspecialchars($classroom['PASSCODE'], ENT_QUOTES, 'UTF-8') ?></strong>
        <?php endif; ?>
    </p>

    <h3>Students</h3>
    <?php if (count($students) === 0): ?>
        <p>No students yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($students as $student): ?>
                <li><?= htmlspecialchars($student, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Assignments</h3>
    <?php if (count($assignments) === 0): ?>
        <p>No assignments yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($assignments as $assignment): ?>
                <li><?= htmlspecialchars($assignment, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="submit" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
