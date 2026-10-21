<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$classroomId = intval($_GET['id'] ?? 0);
$classroom = null;
$students = [];
$assignments = [];
$textbooks = [];
$drills = [];
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
            // Students
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
            oci_free_statement($studentStatement);

            // Quizzes (existing)
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

            // Assigned textbooks
            $tbSql = '
                SELECT t."TITLE"
                FROM "TEXTBOOK" t
                JOIN "CLASSROOM_TEXTBOOK" ct ON ct."TEXTBOOK_ID" = t."TEXTBOOK_ID"
                WHERE ct."CLASSROOM_ID" = :classroom_id
                ORDER BY t."TITLE"
            ';
            $tbStmt = oci_parse($databaseConnection, $tbSql);
            oci_bind_by_name($tbStmt, ':classroom_id', $classroomId);
            if (oci_execute($tbStmt)) {
                while ($row = oci_fetch_assoc($tbStmt)) {
                    $textbooks[] = $row['TITLE'];
                }
            }
            oci_free_statement($tbStmt);

            // Assigned drills
            $drillSql = '
                SELECT d."TITLE"
                FROM "DRILL" d
                JOIN "CLASSROOM_DRILL" cd ON cd."DRILL_ID" = d."DRILL_ID"
                WHERE cd."CLASSROOM_ID" = :classroom_id
                ORDER BY d."TITLE"
            ';
            $drillStmt = oci_parse($databaseConnection, $drillSql);
            oci_bind_by_name($drillStmt, ':classroom_id', $classroomId);
            if (oci_execute($drillStmt)) {
                while ($row = oci_fetch_assoc($drillStmt)) {
                    $drills[] = $row['TITLE'];
                }
            }
            oci_free_statement($drillStmt);
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

    <h3>Quizzes</h3>
    <?php if (count($assignments) === 0): ?>
        <p>No quizzes yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($assignments as $assignment): ?>
                <li><?= htmlspecialchars($assignment, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Assigned Textbooks</h3>
    <?php if (count($textbooks) === 0): ?>
        <p>None.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($textbooks as $tb): ?>
                <li><?= htmlspecialchars($tb, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Assigned Drills</h3>
    <?php if (count($drills) === 0): ?>
        <p>None.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($drills as $d): ?>
                <li><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="assign_content.php?classroom_id=<?= $classroomId ?>">Assign textbook or drill</a></p>
<?php endif; ?>

<form method="get" action="teacher.php">
    <input type="submit" name="goback" value="Go Back" />
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
