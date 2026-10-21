<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$error = '';
$quizId = intval($_POST['quizId'] ?? $_SESSION['quizId'] ?? 0);
$showScore = false;
$quizData = [];

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();
    $sql = '
        SELECT "TITLE", "DUE_AT", "POINTS_POSSIBLE", "ATTEMPTS_ALLOWED"
        FROM "QUIZ"
        WHERE "QUIZ_ID" = :quiz_id
        ORDER BY "DUE_AT"
    ';
    $statement = oci_parse($databaseConnection, $sql);
    oci_bind_by_name($statement, ':quiz_id', $quizId);

    if (!oci_execute($statement)) {
        $error = 'Unable to load the quiz.';
    } else {
        $quiz = oci_fetch_assoc($statement);

        if ($quiz === false) {
            $error = 'Quiz not found.';
        } else {
            $quizData = [
                'title' => $quiz['TITLE'],
                'dueAt' => $quiz['DUE_AT'],
                'maxPoints' => $quiz['POINTS_POSSIBLE'],
                'maxAttempts' => $quiz['ATTEMPTS_ALLOWED']
            ];
        }
    }

    oci_free_statement($statement);
    oci_close($databaseConnection);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="quiz.css" />
    <style>
        .quiz-with-pdf { display: flex; gap: 1rem; height: calc(100vh - 140px); }
        .pdf-panel { flex: 1; display: flex; flex-direction: column; border: 1px solid #ccc; min-width: 280px; }
        .pdf-panel.hidden { display: none; }
        .pdf-header { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0.6rem; background: #f5f5f5; border-bottom: 1px solid #ccc; font-size: 0.9rem; }
        .toggle-btn { padding: 0.2rem 0.6rem; cursor: pointer; }
        .questions-panel { flex: 1; overflow-y: auto; }
        #pdfFrame { flex: 1; border: none; }
    </style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<form method="get" action="viewassignments.php">
    <input type="submit" value="Go Back" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($quizData) === 0): ?>
    <p>Error loading quiz. Try again.</p>
<?php else: 
        $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $quizData['dueAt']);
        if ($date instanceof DateTime) {
            $dueLabel = $date->format('F j, Y, g:i A');
        } else {
            $dueLabel = $quizData['due_at'];
        } ?>
    <h1><?= htmlspecialchars($quizData['title']) ?></h1>
    <p><strong>Due on:</strong> <?= htmlspecialchars($dueLabel) ?></p>
    <p><strong>Points:</strong> 0/<?= htmlspecialchars($quizData['maxPoints']) ?> (0%)</p>
    <p><strong>Attempts left:</strong> <?= htmlspecialchars($quizData['maxAttempts']) ?></p>

    <div class="<?= $pdfUrl !== '' ? 'quiz-with-pdf' : '' ?>">
        <div class="questions-panel">
            <form method="post" action="quiz.php">
                <input type="submit" value="Start quiz" />
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
