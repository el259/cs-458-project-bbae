<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

if (!isset($_SESSION['classroom'])) {
    $_SESSION['classroom'] = [
        'name' => '',
        'passcode' => '',
        'students' => []
    ];
}

$error = '';

if (isset($_POST['setClassroomName'])) {
    $classroomName = trim($_POST['classroomName'] ?? '');
    if ($classroomName === '') {
        $error = 'Please enter a classroom name.';
    } else {
        $_SESSION['classroom']['name'] = $classroomName;
        header('Location: create_classroom.php');
        exit();
    }
}

if (isset($_POST['setPasscode'])) {
    $_SESSION['classroom']['passcode'] = trim($_POST['passcode'] ?? '');
    header('Location: create_classroom.php');
    exit();
}

if (isset($_POST['addStudent'])) {
    $student = trim($_POST['student'] ?? '');
    if ($student === '') {
        $error = 'Please enter a student name.';
    } else {
        foreach ($_SESSION['classroom']['students'] as $existingStudent) {
            if (strcasecmp($existingStudent, $student) === 0) {
                $error = 'That student is already in this classroom.';
                break;
            }
        }

        if ($error === '') {
            $_SESSION['classroom']['students'][] = $student;
            header('Location: create_classroom.php');
            exit();
        }
    }
}

if (isset($_POST['removeStudent'])) {
    $removeIndex = intval($_POST['removeIndex'] ?? -1);
    if (isset($_SESSION['classroom']['students'][$removeIndex])) {
        array_splice($_SESSION['classroom']['students'], $removeIndex, 1);
    }
    header('Location: create_classroom.php');
    exit();
}

if (isset($_POST['createClassroom'])) {
    $classroomName = trim($_SESSION['classroom']['name']);
    $passcode = trim($_SESSION['classroom']['passcode']);
    $students = $_SESSION['classroom']['students'];

    if ($classroomName === '' || ($passcode === '' && count($students) === 0)) {
        $error = 'You must give the classroom a name and add at least one student or a passcode.';
    } else {
        $databaseConnection = hum_conn_no_login();
        $creatorId = $_SESSION['user_id'];
        $checkSql = '
            SELECT c."CLASSROOM_ID"
            FROM "CLASSROOM" c
            JOIN "CLASSROOM_MEMBERSHIP" m
              ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
            WHERE c."CLASSROOM_NAME" = :classroom_name
              AND m."USER_ID" = :creator_id
              AND m."ROLE" = \'teacher\'
        ';
        $checkStatement = oci_parse($databaseConnection, $checkSql);
        oci_bind_by_name($checkStatement, ':classroom_name', $classroomName);
        oci_bind_by_name($checkStatement, ':creator_id', $creatorId);

        if (!oci_execute($checkStatement)) {
            error_log('Classroom duplicate check failed: ' . oci_error($checkStatement)['message']);
            $error = 'Unable to check for an existing classroom.';
        } elseif (oci_fetch_assoc($checkStatement) !== false) {
            $error = 'A classroom with that name already exists.';
        } else {
            $insertSql = '
                INSERT INTO "CLASSROOM" (
                    "CLASSROOM_NAME", "DESCRIPTION", "PASSCODE"
                ) VALUES (:classroom_name, NULL, :passcode)
                RETURNING "CLASSROOM_ID" INTO :classroom_id
            ';
            $insertStatement = oci_parse($databaseConnection, $insertSql);
            $classroomId = 0;
            oci_bind_by_name($insertStatement, ':classroom_name', $classroomName);
            oci_bind_by_name($insertStatement, ':passcode', $passcode);
            oci_bind_by_name($insertStatement, ':classroom_id', $classroomId, 32);

            if (!oci_execute($insertStatement, OCI_NO_AUTO_COMMIT)) {
                error_log('Classroom insert failed: ' . oci_error($insertStatement)['message']);
                oci_rollback($databaseConnection);
                $error = 'Unable to create the classroom.';
            } else {
                $membershipSql = '
                    INSERT INTO "CLASSROOM_MEMBERSHIP" (
                        "CLASSROOM_ID", "USER_ID", "ROLE"
                    ) VALUES (:classroom_id, :user_id, :role)
                ';
                $membershipStatement = oci_parse($databaseConnection, $membershipSql);
                $teacherRole = 'teacher';
                oci_bind_by_name($membershipStatement, ':classroom_id', $classroomId);
                oci_bind_by_name($membershipStatement, ':user_id', $creatorId);
                oci_bind_by_name($membershipStatement, ':role', $teacherRole);

                if (!oci_execute($membershipStatement, OCI_NO_AUTO_COMMIT)) {
                    error_log('Classroom owner membership insert failed: ' . oci_error($membershipStatement)['message']);
                    $error = 'Unable to add the classroom owner.';
                } else {
                    $studentSql = '
                        SELECT "USER_ID"
                        FROM "APP_USER"
                        WHERE "EMAIL" = :email
                    ';
                    $studentStatement = oci_parse($databaseConnection, $studentSql);
                    $studentMembershipStatement = oci_parse($databaseConnection, $membershipSql);
                    $studentRole = 'student';

                    foreach ($students as $student) {
                        $studentEmail = strtolower(trim($student)) . '@example.com';
                        oci_bind_by_name($studentStatement, ':email', $studentEmail);

                        if (!oci_execute($studentStatement)) {
                            error_log('Student lookup failed: ' . oci_error($studentStatement)['message']);
                            $error = 'Unable to find student account: ' . $student;
                            break;
                        }

                        $studentRecord = oci_fetch_assoc($studentStatement);
                        if ($studentRecord === false) {
                            $error = 'Student account not found: ' . $student;
                            break;
                        }

                        oci_bind_by_name($studentMembershipStatement, ':classroom_id', $classroomId);
                        oci_bind_by_name($studentMembershipStatement, ':user_id', $studentRecord['USER_ID']);
                        oci_bind_by_name($studentMembershipStatement, ':role', $studentRole);

                        if (!oci_execute($studentMembershipStatement, OCI_NO_AUTO_COMMIT)) {
                            error_log('Student membership insert failed: ' . oci_error($studentMembershipStatement)['message']);
                            $error = 'Unable to add student: ' . $student;
                            break;
                        }
                    }

                    if ($error === '') {
                        oci_commit($databaseConnection);
                        unset($_SESSION['classroom']);
                        oci_free_statement($studentMembershipStatement);
                        oci_free_statement($studentStatement);
                        oci_free_statement($membershipStatement);
                        oci_free_statement($insertStatement);
                        oci_free_statement($checkStatement);
                        oci_close($databaseConnection);
                        header('Location: teacher.php');
                        exit();
                    }

                    oci_rollback($databaseConnection);
                    oci_free_statement($studentMembershipStatement);
                    oci_free_statement($studentStatement);
                }

                oci_free_statement($membershipStatement);
            }

            oci_free_statement($insertStatement);
        }

        oci_free_statement($checkStatement);
        oci_close($databaseConnection);
    }
}

if (isset($_POST['cancelClassroom'])) {
    unset($_SESSION['classroom']);
    header('Location: teacher.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create a Classroom</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="create_classroom.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Create a Classroom</h1>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="create_classroom.php">
    <input type="submit" name="cancelClassroom" value="Cancel" />
</form>

<?php if ($_SESSION['classroom']['name'] === ''): ?>
    <h2>Name Your Classroom</h2>
    <form method="post" action="create_classroom.php">
        <label for="classroomName">Classroom Name</label>
        <input type="text" name="classroomName" id="classroomName" required="required" />
        <input type="submit" name="setClassroomName" value="Continue" />
    </form>
<?php else: ?>
    <h2><?= htmlspecialchars($_SESSION['classroom']['name'], ENT_QUOTES, 'UTF-8') ?></h2>

    <h3>Student Passcode</h3>
    <form method="post" action="create_classroom.php">
        <label for="passcode">Passcode</label>
        <input type="text" name="passcode" id="passcode" value="<?= htmlspecialchars($_SESSION['classroom']['passcode'], ENT_QUOTES, 'UTF-8') ?>" />
        <input type="submit" name="setPasscode" value="Save Passcode" />
    </form>

    <?php if ($_SESSION['classroom']['passcode'] !== ''): ?>
        <p>Current passcode: <strong><?= htmlspecialchars($_SESSION['classroom']['passcode'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <?php if (count($_SESSION['classroom']['students']) > 0): ?>
        <h3>Students</h3>
        <?php foreach ($_SESSION['classroom']['students'] as $index => $student): ?>
            <div>
                <p><strong><?= $index + 1 ?>.</strong> <?= htmlspecialchars($student, ENT_QUOTES, 'UTF-8') ?></p>
                <form method="post" action="create_classroom.php">
                    <input type="hidden" name="removeIndex" value="<?= $index ?>" />
                    <input type="submit" name="removeStudent" value="Remove" />
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Add a Student</h3>
    <form method="post" action="create_classroom.php">
        <label for="student">Student username</label>
        <input type="text" name="student" id="student" required="required" />
        <input type="submit" name="addStudent" value="Add Student" />
    </form>

    <?php if ($_SESSION['classroom']['passcode'] !== '' || count($_SESSION['classroom']['students']) > 0): ?>
        <form method="post" action="create_classroom.php">
            <input type="submit" name="createClassroom" value="Create Classroom" />
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
