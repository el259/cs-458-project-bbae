<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$userId = $_SESSION['user_id'] ?? 0;
$classroomId = intval($_SESSION['classroomId'] ?? ($_GET['id'] ?? 0));
$error = '';
$success = '';
$members = [];

if ($classroomId <= 0) {
    $error = 'No classroom selected.';
} else {
    $conn = hum_conn_no_login();

    // Verify ownership
    $verify = oci_parse($conn, '
        SELECT c."CLASSROOM_NAME"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" m ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
        WHERE c."CLASSROOM_ID" = :cid AND m."USER_ID" = :uid AND m."ROLE" = \'teacher\'
    ');
    oci_bind_by_name($verify, ':cid', $classroomId);
    oci_bind_by_name($verify, ':uid', $userId);
    $classroomName = '';
    if (!oci_execute($verify) || !($row = oci_fetch_assoc($verify))) {
        $error = 'Classroom not found or access denied.';
        $classroomId = 0;
    } else {
        $classroomName = $row['CLASSROOM_NAME'];
    }
    oci_free_statement($verify);

    // Add student by username
    if ($error === '' && isset($_POST['addStudent'])) {
        $username = trim($_POST['username'] ?? '');
        if ($username === '') {
            $error = 'Username required.';
        } else {
            $email = strtolower($username) . '@example.com';
            $lookup = oci_parse($conn, 'SELECT "USER_ID" FROM "APP_USER" WHERE "EMAIL" = :email');
            oci_bind_by_name($lookup, ':email', $email);
            oci_execute($lookup);
            $user = oci_fetch_assoc($lookup);
            oci_free_statement($lookup);

            if (!$user) {
                $error = 'User not found.';
            } else {
                $insert = oci_parse($conn, '
                    INSERT INTO "CLASSROOM_MEMBERSHIP" ("CLASSROOM_ID", "USER_ID", "ROLE")
                    VALUES (:cid, :uid, \'student\')
                ');
                oci_bind_by_name($insert, ':cid', $classroomId);
                oci_bind_by_name($insert, ':uid', $user['USER_ID']);
                if (oci_execute($insert, OCI_NO_AUTO_COMMIT)) {
                    oci_commit($conn);
                    $success = 'Student added.';
                } else {
                    oci_rollback($conn);
                    $error = 'Could not add student (already a member?).';
                }
                oci_free_statement($insert);
            }
        }
    }

    // Remove member
    if ($error === '' && isset($_POST['removeMember'])) {
        $memberId = intval($_POST['memberId'] ?? 0);
        if ($memberId > 0) {
            $del = oci_parse($conn, '
                DELETE FROM "CLASSROOM_MEMBERSHIP"
                WHERE "MEMBERSHIP_ID" = :mid AND "CLASSROOM_ID" = :cid
                  AND "ROLE" = \'student\'
            ');
            oci_bind_by_name($del, ':mid', $memberId);
            oci_bind_by_name($del, ':cid', $classroomId);
            if (oci_execute($del, OCI_NO_AUTO_COMMIT)) {
                oci_commit($conn);
                $success = 'Student removed.';
            } else {
                oci_rollback($conn);
                $error = 'Could not remove student.';
            }
            oci_free_statement($del);
        }
    }

    // List members
    if ($classroomId > 0) {
        $list = oci_parse($conn, '
            SELECT m."MEMBERSHIP_ID", u."FIRST_NAME", u."EMAIL", m."ROLE"
            FROM "CLASSROOM_MEMBERSHIP" m
            JOIN "APP_USER" u ON u."USER_ID" = m."USER_ID"
            WHERE m."CLASSROOM_ID" = :cid
            ORDER BY m."ROLE" DESC, u."FIRST_NAME"
        ');
        oci_bind_by_name($list, ':cid', $classroomId);
        if (oci_execute($list)) {
            while ($row = oci_fetch_assoc($list)) {
                $members[] = $row;
            }
        }
        oci_free_statement($list);
    }

    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Roster</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/global.css" />
    <link rel="stylesheet" href="css/teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Manage Roster<?= $classroomName ? ': ' . htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') : '' ?></h1>

<?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($success): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<?php if ($classroomId > 0): ?>
    <h2>Add Student</h2>
    <form method="post">
        <label>Username <input type="text" name="username" required placeholder="student username" /></label>
        <input class="button-primary" type="submit" name="addStudent" value="Add" />
    </form>

    <h2>Members</h2>
    <?php if (count($members) === 0): ?>
        <p>No members yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($members as $m): ?>
                <li>
                    <?= htmlspecialchars($m['FIRST_NAME'], ENT_QUOTES, 'UTF-8') ?>
                    (<?= htmlspecialchars($m['EMAIL'], ENT_QUOTES, 'UTF-8') ?>)
                    — <?= htmlspecialchars($m['ROLE'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($m['ROLE'] === 'student'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="memberId" value="<?= $m['MEMBERSHIP_ID'] ?>" />
                            <input class="button-secondary" type="submit" name="removeMember" value="Remove" />
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<p><a href="teacher.php">Back to dashboard</a></p>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
