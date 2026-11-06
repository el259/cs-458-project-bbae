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
$userId = $_SESSION['user_id'];
$attemptData = [];
$attemptsLeft = null;

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();

    $quizSql = '
        SELECT "TITLE", "DUE_AT", "POINTS_POSSIBLE", "ATTEMPTS_ALLOWED"
        FROM "QUIZ"
        WHERE "QUIZ_ID" = :quiz_id
        ORDER BY "DUE_AT"
    ';
    $quizStatement = oci_parse($databaseConnection, $quizSql);
    oci_bind_by_name($quizStatement, ':quiz_id', $quizId);

    if (!oci_execute($quizStatement)) {
        $error = 'Unable to load the quiz.';
    } else {
        $quiz = oci_fetch_assoc($quizStatement);

        if ($quiz === false) {
            $error = 'Quiz not found.';
        } else {
            $quizData = [
                'title' => $quiz['TITLE'],
                'dueAt' => $quiz['DUE_AT'],
                'maxPoints' => $quiz['POINTS_POSSIBLE'],
                'maxAttempts' => $quiz['ATTEMPTS_ALLOWED']
            ];

            $attemptsLeft = $quizData['maxAttempts'];
        }
    }
    oci_free_statement($quizStatement);

    if($error === '') {
        // can combine this and previous query into one using group by, see line 70 of 'student.php'
        $checkSql = '
            SELECT COUNT(*) PREVIOUS_ATTEMPTS, MAX(SCORE) HIGHEST_SCORE
            FROM QUIZ_ATTEMPT
            WHERE QUIZ_ID = :quiz_id
                and USER_ID = :user_id
        ';
        $checkStatement = oci_parse($databaseConnection, $checkSql);
        oci_bind_by_name($checkStatement, ':quiz_id', $quizId);
        oci_bind_by_name($checkStatement, ':user_id', $userId);

        if(!oci_execute($checkStatement)) {
            $error = 'Unable to count previous attempts.';
        } else {
            $check = oci_fetch_assoc($checkStatement);

            $quizData['attemptsUsed'] = $check['PREVIOUS_ATTEMPTS'];
            $quizData['highestScore'] = $check['HIGHEST_SCORE'];

            if($attemptsLeft !== null) {
                $attemptsLeft -= $quizData['attemptsUsed'];
            }
        }
        oci_free_statement($checkStatement);
    }
    
    if($error === '' && $quizData['attemptsUsed'] !== null && $quizData['attemptsUsed'] > 0) {
        $attemptSql = '
            SELECT ATTEMPT_ID, SCORE, ATTEMPT_NUMBER, SUBMITTED_AT
            FROM QUIZ_ATTEMPT
            WHERE QUIZ_ID = :quiz_id
                and USER_ID = :user_id
            ORDER BY SUBMITTED_AT DESC
        ';
        $attemptStatement = oci_parse($databaseConnection, $attemptSql);
        oci_bind_by_name($attemptStatement, ':quiz_id', $quizId);
        oci_bind_by_name($attemptStatement, ':user_id', $userId);

        if (!oci_execute($attemptStatement)) {
            $error = 'Unable to load previous attempts.';
        } else {
            while ($data = oci_fetch_assoc($attemptStatement)) {
                $attemptData[] = [
                    'score' => $data['SCORE'],
                    'attemptNumber' => $data['ATTEMPT_NUMBER'],
                    'submittedAt' => $data['SUBMITTED_AT'],
                    'attemptId' => $data['ATTEMPT_ID']
                ];
            }
        }
        oci_free_statement($attemptStatement);
    }

    oci_close($databaseConnection);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/global.css" />
    <link rel="stylesheet" href="css/quiz.css" />
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

<form method="get" action="student.php">
    <input type="submit" value="Go Back" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($quizData) === 0): ?>
    <p>Error loading quiz. Try again.</p>
<?php else: 
        $dueLabel = 'No Due Date';

        if($quizData['dueAt'] !== null) {
            $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $quizData['dueAt']);
            if ($date instanceof DateTime) {
                $dueLabel = $date->format('F j, Y, g:i A');
            } else {
                $dueLabel = $quizData['dueAt'];
            } 
        } ?>
    <h1><?= htmlspecialchars($quizData['title']) ?></h1>
    <p><strong>Points:</strong> <?= htmlspecialchars($quizData['highestScore'] !== null ? intval($quizData['highestScore']) : '0') ?>/<?= htmlspecialchars($quizData['maxPoints']) ?> (<?= htmlspecialchars($quizData['highestScore'] !== null ? intval((floatval($quizData['highestScore']) / floatval($quizData['maxPoints'])) * 100) : '0') ?>%)</p>
    <p><strong>Attempts left:</strong> <?= htmlspecialchars($attemptsLeft === null ? 'Unlimited' : $attemptsLeft) ?></p>
    <p><strong>Due on:</strong> <?= ($dueLabel) ?></p>

    <?php if($attemptsLeft === null || $attemptsLeft > 0) { ?>
    <div class="questions-panel">
        <form method="post" action="quiz.php">
            <input type="submit" value="Start quiz" />
        </form>
    </div>
    <?php } ?>

    <?php if($quizData['attemptsUsed'] !== null && $quizData['attemptsUsed'] > 0) { ?>
    <div class="attempts-list">
        <form action="quiz.php" method="post">
            <ul>
                <?php foreach($attemptData as $attempt) {
                $dueLabel = '';
                if($attempt['submittedAt'] !== null) {
                    $dueLabel = 'on ';
                    $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $attempt['submittedAt']);
                    if ($date instanceof DateTime) {
                        $dueLabel .= $date->format('F j, Y, g:i A');
                    } else {
                        $dueLabel .= $attempt['submittedAt'];
                    } 
                } ?>
                <li>
                    <button type="submit" name="attemptId" value="<?= $attempt['attemptId'] ?>">Attempt <?= htmlspecialchars($attempt['attemptNumber']) ?></button>
                    <?= htmlspecialchars($attempt['score']) ?>/<?= htmlspecialchars($quizData['maxPoints']) ?> (<?= intval((floatval($attempt['score']) / floatval($quizData['maxPoints'])) * 100) ?>%) <?= htmlspecialchars($dueLabel) ?>
                </li>
                <?php } ?>
            </ul>
        </form>
    </div>
    <?php } ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
