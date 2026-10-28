<?php
session_start();

require_once __DIR__ . '/auth.php';
if (empty($_SESSION['account']) || empty($_SESSION['user_id'])) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/hum_conn_no_login.php';

// Never accept arbitrary filesystem paths.
$name = $_GET['name'] ?? '';

if (!is_string($name) ||
    !preg_match('/\Aq_[a-f0-9]{16}\.(?:jpg|jpeg|png|gif|webp)\z/D', $name)) {
    http_response_code(404);
    exit;
}

$reference = 'private_image.php?name=' . $name;
$authorized = false;

// 1. A teacher can access images referenced by their current draft.
if (!empty($_SESSION['teacher']) &&
    !empty($_SESSION['user_id']) &&
    !empty($_SESSION['quiz']['questions']) &&
    is_array($_SESSION['quiz']['questions'])) {

    foreach ($_SESSION['quiz']['questions'] as $question) {
        if (is_array($question) &&
            ($question['image'] ?? '') === $reference) {
            $authorized = true;
            break;
        }
    }
}

// 2. For saved quizzes, check classroom membership and
//    that the image is actually referenced by a quiz.
if (!$authorized && !empty($_SESSION['user_id'])) {
    $conn = hum_conn_no_login();

    if ($conn) {
        $sql = '
            SELECT 1
            FROM "QUIZ" q
            JOIN "CLASSROOM_MEMBERSHIP" m
              ON m."CLASSROOM_ID" = q."CLASSROOM_ID"
            WHERE m."USER_ID" = :user_id
              AND DBMS_LOB.INSTR(q."QUIZ_JSON", :reference) > 0
              AND ROWNUM = 1
        ';

        $stmt = oci_parse($conn, $sql);
        $userId = (int) $_SESSION['user_id'];

        if ($stmt) {
            oci_bind_by_name($stmt, ':user_id', $userId);
            oci_bind_by_name($stmt, ':reference', $reference);

            if (oci_execute($stmt)) {
                $authorized = oci_fetch_assoc($stmt) !== false;
            } else {
                error_log('Private image authorization query failed');
            }

            oci_free_statement($stmt);
        }

        oci_close($conn);
    }
}

if (!$authorized) {
    http_response_code(404);
    exit;
}

// Files are stored OUTSIDE Apache's document root.
$path = '/var/humglot/private/quiz_images/' . $name;

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

// Verify the actual image format.
$info = @getimagesize($path);

$types = [
    IMAGETYPE_JPEG => 'image/jpeg',
    IMAGETYPE_PNG  => 'image/png',
    IMAGETYPE_GIF  => 'image/gif',
    IMAGETYPE_WEBP => 'image/webp',
];

if ($info === false || !isset($types[$info[2]])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $types[$info[2]]);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($path);
