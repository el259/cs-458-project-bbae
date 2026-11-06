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
    if($isTeacher) {
        header("Location: teacher.php");
        exit();
    }

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
                    SELECT QUIZ.QUIZ_ID, QUIZ.TITLE, QUIZ.DUE_AT, QUIZ.POINTS_POSSIBLE, COUNT(ATTEMPT_ID) NUM_ATTEMPTS, MAX(SCORE) HIGHEST_SCORE, QUIZ.ATTEMPTS_ALLOWED - COUNT(ATTEMPT_ID) AS ATTEMPTS_LEFT
                    FROM QUIZ
                    LEFT JOIN QUIZ_ATTEMPT ON QUIZ.QUIZ_ID = QUIZ_ATTEMPT.QUIZ_ID
                    WHERE QUIZ.CLASSROOM_ID = :classroom_id
                    GROUP BY QUIZ.QUIZ_ID, QUIZ.TITLE, QUIZ.DUE_AT, QUIZ.POINTS_POSSIBLE, QUIZ.ATTEMPTS_ALLOWED
                    ORDER BY NUM_ATTEMPTS, QUIZ.DUE_AT NULLS LAST, ATTEMPTS_LEFT DESC, HIGHEST_SCORE
                ';
                $assignmentsStatement = oci_parse($databaseConnection, $assignmentsSQL);
                oci_bind_by_name($assignmentsStatement, ':classroom_id', $classroomId);
                if(oci_execute($assignmentsStatement, OCI_DEFAULT)) {
                    while ($assignment = oci_fetch_assoc($assignmentsStatement)) {
                        $assignments[] = [
                            'id' => $assignment['QUIZ_ID'],
                            'title' => $assignment['TITLE'],
                            'due' => $assignment['DUE_AT'],
                            'completed' => $assignment['NUM_ATTEMPTS'] > 0,
                            'score' => $assignment['HIGHEST_SCORE'],
                            'maxScore' => $assignment['POINTS_POSSIBLE'],
                            'attemptsLeft' => $assignment['ATTEMPTS_LEFT']
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

    $completedAssignments = [];
    $incompleteAssignments = [];
    $totalPoints = 0;
    $currentPoints = 0;
    $totalPointsFromCompleted = 0;
    $currentPointsFromCompleted = 0;
    foreach($assignments as $assignment) {
        $total = $assignment['maxScore'];
        $score = $assignment['score'];

        if($assignment['completed']) {
            $completedAssignments[] = $assignment;
            $totalPointsFromCompleted += $total;
            $currentPointsFromCompleted += $score;
        } else {
            $incompleteAssignments[] = $assignment;
        }

        $totalPoints += $total;
        $currentPoints += $score;
    }
?>

<!DOCTYPE html>
    <html lang="en" xmlns="http://www.w3.org/1999/xhtml">

    <!--
        Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
        Last Modified: 11/3/26
        STUDENT.PHP
    -->

    <head>
        <title>HumGlot</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/global.css" />
        <link rel="stylesheet" href="css/teacher.css" />
    </head>
    <body>
        <?php require __DIR__ . '/header.php'; ?>

        <?php if ($error !== ''):
            ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php
            require __DIR__ . '/footer.php';
            exit();
            endif; 
        ?>

        <div class="main">    
            <div class="list-area">
                <?php if($totalPoints > 0) { ?>
                <!-- <h2>Total Points: <?= htmlspecialchars($currentPoints) ?>/<?= htmlspecialchars($totalPoints) ?> (<?= $totalPoints === 0 ? 0 : intval((floatval($currentPoints) / floatval($totalPoints)) * 100)?>%)</h2> -->
                <?php } ?>
                <h2>Incomplete Assignments</h2>
    
                <?php if (count($incompleteAssignments) === 0): ?>
                    <p>None</p>
                <?php else: ?>
                    <form class="quiz-list" action="viewquiz.php" method="post">
                        <?php foreach ($incompleteAssignments as $assignment): 
                            $dueLabel = 'No due date';
                            if (!empty($assignment['due']))
                            {
                                $dueLabel = 'Due on: ';
                                $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $assignment['due']);
                                if ($date instanceof DateTime)
                                {
                                    $dueLabel .= $date->format('F j, Y, g:i A');
                                }
                                else
                                {
                                    $dueLabel .= $assignment['due'];
                                }
                            }
                        ?>
                        <div class="quiz-item">
                            <div class="row">
                                <button type="submit" name="quizId" id="<?= $assignment['id'] ?>" value="<?= $assignment['id'] ?>">
                                    <?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
    
                            <label for="<?= $assignment['id'] ?>"><?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?><br />
                                Attempts Left: <?= htmlspecialchars($assignment['attemptsLeft'] === null ? 'Unlimited' : $assignment['attemptsLeft'], ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>

                <br />
                <h2>Completed Assignments</h2>
                <?php if($totalPointsFromCompleted > 0) { ?>
                <h2>Points: <?= htmlspecialchars($currentPointsFromCompleted) ?>/<?= htmlspecialchars($totalPointsFromCompleted) ?> (<?= $totalPointsFromCompleted === 0 ? 0 : intval((floatval($currentPointsFromCompleted) / floatval($totalPointsFromCompleted)) * 100)?>%)</h2>
                <?php } ?>
    
                <?php if (count($completedAssignments) === 0): ?>
                    <p>None</p>
                <?php else: ?>
                    <form class="quiz-list" action="viewquiz.php" method="post">
                        <?php foreach ($completedAssignments as $assignment): 
                            $dueLabel = 'No due date';
                            if (!empty($assignment['due']))
                            {
                                $dueLabel = 'Due on: ';
                                $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $assignment['due']);
                                if ($date instanceof DateTime)
                                {
                                    $dueLabel .= $date->format('F j, Y, g:i A');
                                }
                                else
                                {
                                    $dueLabel .= $assignment['due'];
                                }
                            }
                        ?>
                        <div class="quiz-item">
                            <div class="row">
                                <button type="submit" name="quizId" id="<?= $assignment['id'] ?>" value="<?= $assignment['id'] ?>">
                                    <?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
    
                            <label for="<?= $assignment['id'] ?>"><?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?><br />
                                Attempts Left: <?= htmlspecialchars($assignment['attemptsLeft'] === null ? 'Unlimited' : $assignment['attemptsLeft'], ENT_QUOTES, 'UTF-8') ?><br />
                                Points: <?= htmlspecialchars($assignment['score'], ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($assignment['maxScore'], ENT_QUOTES, 'UTF-8') ?> (<?= intval((floatval($assignment['score']) / floatval($assignment['maxScore'])) * 100)?>%)</label>
                        </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
    
                <!-- <h3>Drills</h3>
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
                <?php endif; ?> -->

                <form method="post" action="signin.php">
                    <input type="submit" name="logout" value="Sign Out" />
                </form>

                <?php require __DIR__ . '/footer.php'; ?>
            
            </div>  <!-- End of list-area -->


        </div> <!-- End of main container -->
    </body>
    <script src="nav_classroom.js"></script>
</html>
