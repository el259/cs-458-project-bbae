<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$error = '';
$quizId = intval($_GET['id'] ?? $_POST['quizId'] ?? $_SESSION['quizId'] ?? 0);
$userId = $_SESSION['user_id'];
$quizTitle = '';
$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;
$pdfUrl = '';
$showSplashScreen = true;
$incompleteAttemptId = null;
$previousAttempts = [];
$quizData = [];
$highestScore = null;
$attemptsLeft = null;

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();
    
    // Load quiz metadata
    $metadataSql = '
        SELECT "TITLE", "DUE_AT", "POINTS_POSSIBLE", "ATTEMPTS_ALLOWED", "QUIZ_JSON"
        FROM "QUIZ"
        WHERE "QUIZ_ID" = :quiz_id
    ';
    $metadataStatement = oci_parse($databaseConnection, $metadataSql);
    oci_bind_by_name($metadataStatement, ':quiz_id', $quizId);

    if (!oci_execute($metadataStatement)) {
        $error = 'Unable to load the quiz.';
    } else {
        $quiz = oci_fetch_assoc($metadataStatement);

        if ($quiz === false) {
            $error = 'Quiz not found.';
        } else {
            $quizTitle = $quiz['TITLE'];
            $quizData = [
                'title' => $quiz['TITLE'],
                'dueAt' => $quiz['DUE_AT'],
                'maxPoints' => $quiz['POINTS_POSSIBLE'],
                'attemptsAllowed' => $quiz['ATTEMPTS_ALLOWED']
            ];
            
            // Check for incomplete attempts
            $incompleteCheckSql = '
                SELECT "ATTEMPT_ID"
                FROM "QUIZ_ATTEMPT"
                WHERE "QUIZ_ID" = :quiz_id AND "USER_ID" = :user_id AND "SCORE" IS NULL
                ORDER BY "ATTEMPT_ID" DESC
            ';
            $incompleteCheckStatement = oci_parse($databaseConnection, $incompleteCheckSql);
            oci_bind_by_name($incompleteCheckStatement, ':quiz_id', $quizId);
            oci_bind_by_name($incompleteCheckStatement, ':user_id', $userId);
            
            if (oci_execute($incompleteCheckStatement)) {
                $incompleteRow = oci_fetch_assoc($incompleteCheckStatement);
                if ($incompleteRow !== false) {
                    $incompleteAttemptId = $incompleteRow['ATTEMPT_ID'];
                }
            }
            oci_free_statement($incompleteCheckStatement);
            
            // Check for completed attempts (for splash screen display)
            $attemptsCheckSql = '
                SELECT COUNT(*) NUM_ATTEMPTS, MAX(SCORE) HIGHEST_SCORE
                FROM "QUIZ_ATTEMPT"
                WHERE "QUIZ_ID" = :quiz_id AND "USER_ID" = :user_id AND "SCORE" IS NOT NULL
            ';
            $attemptsCheckStatement = oci_parse($databaseConnection, $attemptsCheckSql);
            oci_bind_by_name($attemptsCheckStatement, ':quiz_id', $quizId);
            oci_bind_by_name($attemptsCheckStatement, ':user_id', $userId);
            
            if (oci_execute($attemptsCheckStatement)) {
                $attemptsInfo = oci_fetch_assoc($attemptsCheckStatement);
                $attemptsUsed = $attemptsInfo['NUM_ATTEMPTS'] ?? 0;
                $highestScore = $attemptsInfo['HIGHEST_SCORE'];
                
                $attemptsAllowed = $quiz['ATTEMPTS_ALLOWED'];
                if ($attemptsAllowed === null) {
                    $attemptsLeft = null; // unlimited
                } else {
                    $attemptsLeft = $attemptsAllowed - $attemptsUsed - ($incompleteAttemptId !== null ? 1 : 0);
                }
                
                $quizData['attemptsUsed'] = $attemptsUsed;
            }
            oci_free_statement($attemptsCheckStatement);
            
            // Load previous attempts for splash screen
            $attemptsHistorySql = '
                SELECT "ATTEMPT_ID", "SCORE", "ATTEMPT_NUMBER", "SUBMITTED_AT"
                FROM "QUIZ_ATTEMPT"
                WHERE "QUIZ_ID" = :quiz_id AND "USER_ID" = :user_id AND "SCORE" IS NOT NULL
                ORDER BY "ATTEMPT_NUMBER" DESC
            ';
            $attemptsHistoryStatement = oci_parse($databaseConnection, $attemptsHistorySql);
            oci_bind_by_name($attemptsHistoryStatement, ':quiz_id', $quizId);
            oci_bind_by_name($attemptsHistoryStatement, ':user_id', $userId);
            
            if (oci_execute($attemptsHistoryStatement)) {
                while ($attemptRow = oci_fetch_assoc($attemptsHistoryStatement)) {
                    $previousAttempts[] = [
                        'attemptId' => $attemptRow['ATTEMPT_ID'],
                        'score' => $attemptRow['SCORE'],
                        'attemptNumber' => $attemptRow['ATTEMPT_NUMBER'],
                        'submittedAt' => $attemptRow['SUBMITTED_AT']
                    ];
                }
            }
            oci_free_statement($attemptsHistoryStatement);
            
            // Handle "Start Quiz" button click - create incomplete attempt and redirect
            if ($error === '' && $incompleteAttemptId === null && isset($_POST['startQuiz'])) {
                $nextAttemptNumber = ($quizData['attemptsUsed'] ?? 0) + 1;
                $createAttemptSql = '
                    INSERT INTO "QUIZ_ATTEMPT" ("QUIZ_ID", "USER_ID", "ATTEMPT_NUMBER", "STARTED_AT")
                    VALUES (:quiz_id, :user_id, :attempt_number, SYSDATE)
                ';
                $createAttemptStatement = oci_parse($databaseConnection, $createAttemptSql);
                oci_bind_by_name($createAttemptStatement, ':quiz_id', $quizId);
                oci_bind_by_name($createAttemptStatement, ':user_id', $userId);
                oci_bind_by_name($createAttemptStatement, ':attempt_number', $nextAttemptNumber);
                
                if (oci_execute($createAttemptStatement)) {
                    oci_commit($databaseConnection);
                    oci_free_statement($createAttemptStatement);
                    // Redirect back to reload and show quiz questions
                    header("Location: quiz.php?id=" . urlencode($quizId));
                    exit();
                } else {
                    $error = 'Unable to start new attempt.';
                }
                oci_free_statement($createAttemptStatement);
            }
            
            // Determine if showing quiz questions (not splash)
            if ($error === '' && $incompleteAttemptId !== null && (isset($_POST['continueAttempt']) || isset($_POST['guesses']))) {
                $showSplashScreen = false;
            }
            
            // Load quiz questions if showing quiz
            if ($error === '' && !$showSplashScreen && $incompleteAttemptId !== null) {
                $jsonValue = $quiz['QUIZ_JSON'];
                $jsonString = is_object($jsonValue) && method_exists($jsonValue, 'load')
                    ? $jsonValue->load()
                    : (string) $jsonValue;
                $data = json_decode($jsonString, true);

                if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
                    $error = 'Quiz data is invalid.';
                } else {
                    $pdfUrl = isset($data['pdf']) ? (string) $data['pdf'] : '';

                    foreach ($data['questions'] as $index => $rawQuestion) {
                        $inputType = 'multiple-choice';
                        $fillAnswer = '';
                        $ignoreDiacritics = false;
                        $allowTypos = false;

                        if (is_array($rawQuestion) && isset($rawQuestion['question'])) {
                            $questionText = $rawQuestion['question'];
                            $inputType = $rawQuestion['inputType'] ?? 'multiple-choice';
                            $optionList = isset($rawQuestion['answers']) && is_array($rawQuestion['answers'])
                                ? $rawQuestion['answers']
                                : [];
                            $correctIndex = isset($rawQuestion['correct'])
                                ? intval($rawQuestion['correct'])
                                : -1;
                            $fillAnswer = isset($rawQuestion['answer'])
                                ? (string) $rawQuestion['answer']
                                : '';
                            $ignoreDiacritics = !empty($rawQuestion['ignoreDiacritics']);
                            $allowTypos = !empty($rawQuestion['allowTypos']);
                            $description = isset($rawQuestion['description'])
                                ? (string) $rawQuestion['description']
                                : '';
                            $image = isset($rawQuestion['image'])
                                ? (string) $rawQuestion['image']
                                : '';
                        } else {
                            $questionText = (string) $rawQuestion;
                            $optionList = isset($data['answers'][$index]) && is_array($data['answers'][$index])
                                ? $data['answers'][$index]
                                : [];
                            $correctIndex = -1;
                            $description = '';
                            $image = '';

                            if (isset($data['correct'][$index])) {
                                $correctValue = $data['correct'][$index];
                                if (is_int($correctValue) || (is_string($correctValue) && ctype_digit($correctValue))) {
                                    $correctIndex = intval($correctValue);
                                } else {
                                    $found = array_search($correctValue, $optionList, true);
                                    $correctIndex = $found === false ? -1 : $found;
                                }
                            }
                        }

                        $questions[] = [
                            'question' => $questionText,
                            'description' => $description,
                            'image' => $image,
                            'inputType' => $inputType,
                            'answers' => $optionList,
                            'correct' => $correctIndex,
                            'answer' => $fillAnswer,
                            'ignoreDiacritics' => $ignoreDiacritics,
                            'allowTypos' => $allowTypos,
                            'verdict' => 'wrong'
                        ];
                    }
                }
            }
            
            // Handle quiz submission - UPDATE incomplete attempt with score
            if ($error === '' && !$showSplashScreen && isset($_POST['guesses']) && is_array($_POST['guesses']) && $incompleteAttemptId !== null) {
                $guesses = $_POST['guesses'];

                if (count($questions) > 0 && count($guesses) === count($questions)) {
                    $showScore = true;
                    foreach ($questions as $index => $question) {
                        $given = $guesses[$index] ?? '';

                        if ($question['inputType'] === 'fill-in') {
                            $result = checkAnswer(
                                (string) $given,
                                $question['answer'],
                                $question['ignoreDiacritics'],
                                $question['allowTypos']
                            );
                            $questions[$index]['verdict'] = $result['verdict'];
                            if ($result['verdict'] !== 'wrong') {
                                $totalCorrect++;
                            }
                        } else if (intval($given) === intval($question['correct'])) {
                            $questions[$index]['verdict'] = 'exact';
                            $totalCorrect++;
                        } else {
                            $questions[$index]['verdict'] = 'wrong';
                        }
                    }

                    $maxPoints = $quizData['maxPoints'] ?? -1;
                    $score = ($totalCorrect / count($questions)) * $maxPoints;

                    $submitSql = '
                        UPDATE "QUIZ_ATTEMPT"
                        SET "SUBMITTED_AT" = SYSDATE, "SCORE" = :score
                        WHERE "ATTEMPT_ID" = :attempt_id
                    ';
                    $submitStatement = oci_parse($databaseConnection, $submitSql);
                    oci_bind_by_name($submitStatement, ':score', $score);
                    oci_bind_by_name($submitStatement, ':attempt_id', $incompleteAttemptId);

                    if (oci_execute($submitStatement)) {
                        oci_commit($databaseConnection);
                        oci_free_statement($submitStatement);
                        // Redirect back to splash screen
                        header("Location: quiz.php?id=" . urlencode($quizId));
                        exit();
                    } else {
                        $error = 'Error submitting quiz.';
                    }
                    oci_free_statement($submitStatement);
                }
            }
        }
    }
    oci_free_statement($metadataStatement);
    oci_close($databaseConnection);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HumGlot</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/global.css" />
    <link rel="stylesheet" href="css/quiz.css" />
    <style>
        .pdf-panel { display: flex; flex-direction: column; height: 500px; border: 1px solid #ccc; margin-bottom: 1rem; }
        .pdf-panel.hidden { display: none; }
        .pdf-header { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0.6rem; background: #f5f5f5; border-bottom: 1px solid #ccc; font-size: 0.9rem; }
        .toggle-btn { padding: 0.2rem 0.6rem; cursor: pointer; }
        .questions-panel { flex: 1; overflow-y: auto; }
        #pdfFrame { flex: 1; border: none; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/header.php'; ?>
    <div class="container">
        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif ($showSplashScreen): ?>
            <!-- SPLASH SCREEN -->
            <div class="quiz-container">
                <?php 
                    $dueLabel = 'No Due Date';
                    if ($quizData['dueAt'] !== null) {
                        $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $quizData['dueAt']);
                        if ($date instanceof DateTime) {
                            $dueLabel = $date->format('F j, Y, g:i A');
                        } else {
                            $dueLabel = $quizData['dueAt'];
                        }
                    }
                ?>
                <h1><?= htmlspecialchars($quizData['title']) ?></h1>
                <p><strong>Points:</strong> <?= htmlspecialchars($highestScore !== null ? intval($highestScore) : '0') ?>/<?= htmlspecialchars($quizData['maxPoints']) ?> (<?= htmlspecialchars($highestScore !== null ? intval((floatval($highestScore) / floatval($quizData['maxPoints'])) * 100) : '0') ?>%)</p>
                <p><strong>Attempts left:</strong> <?= htmlspecialchars($attemptsLeft === null ? 'Unlimited' : $attemptsLeft) ?></p>
                <p><strong>Due on:</strong> <?= ($dueLabel) ?></p>

                <?php if ($incompleteAttemptId !== null || $attemptsLeft === null || $attemptsLeft > 0): ?>
                    <div class="questions-panel">
                        <form method="post" action="quiz.php?id=<?= htmlspecialchars($quizId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($incompleteAttemptId !== null): ?>
                                <button class="button-primary" type="submit" name="continueAttempt" value="1">Continue Attempt</button>
                            <?php else: ?>
                                <button class="button-primary" type="submit" name="startQuiz" value="1">Start Quiz</button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (count($previousAttempts) > 0): ?>
                    <div class="attempts-list">
                        <h2>Previous Attempts</h2>
                        <ul>
                            <?php foreach ($previousAttempts as $attempt): 
                                $attemptLabel = 'Attempt ' . $attempt['attemptNumber'];
                                if ($attempt['submittedAt'] !== null) {
                                    $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $attempt['submittedAt']);
                                    if ($date instanceof DateTime) {
                                        $attemptLabel .= ' on ' . $date->format('F j, Y, g:i A');
                                    }
                                }
                            ?>
                                <li><?= htmlspecialchars($attemptLabel, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(intval($attempt['score']), ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($quizData['maxPoints'], ENT_QUOTES, 'UTF-8') ?> (<?= intval((floatval($attempt['score']) / floatval($quizData['maxPoints'])) * 100)?>%)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- QUIZ QUESTIONS -->
            <div class="quiz-container">
                <?php if ($pdfUrl !== ''): ?>
                    <div class="pdf-panel" id="pdfPanel">
                        <div class="pdf-header">
                            <span>Reference PDF</span>
                            <button type="button" id="togglePdf" class="toggle-btn">Hide PDF</button>
                        </div>
                        <iframe id="pdfFrame" src="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"></iframe>
                    </div>
                <?php endif; ?>

                <div class="questions-panel">
                    <h1><?= htmlspecialchars($quizTitle) ?></h1>
                    <form method="post" action="quiz.php?id=<?= htmlspecialchars($quizId, ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($questions as $questionIndex => $question): ?>
                            <div class="quiz-question">
                                <p class="q-text"><?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($question['description'])): ?>
                                    <p class="q-description"><?= htmlspecialchars($question['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($question['image'])): ?>
                                    <img class="q-image" src="<?= htmlspecialchars($question['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Question image" />
                                <?php endif; ?>
                                <?php if ($question['inputType'] === 'fill-in'): ?>
                                    <input
                                        type="text"
                                        name="guesses[<?= $questionIndex ?>]"
                                        value="<?= isset($guesses[$questionIndex]) ? htmlspecialchars((string) $guesses[$questionIndex], ENT_QUOTES, 'UTF-8') : '' ?>"
                                        required="required"
                                        <?= $showScore ? 'disabled="disabled"' : '' ?>
                                    />
                                <?php else: ?>
                                    <?php foreach ($question['answers'] as $answerIndex => $answer): ?>
                                        <?php $optionId = 'q' . $questionIndex . 'o' . $answerIndex; ?>
                                        <div>
                                            <input
                                                type="radio"
                                                name="guesses[<?= $questionIndex ?>]"
                                                value="<?= $answerIndex ?>"
                                                id="<?= $optionId ?>"
                                                required="required"
                                                <?= isset($guesses[$questionIndex]) && intval($guesses[$questionIndex]) === $answerIndex ? 'checked="checked"' : '' ?>
                                                <?= $showScore ? 'disabled="disabled"' : '' ?>
                                            />
                                            <label for="<?= $optionId ?>">
                                                <?= htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if ($showScore): ?>
                                    <?php if ($question['verdict'] === 'exact'): ?>
                                        <p class="correct">Correct!</p>
                                    <?php elseif ($question['verdict'] === 'diacritic'): ?>
                                        <p class="correct">Accepted. Accent missing: <?= htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php elseif ($question['verdict'] === 'typo'): ?>
                                        <p class="correct">Accepted. Expected: <?= htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php else: ?>
                                        <p class="incorrect">Incorrect</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$showScore): ?>
                            <input class="button-primary" type="submit" value="Submit" />
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($showScore): ?>
                <?php $percent = ($totalCorrect / count($questions)) * 100; ?>
                <div class="score-panel">
                    <p>Score: <?= htmlspecialchars((string) $percent, ENT_QUOTES, 'UTF-8') ?>%</p>
                </div>
            <?php endif; ?>

            <?php if ($pdfUrl !== ''): ?>
            <script>
            const toggleBtn = document.getElementById('togglePdf');
            const pdfPanel = document.getElementById('pdfPanel');
            if (toggleBtn && pdfPanel) {
                toggleBtn.addEventListener('click', function () {
                    pdfPanel.classList.toggle('hidden');
                    toggleBtn.textContent = pdfPanel.classList.contains('hidden') ? 'Show PDF' : 'Hide PDF';
                });
            }
            </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
