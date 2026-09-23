<?php
session_start();

require_once dirname(__DIR__, 3) . '/hum_conn_no_login.php';

if (!isset($_SESSION['account'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['quizId'])
)
{
    $_SESSION['quizId'] = intval($_POST['quizId']);
}

if (!isset($_SESSION['quizId'])) {
    $error = 'No quiz selected. Try again.';
}

$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;

if ($error === '') {
    $quizId = $_SESSION['quizId'];
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

            if (is_object($jsonValue) && method_exists($jsonValue, 'load')) {
                $jsonString = $jsonValue->load();
            } else {
                $jsonString = (string) $jsonValue;
            }

            $data = json_decode($jsonString, true);

            if (!is_array($data)) {
                $error = 'Quiz data is invalid.';
            } elseif (
                !isset($data['questions']) ||
                !is_array($data['questions'])
            ) {
                $error = 'Quiz contains no questions.';
            } else {
                foreach ($data['questions'] as $index => $rawQuestion) {
                    if (
                        is_array($rawQuestion) &&
                        isset($rawQuestion['question'])
                    ) {
                        $questionText = $rawQuestion['question'];
                        $optionList = isset($rawQuestion['answers']) &&
                            is_array($rawQuestion['answers'])
                            ? $rawQuestion['answers']
                            : [];

                        $correctIndex = isset($rawQuestion['correct'])
                            ? intval($rawQuestion['correct'])
                            : -1;
                    } else {
                        $questionText = (string) $rawQuestion;

                        $optionList = isset($data['answers'][$index]) &&
                            is_array($data['answers'][$index])
                            ? $data['answers'][$index]
                            : [];

                        $correctIndex = -1;

                        if (isset($data['correct'][$index])) {
                            $correctValue = $data['correct'][$index];

                            if (
                                is_int($correctValue) ||
                                (
                                    is_string($correctValue) &&
                                    ctype_digit($correctValue)
                                )
                            ) {
                                $correctIndex = intval($correctValue);
                            } else {
                                $found = array_search(
                                    $correctValue,
                                    $optionList,
                                    true
                                );

                                $correctIndex = $found === false
                                    ? -1
                                    : $found;
                            }
                        }
                    }

                    $questions[] = [
                        'question' => $questionText,
                        'answers' => $optionList,
                        'correct' => $correctIndex
                    ];
                }
            }
        }
    }

    oci_free_statement($statement);
    oci_close($databaseConnection);
}

if (
    $error === '' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['guesses']) &&
    is_array($_POST['guesses'])
) {
    $guesses = $_POST['guesses'];

    if (count($questions) === count($guesses)) {
        $showScore = true;

        foreach ($questions as $index => $question) {
            if (
                isset($guesses[$index]) &&
                intval($guesses[$index]) === $question['correct']
            ) {
                $totalCorrect++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Quiz</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php require __DIR__ . '/header.php'; ?>

<form method="get" action="take.php">
    <input type="submit" value="Go Back">
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($questions) === 0): ?>
    <p>Error loading quiz. Try again.</p>
<?php else: ?>

    <?php if ($showScore): ?>
        <?php $percent = ($totalCorrect / count($questions)) * 100; ?>

        <p>
            Percent:
            <?= htmlspecialchars(
                (string) $percent,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="quiz.php">
        <input
            type="hidden"
            name="quizId"
            value="<?= htmlspecialchars(
                $_SESSION['quizId'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <?php foreach ($questions as $questionIndex => $question): ?>
            <div>
                <p>
                    <?= htmlspecialchars(
                        $question['question'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <?php foreach (
                    $question['answers'] as $answerIndex => $answer
                ): ?>
                    <?php
                    $optionId =
                        'q' . $questionIndex . 'o' . $answerIndex;

                    $isChecked =
                        isset($guesses[$questionIndex]) &&
                        intval($guesses[$questionIndex]) === $answerIndex;
                    ?>

                    <div>
                        <input
                            type="radio"
                            name="guesses[<?= $questionIndex ?>]"
                            value="<?= $answerIndex ?>"
                            id="<?= $optionId ?>"
                            required
                            <?= $isChecked ? 'checked' : '' ?>
                        >

                        <label for="<?= $optionId ?>">
                            <?= htmlspecialchars(
                                $answer,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </label>
                    </div>
                <?php endforeach; ?>

                <?php if ($showScore): ?>
                    <?php if (
                        isset($guesses[$questionIndex]) &&
                        intval($guesses[$questionIndex]) ===
                            $question['correct']
                    ): ?>
                        <p class="correct">Correct!</p>
                    <?php else: ?>
                        <p class="incorrect">Incorrect</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <input type="submit" value="Submit">
    </form>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>