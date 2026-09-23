<?php
session_start();

require_once dirname(__DIR__, 3) . '/hum_conn_no_login.php';

if (
    !isset($_SESSION['account']) ||
    empty($_SESSION['teacher']) ||
    !isset($_SESSION['user_id'])
) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['quiz'])) {
    $_SESSION['quiz'] = [
        'name' => '',
        'questions' => []
    ];
}

$error = '';

if (isset($_POST['setQuizName'])) {
    $quizName = trim($_POST['quizName'] ?? '');

    if ($quizName === '') {
        $error = 'Please enter a quiz name.';
    } else {
        $_SESSION['quiz']['name'] = $quizName;

        header('Location: create.php');
        exit();
    }
}

if (isset($_POST['addQuestion'])) {
    $question = trim($_POST['question'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';

    $postedAnswers = isset($_POST['answer']) &&
        is_array($_POST['answer'])
        ? $_POST['answer']
        : [];

    $answers = [];

    foreach ($postedAnswers as $answer) {
        $answers[] = trim((string) $answer);
    }

    $correct = intval($_POST['correct'] ?? -1);

    if ($question === '') {
        $error = 'Please enter a question.';
    } elseif (
        $inputType !== 'multiple-choice' &&
        $inputType !== 'true-false'
    ) {
        $error = 'Invalid input type.';
    } elseif (count($answers) < 2) {
        $error = 'Please provide at least two answers.';
    } elseif (count($answers) > 6) {
        $error = 'A question cannot have more than six answers.';
    } elseif (
        $inputType === 'true-false' &&
        count($answers) !== 2
    ) {
        $error = 'True/false questions must have exactly two answers.';
    } elseif (in_array('', $answers, true)) {
        $error = 'Answer choices cannot be empty.';
    } elseif (!array_key_exists($correct, $answers)) {
        $error = 'Please select a correct answer.';
    } else {
        $_SESSION['quiz']['questions'][] = [
            'question' => $question,
            'inputType' => $inputType,
            'answers' => $answers,
            'correct' => $correct
        ];

        header('Location: create.php');
        exit();
    }
}

if (isset($_POST['createQuiz'])) {
    $quizName = trim($_SESSION['quiz']['name']);
    $questions = $_SESSION['quiz']['questions'];

    if ($quizName === '' || count($questions) === 0) {
        $error = 'You must give the quiz a name and add at least one question.';
    } else {
        $databaseConnection = hum_conn_no_login();

        $checkSql = '
            SELECT "QUIZ_ID"
            FROM "QUIZ"
            WHERE "CREATOR_ID" = :creator_id
              AND "TITLE" = :title
        ';

        $checkStatement = oci_parse(
            $databaseConnection,
            $checkSql
        );

        $creatorId = $_SESSION['user_id'];

        oci_bind_by_name(
            $checkStatement,
            ':creator_id',
            $creatorId
        );

        oci_bind_by_name(
            $checkStatement,
            ':title',
            $quizName
        );

        if (!oci_execute($checkStatement)) {
            $error = 'Unable to check for existing quizzes.';
        } elseif (oci_fetch_assoc($checkStatement) !== false) {
            $error = 'A quiz with that name already exists.';
        } else {
            $quizJson = json_encode(
                $_SESSION['quiz'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );

            if ($quizJson === false) {
                $error = 'Unable to format the quiz data.';
            } else {
                $insertSql = '
                    INSERT INTO "QUIZ" (
                        "CREATOR_ID",
                        "TITLE",
                        "DESCRIPTION",
                        "QUIZ_JSON"
                    )
                    VALUES (
                        :creator_id,
                        :title,
                        NULL,
                        :quiz_json
                    )
                ';

                $insertStatement = oci_parse(
                    $databaseConnection,
                    $insertSql
                );

                $quizClob = oci_new_descriptor(
                    $databaseConnection,
                    OCI_D_LOB
                );

                if ($quizClob === false) {
                    $error = 'Unable to create the quiz data object.';
                } else {
                    oci_bind_by_name(
                        $insertStatement,
                        ':creator_id',
                        $creatorId
                    );

                    oci_bind_by_name(
                        $insertStatement,
                        ':title',
                        $quizName
                    );

                    oci_bind_by_name(
                        $insertStatement,
                        ':quiz_json',
                        $quizClob,
                        -1,
                        OCI_B_CLOB
                    );

                    $quizClob->writeTemporary(
                        $quizJson,
                        OCI_TEMP_CLOB
                    );

                    if (
                        !oci_execute(
                            $insertStatement,
                            OCI_NO_AUTO_COMMIT
                        )
                    ) {
                        $oracleError = oci_error($insertStatement);

                        error_log(
                            'Quiz insert failed: ' .
                            ($oracleError['message'] ?? 'Unknown error')
                        );

                        oci_rollback($databaseConnection);
                        $error = 'Unable to save the quiz.';
                    } else {
                        oci_commit($databaseConnection);
                        unset($_SESSION['quiz']);

                        $quizClob->free();
                        oci_free_statement($insertStatement);
                        oci_free_statement($checkStatement);
                        oci_close($databaseConnection);

                        header('Location: teacher.php');
                        exit();
                    }

                    $quizClob->free();
                }

                oci_free_statement($insertStatement);
            }
        }

        oci_free_statement($checkStatement);
        oci_close($databaseConnection);
    }
}

if (isset($_POST['cancelQuiz'])) {
    unset($_SESSION['quiz']);

    header('Location: teacher.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create a Quiz</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Create a Quiz</h1>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="create.php">
    <input type="submit" name="cancelQuiz" value="Cancel">
</form>

<?php if ($_SESSION['quiz']['name'] === ''): ?>

    <h2>1. Name Your Quiz</h2>

    <form method="post" action="create.php">
        <label for="quizName">Quiz Name</label>

        <input
            type="text"
            name="quizName"
            id="quizName"
            required
        >

        <input
            type="submit"
            name="setQuizName"
            value="Continue"
        >
    </form>

<?php else: ?>

    <h2>
        <?= htmlspecialchars(
            $_SESSION['quiz']['name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </h2>

    <?php if (count($_SESSION['quiz']['questions']) > 0): ?>
        <h3>Questions</h3>

        <?php foreach (
            $_SESSION['quiz']['questions'] as $index => $question
        ): ?>
            <div>
                <p>
                    <strong>Question <?= $index + 1 ?>:</strong>
                    <?= htmlspecialchars(
                        $question['question'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    Input Type:
                    <?= htmlspecialchars(
                        $question['inputType'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <ol>
                    <?php foreach (
                        $question['answers'] as $answerIndex => $answer
                    ): ?>
                        <li>
                            <?= htmlspecialchars(
                                $answer,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if (
                                $answerIndex == $question['correct']
                            ): ?>
                                <strong>(Correct)</strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Add a Question</h2>

    <form method="post" action="create.php">
        <div>
            <label for="question">Question</label>

            <input
                type="text"
                name="question"
                id="question"
                required
            >
        </div>

        <div>
            <label for="inputType">Input Type</label>

            <select name="inputType" id="inputType">
                <option value="multiple-choice">
                    Multiple Choice
                </option>
                <option value="true-false">
                    True / False
                </option>
            </select>
        </div>

        <p>Answers</p>

        <div id="answers"></div>

        <button type="button" id="addAnswer">
            Add Answer
        </button>

        <input
            type="submit"
            name="addQuestion"
            value="Add Question"
        >
    </form>

    <?php if (count($_SESSION['quiz']['questions']) > 0): ?>
        <form method="post" action="create.php">
            <input
                type="submit"
                name="createQuiz"
                value="Create Quiz"
            >
        </form>
    <?php endif; ?>

<?php endif; ?>

<script>
const inputType = document.getElementById('inputType');
const answersContainer = document.getElementById('answers');
const addAnswerButton = document.getElementById('addAnswer');

function createAnswerRow(index, value = '') {
    const row = document.createElement('div');

    row.className = 'answer-row';

    row.innerHTML = `
        <input
            type="radio"
            name="correct"
            value="${index}"
            ${index === 0 ? 'required' : ''}
        >

        <input
            type="text"
            name="answer[${index}]"
            value="${value}"
            placeholder="Answer ${index + 1}"
            required
        >

        <button type="button" class="remove-answer">
            Remove
        </button>
    `;

    row.querySelector('.remove-answer').addEventListener(
        'click',
        function () {
            row.remove();
            renumberAnswers();
        }
    );

    return row;
}

function renumberAnswers() {
    const rows = answersContainer.querySelectorAll('.answer-row');

    rows.forEach(function (row, index) {
        const radio = row.querySelector('input[type="radio"]');
        const text = row.querySelector('input[type="text"]');
        const removeButton = row.querySelector('.remove-answer');

        radio.value = index;
        radio.required = index === 0;

        text.name = `answer[${index}]`;
        text.placeholder = `Answer ${index + 1}`;

        removeButton.disabled = rows.length <= 2;
    });

    addAnswerButton.disabled =
        inputType.value === 'true-false' || rows.length >= 6;
}

function renderAnswers() {
    answersContainer.innerHTML = '';

    if (inputType.value === 'true-false') {
        answersContainer.appendChild(createAnswerRow(0, 'True'));
        answersContainer.appendChild(createAnswerRow(1, 'False'));
    } else {
        answersContainer.appendChild(createAnswerRow(0));
        answersContainer.appendChild(createAnswerRow(1));
    }

    renumberAnswers();
}

inputType.addEventListener('change', renderAnswers);

addAnswerButton.addEventListener('click', function () {
    const rows = answersContainer.querySelectorAll('.answer-row');

    if (inputType.value !== 'true-false' && rows.length < 6) {
        answersContainer.appendChild(createAnswerRow(rows.length));
        renumberAnswers();
    }
});

renderAnswers();
</script>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>