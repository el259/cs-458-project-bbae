<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

$error = '';
$quizId = intval($_POST['quizId'] ?? $_SESSION['quizId'] ?? 0);
$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;

if ($quizId <= 0) {
    $error = 'No quiz selected. Try again.';
} else {
    $_SESSION['quizId'] = $quizId;
    $databaseConnection = hum_conn_no_login();
    $sql = '
        SELECT "QUIZ_JSON"
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
            $jsonValue = $quiz['QUIZ_JSON'];
            $jsonString = is_object($jsonValue) && method_exists($jsonValue, 'load')
                ? $jsonValue->load()
                : (string) $jsonValue;
            $data = json_decode($jsonString, true);

            if (!is_array($data) || !isset($data['questions']) ||
                !is_array($data['questions'])) {
                $error = 'Quiz data is invalid.';
            } else {
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
                    } else {
                        $questionText = (string) $rawQuestion;
                        $optionList = isset($data['answers'][$index]) &&
                            is_array($data['answers'][$index])
                            ? $data['answers'][$index]
                            : [];
                        $correctIndex = -1;

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

if ($error === '' && isset($_POST['guesses']) && is_array($_POST['guesses'])) {
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
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<form method="get" action="take.php">
    <input type="submit" value="Go Back" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($questions) === 0): ?>
    <p>Error loading quiz. Try again.</p>
<?php else: ?>
    <?php if ($showScore): ?>
        <?php $percent = ($totalCorrect / count($questions)) * 100; ?>
        <p>Percent: <?= htmlspecialchars((string) $percent, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p>IGNORE: Loaded <?= count($questions) ?> questions, <?= strlen($jsonString) ?> bytes.</p>

    <form method="post" action="quiz.php">
        <input type="hidden" name="quizId" value="<?= htmlspecialchars((string) $quizId, ENT_QUOTES, 'UTF-8') ?>" />
        <?php foreach ($questions as $questionIndex => $question): ?>
            <div>
                <p><?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') ?></p>
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
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
