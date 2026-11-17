<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$error = '';
$quizId = intval($_GET['id'] ?? $_POST['quizId'] ?? $_SESSION['quizId'] ?? 0);
$userId = $_SESSION['user_id'];

// Phase 2: Splash screen variables
$showSplashScreen = false;
$incompleteAttemptId = null;
$previousAttempts = [];
$quizData = [];
$highestScore = null;
$attemptsLeft = null;

// Quiz display variables
$quizTitle = '';
$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;
$pdfUrl = '';
$attemptId = isset($_POST['attemptId']) ? intval(htmlspecialchars($_POST['attemptId'])) : null;
$isAttempting = $attemptId !== null;

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();

    // ===== LOAD QUIZ METADATA (early, for both splash screen and quiz) =====
    $quizMetadataSql = '
        SELECT "TITLE", "DUE_AT", "POINTS_POSSIBLE", "ATTEMPTS_ALLOWED", "QUIZ_JSON"
        FROM "QUIZ"
        WHERE "QUIZ_ID" = :quiz_id
    ';
    $quizMetadataStatement = oci_parse($databaseConnection, $quizMetadataSql);
    oci_bind_by_name($quizMetadataStatement, ':quiz_id', $quizId);

    if (!oci_execute($quizMetadataStatement)) {
        $error = 'Unable to load the quiz.';
    } else {
        $quiz = oci_fetch_assoc($quizMetadataStatement);

        if ($quiz === false) {
            $error = 'Quiz not found.';
        } else {
            $quizTitle = $quiz['TITLE'];
            $quizData = [
                'title' => $quiz['TITLE'],
                'dueAt' => $quiz['DUE_AT'],
                'maxPoints' => $quiz['POINTS_POSSIBLE'],
                'maxAttempts' => $quiz['ATTEMPTS_ALLOWED']
            ];
            $attemptsLeft = $quizData['maxAttempts'];
        }
    }
    oci_free_statement($quizMetadataStatement);

    // ===== CHECK FOR INCOMPLETE ATTEMPT =====
    if ($error === '') {
        $incompleteAttemptSql = '
            SELECT "ATTEMPT_ID"
            FROM "QUIZ_ATTEMPT"
            WHERE "QUIZ_ID" = :quiz_id
                AND "USER_ID" = :user_id
                AND "SCORE" IS NULL
            FETCH FIRST 1 ROWS ONLY
        ';
        $incompleteStatement = oci_parse($databaseConnection, $incompleteAttemptSql);
        oci_bind_by_name($incompleteStatement, ':quiz_id', $quizId);
        oci_bind_by_name($incompleteStatement, ':user_id', $userId);

        if (oci_execute($incompleteStatement)) {
            $incompleteRow = oci_fetch_assoc($incompleteStatement);
            if ($incompleteRow !== false) {
                $incompleteAttemptId = $incompleteRow['ATTEMPT_ID'];
            }
        }
        oci_free_statement($incompleteStatement);
    }

    // ===== LOAD PREVIOUS ATTEMPTS (for splash screen) =====
    if ($error === '') {
        $attemptsCheckSql = '
            SELECT COUNT(*) PREVIOUS_ATTEMPTS, MAX(SCORE) HIGHEST_SCORE
            FROM "QUIZ_ATTEMPT"
            WHERE "QUIZ_ID" = :quiz_id
                AND "USER_ID" = :user_id
                AND "SCORE" IS NOT NULL
        ';
        $attemptsCheckStatement = oci_parse($databaseConnection, $attemptsCheckSql);
        oci_bind_by_name($attemptsCheckStatement, ':quiz_id', $quizId);
        oci_bind_by_name($attemptsCheckStatement, ':user_id', $userId);

        if (oci_execute($attemptsCheckStatement)) {
            $attemptsCheck = oci_fetch_assoc($attemptsCheckStatement);
            $quizData['attemptsUsed'] = $attemptsCheck['PREVIOUS_ATTEMPTS'] ?? 0;
            $highestScore = $attemptsCheck['HIGHEST_SCORE'];

            if ($attemptsLeft !== null) {
                $attemptsLeft -= $quizData['attemptsUsed'];
            }
        }
        oci_free_statement($attemptsCheckStatement);
    }

    // ===== LOAD PREVIOUS ATTEMPTS HISTORY (for display on splash) =====
    if ($error === '' && isset($quizData['attemptsUsed']) && $quizData['attemptsUsed'] > 0) {
        $attemptsHistorySql = '
            SELECT "ATTEMPT_ID", "SCORE", "ATTEMPT_NUMBER", "SUBMITTED_AT"
            FROM "QUIZ_ATTEMPT"
            WHERE "QUIZ_ID" = :quiz_id
                AND "USER_ID" = :user_id
                AND "SCORE" IS NOT NULL
            ORDER BY "SUBMITTED_AT" DESC
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
    }

    // ===== HANDLE "START QUIZ" or "CONTINUE ATTEMPT" BUTTON (from splash screen) =====
    if ($error === '' && $incompleteAttemptId === null && isset($_POST['startQuiz'])) {
        // Create new attempt
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
            // Set attemptId to the newly created attempt
            // Query for the new attempt ID
            $getNewAttemptSql = 'SELECT ATTEMPT_ID FROM QUIZ_ATTEMPT WHERE QUIZ_ID = :quiz_id AND USER_ID = :user_id AND ATTEMPT_NUMBER = :attempt_number';
            $getNewAttemptStatement = oci_parse($databaseConnection, $getNewAttemptSql);
            oci_bind_by_name($getNewAttemptStatement, ':quiz_id', $quizId);
            oci_bind_by_name($getNewAttemptStatement, ':user_id', $userId);
            oci_bind_by_name($getNewAttemptStatement, ':attempt_number', $nextAttemptNumber);
            
            if (oci_execute($getNewAttemptStatement)) {
                if ($attemptData = oci_fetch_assoc($getNewAttemptStatement)) {
                    $attemptId = $attemptData['ATTEMPT_ID'];
                    $isAttempting = true;
                    $incompleteAttemptId = $attemptData['ATTEMPT_ID'];
                }
            }
            oci_free_statement($getNewAttemptStatement);
        } else {
            $error = 'Unable to start new attempt.';
        }
        oci_free_statement($createAttemptStatement);
    }

    // ===== DETERMINE IF SHOWING SPLASH SCREEN OR QUIZ =====
    if ($error === '' && $incompleteAttemptId === null) {
        // No incomplete attempt: show splash screen
        $showSplashScreen = true;
    } elseif ($error === '' && $incompleteAttemptId !== null) {
        // Has incomplete attempt or just created one
        // Show quiz if continuing, otherwise show splash
        if (isset($_POST['continueAttempt'])) {
            $showSplashScreen = false;
            $attemptId = $incompleteAttemptId;
            $isAttempting = true;
        } else if (isset($_POST['startQuiz'])) {
            // Just started new quiz, now show it
            $showSplashScreen = false;
            $attemptId = $incompleteAttemptId;
            $isAttempting = true;
        } else {
            // Page load with existing incomplete attempt
            $showSplashScreen = true;
            $attemptId = $incompleteAttemptId;
            $isAttempting = true;
        }
    }

    // ===== IF NOT SHOWING SPLASH, LOAD QUIZ QUESTIONS =====
    if ($error === '' && !$showSplashScreen) {
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

    // ===== HANDLE QUIZ SUBMISSION =====
    if ($error === '' && !$showSplashScreen && isset($_POST['guesses']) && is_array($_POST['guesses'])) {
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
            oci_bind_by_name($submitStatement, ':attempt_id', $attemptId);

            if (oci_execute($submitStatement)) {
                oci_commit($databaseConnection);
                // Redirect back to splash screen to show results
                header("Location: quiz.php?id=" . urlencode($quizId));
                exit();
            } else {
                $error = 'Error submitting quiz.';
            }
            oci_free_statement($submitStatement);
        }
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
    <input class="button-secondary" type="submit" value="Go Back" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>

    <!-- ===== SPLASH SCREEN (BEFORE TAKING QUIZ) ===== -->
    <?php if ($showSplashScreen): ?>
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

        <?php if ($attemptsLeft === null || $attemptsLeft > 0): ?>
            <div class="questions-panel">
                <form method="post" action="quiz.php?id=<?= htmlspecialchars($quizId, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($incompleteAttemptId !== null): ?>
                        <input type="hidden" name="attemptId" value="<?= htmlspecialchars((string) $incompleteAttemptId, ENT_QUOTES, 'UTF-8') ?>" />
                        <button class="button-primary" type="submit" name="continueAttempt" value="1">Continue Attempt</button>
                    <?php else: ?>
                        <button class="button-primary" type="submit" name="startQuiz" value="1">Start Quiz</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($quizData['attemptsUsed'] > 0): ?>
            <div class="attempts-list">
                <h2>Previous Attempts</h2>
                <ul>
                    <?php foreach ($previousAttempts as $attempt): 
                        $attemptLabel = '';
                        if ($attempt['submittedAt'] !== null) {
                            $attemptLabel = 'on ';
                            $date = DateTime::createFromFormat('d-M-y h.i.s.u A', $attempt['submittedAt']);
                            if ($date instanceof DateTime) {
                                $attemptLabel .= $date->format('F j, Y, g:i A');
                            } else {
                                $attemptLabel .= $attempt['submittedAt'];
                            }
                        }
                    ?>
                        <li>
                            Attempt <?= htmlspecialchars($attempt['attemptNumber']) ?>: 
                            <?= htmlspecialchars($attempt['score']) ?>/<?= htmlspecialchars($quizData['maxPoints']) ?> 
                            (<?= intval((floatval($attempt['score']) / floatval($quizData['maxPoints'])) * 100) ?>%) 
                            <?= htmlspecialchars($attemptLabel) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <!-- ===== QUIZ QUESTIONS (WHILE TAKING QUIZ) ===== -->
    <?php else: ?>
        <?php if (count($questions) === 0): ?>
            <p>Error loading quiz. Try again.</p>
        <?php else: ?>
            <h1><?= htmlspecialchars($quizTitle) ?></h1>
            <?php if ($showScore): ?>
                <?php $percent = ($totalCorrect / count($questions)) * 100; ?>
                <p>Percent: <?= htmlspecialchars((string) $percent, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="<?= $pdfUrl !== '' ? 'quiz-with-pdf' : '' ?>">
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
                    <form method="post" action="quiz.php?id=<?= htmlspecialchars($quizId, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="attemptId" value="<?= htmlspecialchars((string) $attemptId, ENT_QUOTES, 'UTF-8') ?>" />
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
                                        <?= $isAttempting ? '' : 'disabled="disabled"' ?>
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
                                                <?= $isAttempting ? '' : 'disabled="disabled"' ?>
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
                        <?php if ($isAttempting): ?>
                            <input class="button-primary" type="submit" value="Submit" />
                        <?php endif; ?>
                    </form>
                </div>
            </div>

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
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
