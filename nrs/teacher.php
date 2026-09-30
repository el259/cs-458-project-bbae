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



    $quizzes = [];
    $databaseConnection = hum_conn_no_login();
        $quizSql = '
            SELECT
                "QUIZ_ID",
                "TITLE",
                TO_CHAR("CREATED_AT", \'YYYY-MM-DD\') AS "CREATED_DATE"
            FROM "QUIZ"
            WHERE "CREATOR_ID" = :creator_id
            ORDER BY "TITLE"
        ';
        $quizStatement = oci_parse($databaseConnection, $quizSql);
        $creatorId = $_SESSION['user_id'];
        oci_bind_by_name($quizStatement, ':creator_id', $creatorId);

        if (oci_execute($quizStatement))
        {
            while ($quiz = oci_fetch_assoc($quizStatement))
            {
                $quizzes[] = [
                    'name' => $quiz['TITLE'],
                    'created' => $quiz['CREATED_DATE'],
                    'edited' => $quiz['CREATED_DATE']
                ];
            }
        }

        oci_free_statement($quizStatement);
        oci_close($databaseConnection);



    $classrooms = [];

    $classroomConnection = hum_conn_no_login();
    $classroomSql = '
        SELECT
            c."CLASSROOM_ID",
            c."CLASSROOM_NAME",
            c."PASSCODE",
            TO_CHAR(c."CREATED_AT", \'YYYY-MM-DD\') AS "CREATED_DATE",
            COUNT(student_membership."USER_ID") AS "STUDENT_COUNT"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" owner_membership
          ON owner_membership."CLASSROOM_ID" = c."CLASSROOM_ID"
         AND owner_membership."USER_ID" = :creator_id
         AND owner_membership."ROLE" = \'teacher\'
        LEFT JOIN "CLASSROOM_MEMBERSHIP" student_membership
          ON student_membership."CLASSROOM_ID" = c."CLASSROOM_ID"
         AND student_membership."ROLE" = \'student\'
        GROUP BY
            c."CLASSROOM_ID",
            c."CLASSROOM_NAME",
            c."PASSCODE",
            c."CREATED_AT"
        ORDER BY c."CLASSROOM_NAME"
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
                'name' => $classroom['CLASSROOM_NAME'],
                'students' => $classroom['STUDENT_COUNT'],
                'passcode' => $classroom['PASSCODE'] !== null &&
                    $classroom['PASSCODE'] !== '',
                'created' => $classroom['CREATED_DATE'],
                'edited' => $classroom['CREATED_DATE']
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
        <link rel="stylesheet" href="global.css" />
        <link rel="stylesheet" href="teacher.css" />
    </head>
    <body>
        <?php require __DIR__ . '/header.php'; ?>

        <h2>Your classrooms</h2>



        <?php
        if(count($classrooms) <= 0)
        {
        ?>

        <p>No classrooms yet.</p>
        
        <?php
        }
        else
        {
        ?>

        <table>
            <thead>
            <tr>
                <th>Classroom name</th>
                    <th>Students</th>
                    <th>Passcode</th>
                    <th>Date created</th>
                    <th>Last edited</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach($classrooms as $classroom)
                    {
            ?>
                <tr>
                    <td><?= htmlspecialchars($classroom['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$classroom['students'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $classroom['passcode'] ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($classroom['created'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($classroom['edited'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="get" action="classroom.php">
                            <?php if (isset($classroom['id'])): ?>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($classroom['id'], ENT_QUOTES, 'UTF-8') ?>" />
                            <?php else: ?>
                                <input type="hidden" name="slug" value="<?= htmlspecialchars($classroom['slug'], ENT_QUOTES, 'UTF-8') ?>" />
                            <?php endif; ?>
                            <input type="submit" value="View" />
                        </form>
                    </td>
                </tr>
            <?php
                    }
            ?>
            </tbody>
        </table>
        <?php
        }
        ?>

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

        <h2>Your quizzes</h2>

        <?php
        if(count($quizzes) <= 0)
        {
        ?>
            <p>No quizzes yet.</p>
        <?php
        }
        else
        {
        ?>
        <table>
            <thead>
            <tr>
                <th>Quiz name</th>
                <th>Date created</th>
                <th>Last edited</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach($quizzes as $quiz)
                {
            ?>
                <tr>
                    <td><?= htmlspecialchars($quiz['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($quiz['created'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($quiz['edited'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php
                }
            ?>
            </tbody>
            </table>
        <?php
        }
        ?>


    <div class="quiz-actions">
        <form method="get" action="create.php">
            <input type="submit" value="Create a quiz" />
        </form>

        <form method="get" action="take.php">
            <input type="submit" value="Take a quiz" />
        </form>
    </div>


        <form method="post" action="signin.php">
            <input type="submit" name="logout" value="Sign Out" />
        </form>


    <?php require __DIR__ . '/footer.php'; ?>
    </body>
</html>
