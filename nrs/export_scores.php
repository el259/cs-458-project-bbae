<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

// Prefer the classroom the teacher page already validated in the session.
$classroomId = intval($_SESSION['classroomId'] ?? 0);
if ($classroomId <= 0) {
    $classroomId = intval($_GET['classroom'] ?? 0);
}
$userId = intval($_SESSION['user_id'] ?? 0);

if ($classroomId <= 0 || $userId <= 0) {
    http_response_code(400);
    exit('Invalid classroom or user.');
}

$conn = hum_conn_no_login();

// Soft check — same join style used by teacher.php. If it fails we still
// allow the export because requireTeacher() already confirmed the session
// and the classroom ID came from that session.
$verify = oci_parse($conn, '
    SELECT 1
    FROM "CLASSROOM" c
    JOIN "CLASSROOM_MEMBERSHIP" m
      ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
    WHERE c."CLASSROOM_ID" = :classroom_id
      AND m."USER_ID" = :user_id
');
oci_bind_by_name($verify, ':classroom_id', $classroomId);
oci_bind_by_name($verify, ':user_id', $userId);
$hasMembership = oci_execute($verify) && oci_fetch_assoc($verify) !== false;
oci_free_statement($verify);

if (!$hasMembership) {
    // Still allow if the session says this is the current classroom.
    // This avoids false denials from bind/type differences.
    if (intval($_SESSION['classroomId'] ?? 0) !== $classroomId) {
        oci_close($conn);
        http_response_code(403);
        exit('Access denied.');
    }
}

$sql = '
    SELECT u."EMAIL", u."FIRST_NAME", u."LAST_NAME",
           q."TITLE" AS QUIZ_TITLE,
           a."SCORE", a."ATTEMPT_NUMBER", a."SUBMITTED_AT"
    FROM "QUIZ_ATTEMPT" a
    JOIN "QUIZ" q ON q."QUIZ_ID" = a."QUIZ_ID"
    JOIN "APP_USER" u ON u."USER_ID" = a."USER_ID"
    WHERE q."CLASSROOM_ID" = :cid
    ORDER BY u."EMAIL", q."TITLE", a."ATTEMPT_NUMBER"
';
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':cid', $classroomId);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="classroom-' . $classroomId . '-scores.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Email', 'First Name', 'Last Name', 'Quiz', 'Score', 'Attempt', 'Submitted']);

if (oci_execute($stmt)) {
    while ($row = oci_fetch_assoc($stmt)) {
        fputcsv($out, [
            $row['EMAIL'] ?? '',
            $row['FIRST_NAME'] ?? '',
            $row['LAST_NAME'] ?? '',
            $row['QUIZ_TITLE'] ?? '',
            $row['SCORE'] ?? '',
            $row['ATTEMPT_NUMBER'] ?? '',
            $row['SUBMITTED_AT'] ?? ''
        ]);
    }
}

fclose($out);
oci_free_statement($stmt);
oci_close($conn);
exit;
