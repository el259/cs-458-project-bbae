<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$error = '';
$quizId = intval($_POST['quizId'] ?? $_SESSION['quizId'] ?? 0);
$quizTitle = '';
$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;
$pdfUrl = '';

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();
    $sql = '
        SELECT "TITLE", "QUIZ_JSON"
        FROM "QUIZ"
        WHERE "QUIZ_ID" = :quiz_id
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
            $quizTitle = $quiz['TITLE'];
            $jsonValue = $quiz['QUIZ_JSON'];
            $jsonString = is_object($jsonValue) && method_exists($jsonValue, 'load')
                ? $jsonValue->load()
                : (string) $jsonValue;
            $data = json_decode($jsonString, true);

            if (!is_array($data) || !isset($data['questions']) ||
                !is_array($data['questions'])) {
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
                        $optionList = isset($rawQuestion['answers']) &&
                            is_array($rawQuestion['answers'])
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
                        $optionList = isset($data['answers'][$index]) &&
                            is_array($data['answers'][$index])
                            ? $data['answers'][$index]
                            : [];
                        $correctIndex = -1;
                        $description = '';
                        $image = '';

                        if (isset($data['correct'][$index])) {
                            $correctValue = $data['correct'][$index];
                            if (is_int($correctValue) ||
                                (is_string($correctValue) && ctype_digit($correctValue))) {
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
    }

    oci_free_statement($statement);
    oci_close($databaseConnection);
}

if ($error === '' && isset($_POST['guesses']) && is_array($_POST['guesses']) && isset($_SESSION['user_id'])) {
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

        $databaseConnection = hum_conn_no_login();

        $userId = $_SESSION['user_id'];
        $maxPoints = -1;
        $currentAttempts = 0;
        $redirect = false;

        $selectSql = '
            SELECT "POINTS_POSSIBLE"
            FROM "QUIZ"
            WHERE "QUIZ_ID" = :quiz_id
        ';
        $statement = oci_parse($databaseConnection, $selectSql);
        oci_bind_by_name($statement, ':quiz_id', $quizId);
        if (!oci_execute($statement)) {
            $error = 'Error reading quiz point data.';
        } else {
            $points = oci_fetch_assoc($statement);
            $maxPoints = $points['POINTS_POSSIBLE'];
        }
        oci_free_statement($statement);

        if($error === '') {
            $selectSql = '
                SELECT COUNT(*) "NUM_ATTEMPTS"
                FROM "QUIZ_ATTEMPT"
                WHERE "QUIZ_ID" = :quiz_id
                    AND "USER_ID" = :user_id
            ';
            $statement = oci_parse($databaseConnection, $selectSql);
            oci_bind_by_name($statement, ':quiz_id', $quizId);
            oci_bind_by_name($statement, ':user_id', $userId);
            if (!oci_execute($statement)) {
                $error = 'Error calculating quiz attempt number.';
            } else {
                $attempts = oci_fetch_assoc($statement);
                $currentAttempts = $attempts['NUM_ATTEMPTS'];
            }
            
            oci_free_statement($statement);
        }

        if($error === '') {
            $score = ($totalCorrect / count($questions)) * $maxPoints;
            $attempt = $currentAttempts + 1;

            $updateSql = '
                INSERT INTO QUIZ_ATTEMPT ("QUIZ_ID", "USER_ID", "ATTEMPT_NUMBER", "SUBMITTED_AT", "SCORE")
                VALUES
                (:quiz_id, :user_id, :attempt_number, SYSDATE, :score)
            ';
            $statement = oci_parse($databaseConnection, $updateSql);
            oci_bind_by_name($statement, ':quiz_id', $quizId);
            oci_bind_by_name($statement, ':user_id', $userId);
            oci_bind_by_name($statement, ':attempt_number', $attempt);
            oci_bind_by_name($statement, ':score', $score);

            if (!oci_execute($statement)) {
                $error = 'Error submitting quiz.';
            } else {
                $redirect = true;
            }
            
            oci_free_statement($statement);
        }
        
        oci_close($databaseConnection);

        // if($redirect) {
        //     header("Location: viewquiz.php");
        //     exit();
        // }
    }
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

<form method="get" action="viewquiz.php">
    <input type="submit" value="Go Back" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($questions) === 0): ?>
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
            <form method="post" action="quiz.php">
                <input type="hidden" name="quizId" value="<?= htmlspecialchars((string) $quizId, ENT_QUOTES, 'UTF-8') ?>" />
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
                <input type="submit" value="Submit" />
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

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
