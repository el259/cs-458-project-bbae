<?php
    session_start();
    
    $logo_href = "index.php";
    if (!empty($_SESSION['teacher'])) {
        $logo_href = "teacher.php";
    } else {
        $logo_href = "student.php";
    }

    // Get the classrooms
    $classroomId = intval($_SESSION['classroomId'] ?? -1);
    $classrooms = [];
    $currentClassroomName = '';
    $uesrId = $_SESSION['user_id'];
    $classroomConnection = hum_conn_no_login();
    
    $classroomSql = '
        SELECT CLASSROOM.CLASSROOM_ID, CLASSROOM.CLASSROOM_NAME
        FROM CLASSROOM, CLASSROOM_MEMBERSHIP
        WHERE CLASSROOM.CLASSROOM_ID = CLASSROOM_MEMBERSHIP.CLASSROOM_ID
            AND CLASSROOM_MEMBERSHIP.USER_ID = :user_id
            AND CLASSROOM_MEMBERSHIP.ROLE = \'teacher\'
            AND CLASSROOM.IS_ACTIVE = \'Y\'
        ORDER BY CLASSROOM.CLASSROOM_NAME
    ';
    $classroomStatement = oci_parse($classroomConnection, $classroomSql);
    oci_bind_by_name($classroomStatement, ':user_id', $uesrId);

    if (oci_execute($classroomStatement))
    {
        while ($classroom = oci_fetch_assoc($classroomStatement))
        {
            $classrooms[] = [
                'id' => $classroom['CLASSROOM_ID'],
                'name' => $classroom['CLASSROOM_NAME']
            ];
            if (intval($classroom['CLASSROOM_ID']) === $classroomId) {
                $currentClassroomName = $classroom['CLASSROOM_NAME'];
            }
        }
    }

    oci_free_statement($classroomStatement);
    oci_close($classroomConnection);
?>

<div class="site-header">
    <a class="logo" href="<?= $logo_href ?>">
        <p>HumGlot</p>
    </a>
    <button class="classroom-button" onclick="toggleClassroomPopup()">
        <?= htmlspecialchars($currentClassroomName, ENT_QUOTES, 'UTF-8') ?>
        <svg width="800px" height="800px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <g>
                <path fill="none" d="M0 0h24v24H0z"/>
                <path d="M12 15l-4.243-4.243 1.415-1.414L12 12.172l2.828-2.829 1.415 1.414z"/>
            </g>
        </svg>
    </button>

    <div class="right-side">
        <?php
        if(!empty($_SESSION['account']))
        {
        ?>
            <div class="account">
                <p class="account-name"><?= htmlspecialchars($_SESSION['account'], ENT_QUOTES, 'UTF-8') ?></p>
                <span></span>
            </div>
        <?php
        }
        ?>
    </div>

    <div class="classroom-popup" aria-hidden="true">
        <div class="background"></div>
        <div class="content">
            <div class="popup-header">
                <h2>Switch Classrooms</h2>
                <button class="close-classroom-popup" onclick="closeClassroomPopup()">
                    X
                </button>
            </div>

            <!-- Classroom list -->
            <form action="teacher.php" method="post">
            <?php for($i = 0; $i < count($classrooms); $i++) { ?>
                <div>
                    <button type="submit" class="classroom-link" name="classroomId" value="<?= $classrooms[$i]['id'] ?>"><?= $classrooms[$i]['name'] ?></button>
                </div>
            <?php } ?>
            </form>

            <!-- Classroom button options -->
            <div class="quiz-actions">
                <form method="get" action="create_classroom.php">
                    <input type="submit" value="Make classroom" />
                </form>

                <form method="get" action="archive_classrooms.php">
                    <input type="submit" value="Archive classroom" />
                </form>

                <form method="get" action="delete_classrooms.php">
                    <input type="submit" value="Delete classroom" />
                </form>
            </div>
        </div>
    </div>
</div>