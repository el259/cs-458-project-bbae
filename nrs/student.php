<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireAccount('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!empty($_SESSION['teacher'])) {
    header("Location: teacher.php");
    exit();
}

$classrooms = [];

$classroomConnection = hum_conn_no_login();
$classroomSql = '
    SELECT CLASSROOM.CLASSROOM_ID, CLASSROOM.CLASSROOM_NAME
    FROM CLASSROOM, CLASSROOM_MEMBERSHIP
    WHERE CLASSROOM.CLASSROOM_ID = CLASSROOM_MEMBERSHIP.CLASSROOM_ID
        AND CLASSROOM_MEMBERSHIP.USER_ID = :student_id
    ORDER BY CLASSROOM.CLASSROOM_NAME
';

$classroomStatement = oci_parse($classroomConnection, $classroomSql);
$studentId = $_SESSION['user_id'];
oci_bind_by_name($classroomStatement, ':student_id', $studentId);

if (oci_execute($classroomStatement, OCI_DEFAULT)) {
    while ($classroom = oci_fetch_assoc($classroomStatement)) {
        $classrooms[] = [
            'id' => $classroom['CLASSROOM_ID'],
            'name' => $classroom['CLASSROOM_NAME']
        ];
    }
}

oci_free_statement($classroomStatement);
oci_close($classroomConnection);

?>


<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 10/7/26
    STUDENT.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="student.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

    <h2><?= htmlspecialchars('Welcome, ' . $_SESSION['account'] . '!', ENT_QUOTES, 'UTF-8') ?></h2>


    <h3>Your classes</h3>

    <form action="viewassignments.php" method="post">
<?php
    for($i = 0; $i < count($classrooms); $i++) {
?>
        <div>
            <button type="submit" name="classroomId" value="<?= $classrooms[$i]['id'] ?>"><?= $classrooms[$i]['name'] ?></button>
        </div>
<?php
    }
?>
    </form>

    <form method="post" action="signin.php">
        <input type="submit" name="logout" value="Sign Out" />
    </form>

    <?php require __DIR__ . '/footer.php'; ?>

    </body>
</html>
