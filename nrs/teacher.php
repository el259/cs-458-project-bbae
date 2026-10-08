<?php
    session_start();

    // Make sure the user is logged in
    //   else, it sends to signin.php
    require __DIR__ . '/auth.php';
    requireTeacher('dashboard');
    require_once dirname(__DIR__) . '/hum_conn_no_login.php';

    if (!isset($_SESSION['user_id']))
    {
        header('Location: signin.php');
        exit();
    }

    $classrooms = [];

    $classroomConnection = hum_conn_no_login();
    // $classroomSql = '
    //     SELECT
    //         c."CLASSROOM_ID",
    //         c."CLASSROOM_NAME",
    //         TO_CHAR(c."CREATED_AT", \'YYYY-MM-DD\') AS "CREATED_DATE",
    //     FROM "CLASSROOM" c
    //     JOIN "CLASSROOM_MEMBERSHIP" owner_membership
    //       ON owner_membership."CLASSROOM_ID" = c."CLASSROOM_ID"
    //      AND owner_membership."USER_ID" = :creator_id
    //      AND owner_membership."ROLE" = \'teacher\'
    //     ORDER BY "CREATED_DATE"
    // ';
    $classroomSql = '
        SELECT CLASSROOM.CLASSROOM_ID, CLASSROOM.CLASSROOM_NAME
        FROM CLASSROOM, CLASSROOM_MEMBERSHIP
        WHERE CLASSROOM.CLASSROOM_ID = CLASSROOM_MEMBERSHIP.CLASSROOM_ID
            AND CLASSROOM_MEMBERSHIP.USER_ID = :creator_id
            AND CLASSROOM_MEMBERSHIP.ROLE = \'teacher\'
        ORDER BY CLASSROOM.CLASSROOM_NAME
    ';
    $classroomStatement = oci_parse($classroomConnection, $classroomSql);
    $creatorId = $_SESSION['user_id'];
    oci_bind_by_name($classroomStatement, ':creator_id', $creatorId);

    if (oci_execute($classroomStatement))
    {
        while ($classroom = oci_fetch_assoc($classroomStatement))
        {
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
        Last Modified: 9/25/26
        TEACHER.PHP
    -->

    <head>
        <title>HumGlot</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="global.css" />
        <link rel="stylesheet" href="teacher.css" />
        <script src="teacher_page.js"></script>
    </head>
    <body>
        <?php require __DIR__ . '/header.php'; ?>

        <h2>Your classes</h2>

        <form action="viewassignments.php" method="post">
        <?php for($i = 0; $i < count($classrooms); $i++) { ?>
            <div>
                <button type="submit" name="classroomId" value="<?= $classrooms[$i]['id'] ?>"><?= $classrooms[$i]['name'] ?></button>
            </div>
        <?php } ?>
        </form>

        <div class="quiz-actions">

            <form method="get" action="create_classroom.php">
                <input type="submit" value="Make classroom" />
            </form>

            <form method="get" action="404.php">
                <input type="submit" value="Archive classroom" />
            </form>

            <form method="get" action="404.php">
                <input type="submit" value="Delete classroom" />
            </form>

        </div>
        <br />

        <form method="post" action="signin.php">
            <input type="submit" name="logout" value="Sign Out" />
        </form>


        <?php require __DIR__ . '/footer.php'; ?>
    </body>
</html>
