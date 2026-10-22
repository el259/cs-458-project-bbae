<?php
    session_start();
    require __DIR__ . '/auth.php';
    requireAccount('dashboard');
    require_once dirname(__DIR__) . '/hum_conn_no_login.php';

    // Must run before the session value is read below (header.php is included too late)
    if(isset($_POST['classroomId'])) {
        $_SESSION['classroomId'] = intval($_POST['classroomId']);
    }

    // No classroom selected yet: default to the user's first one
    if(!isset($_SESSION['classroomId'])) {
        $firstConnection = hum_conn_no_login();
        $firstStatement = oci_parse($firstConnection, '
            SELECT MIN("CLASSROOM_ID") AS "CLASSROOM_ID"
            FROM "CLASSROOM_MEMBERSHIP"
            WHERE "USER_ID" = :user_id
        ');
        $firstUserId = $_SESSION['user_id'];
        oci_bind_by_name($firstStatement, ':user_id', $firstUserId);
        if (oci_execute($firstStatement)) {
            $firstRow = oci_fetch_assoc($firstStatement);
            if ($firstRow && $firstRow['CLASSROOM_ID'] !== null) {
                $_SESSION['classroomId'] = intval($firstRow['CLASSROOM_ID']);
            }
        }
        oci_free_statement($firstStatement);
        oci_close($firstConnection);
    }

    $classroomId = intval($_SESSION['classroomId'] ?? -1);
    $classroom = null;
    $assignments = [];
    $drills = [];
    $error = '';
    $isTeacher = !empty($_SESSION['teacher']);

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
                // Quizzes
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

                // Assigned drills
                $drillSql = '
                    SELECT d."DRILL_ID", d."TITLE"
                    FROM "DRILL" d
                    JOIN "CLASSROOM_DRILL" cd ON cd."DRILL_ID" = d."DRILL_ID"
                    WHERE cd."CLASSROOM_ID" = :classroom_id
                    ORDER BY d."TITLE"
                ';
                $drillStmt = oci_parse($databaseConnection, $drillSql);
                oci_bind_by_name($drillStmt, ':classroom_id', $classroomId);
                if (oci_execute($drillStmt)) {
                    while ($d = oci_fetch_assoc($drillStmt)) {
                        $drills[] = $d;
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
    </head>
    <body>
        <?php require __DIR__ . '/header.php'; ?>

        <?php if ($error !== ''):
            require __DIR__ . '/header.php';
            ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php
            require __DIR__ . '/footer.php';
            exit();
            endif; 
        ?>

        <div class="main">
            <div class="classroom-info">
                <h2>Classroom Information</h2>
                <p><a href="manage_textbooks.php">Manage Textbooks</a></p>
                <p><a href="manage_items.php">Free-floating Items</a></p>
                <p><a href="manage_drills.php">Manage Drills</a></p>

                <form method="post" action="signin.php">
                    <input type="submit" name="logout" value="Sign Out" />
                </form>
            </div>
    
            <div class="list-area">
                <h2>Quizzes</h2>
    
                <?php if (count($assignments) === 0): ?>
                    <p>No quizzes yet.</p>
                <?php else: ?>
                    <form class="quiz-list" action="viewquiz.php" method="post">
                        <?php foreach ($assignments as $assignment): 
                            $dueLabel = 'No due date';
                            if (!empty($assignment['due']))
                            {
                                $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $assignment['due']);
                                if ($date instanceof DateTime)
                                {
                                    $dueLabel = $date->format('F j, Y, g:i A');
                                }
                                else
                                {
                                    $dueLabel = $assignment['due'];
                                }
                            }
                        ?>
                        <div class="quiz-item">
                            <div class="row">
                                <h3>
                                    <?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <button type="submit" class="edit" name="quizId" id="<?= $assignment['id'] ?>" value="<?= $assignment['id'] ?>">
                                    <svg width="32px" height="32px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.0671 2.27157C17.5 2.09228 17.9639 2 18.4324 2C18.9009 2 19.3648 2.09228 19.7977 2.27157C20.2305 2.45086 20.6238 2.71365 20.9551 3.04493C21.2864 3.37621 21.5492 3.7695 21.7285 4.20235C21.9077 4.63519 22 5.09911 22 5.56761C22 6.03611 21.9077 6.50003 21.7285 6.93288C21.5492 7.36572 21.2864 7.75901 20.9551 8.09029L20.4369 8.60845L15.3916 3.56308L15.9097 3.04493C16.241 2.71365 16.6343 2.45086 17.0671 2.27157Z" fill="#000000"/>
                                        <path d="M13.9774 4.9773L3.6546 15.3001C3.53154 15.4231 3.44273 15.5762 3.39694 15.7441L2.03526 20.7369C1.94084 21.0831 2.03917 21.4534 2.29292 21.7071C2.54667 21.9609 2.91693 22.0592 3.26314 21.9648L8.25597 20.6031C8.42387 20.5573 8.57691 20.4685 8.69996 20.3454L19.0227 10.0227L13.9774 4.9773Z" fill="#000000"/>
                                    </svg>
                                </button>
                            </div>
    
                            <p for="<?= $assignment['id'] ?>">Due on: <?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
    
                <div class="quiz-actions">
                    <form method="get" action="create_quiz.php">
                        <input type="hidden" name="id" value="<?= $classroomId ?>" />
                        <input type="submit" value="Make quiz" />
                    </form> 
                    <form method="get" action="edit_quiz.php">
                        <input type="hidden" name="id" value="<?= $classroomId ?>" />
                        <input type="submit" value="Edit quiz" />
                    </form>
                    <form method="get" action="delete_quiz.php">
                        <input type="hidden" name="id" value="<?= $classroomId ?>" />
                        <input type="submit" value="Delete quiz" />
                    </form>
                    <form method="get" action="assign_content.php">
                        <input type="hidden" name="classroom_id" value="<?= $classroomId ?>" />
                        <input type="submit" value="Assign textbook / drill" />
                    </form>
                </div>
    
                <h3>Drills</h3>
                <?php if (count($drills) === 0): ?>
                    <p>No drills assigned yet.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($drills as $d): ?>
                            <li>
                                <a href="drill.php?id=<?= $d['DRILL_ID'] ?>&classroom=<?= $classroomId ?>">
                                    <?= htmlspecialchars($d['TITLE'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><a href="list_drills.php?id=<?= $classroomId ?>">View all drills</a></p>

                <?php require __DIR__ . '/footer.php'; ?>
            
            </div>  <!-- End of list-area -->

        </div> <!-- End of main container -->
    </body>
    <script src="nav_classroom.js"></script>
</html>
