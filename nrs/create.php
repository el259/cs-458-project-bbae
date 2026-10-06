<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id']))
{
    header('Location: signin.php');
    exit();
}


// Start a new quiz
if (!isset($_SESSION['quiz']))
{
    $_SESSION['quiz'] = [
        'name' => '',
        'questions' => [],
        'editing' => -1
    ];
}

if (!isset($_SESSION['quiz']['editing']))
{
    $_SESSION['quiz']['editing'] = -1;
}


$error = '';
$editingIndex = intval($_SESSION['quiz']['editing']);
$editingQuestion = null;

if ($editingIndex >= 0 && isset($_SESSION['quiz']['questions'][$editingIndex]))
{
    $editingQuestion = $_SESSION['quiz']['questions'][$editingIndex];
}


// Save quiz name
if (isset($_POST['setQuizName']))
{
    $quizName = trim($_POST['quizName'] ?? '');


    if ($quizName === '')
    {
        $error = 'Please enter a quiz name.';
    }
    else
    {
        $_SESSION['quiz']['name'] = $quizName;


        header("Location: create.php");
        exit();
    }
}


// Go back to the quiz name
if (isset($_POST['editQuizName']))
{
    $_SESSION['quiz']['name'] = '';
    $_SESSION['quiz']['editing'] = -1;
    header("Location: create.php");
    exit();
}


// Reopen a question that was already added
if (isset($_POST['editQuestion']))
{
    $index = intval($_POST['editQuestion']);

    if (!isset($_SESSION['quiz']['questions'][$index]))
    {
        $error = 'That question could not be found.';
    }
    else
    {
        $_SESSION['quiz']['editing'] = $index;
        header("Location: create.php");
        exit();
    }
}


// Drop a question and stay on the list
if (isset($_POST['removeQuestion']))
{
    $index = intval($_POST['removeQuestion']);

    if (isset($_SESSION['quiz']['questions'][$index]))
    {
        array_splice($_SESSION['quiz']['questions'], $index, 1);

        if ($_SESSION['quiz']['editing'] === $index)
        {
            $_SESSION['quiz']['editing'] = -1;
        }
        else if ($_SESSION['quiz']['editing'] > $index)
        {
            $_SESSION['quiz']['editing']--;
        }
    }

    header("Location: create.php");
    exit();
}


// Leave the form without writing the open question
if (isset($_POST['cancelEdit']))
{
    $_SESSION['quiz']['editing'] = -1;
    header("Location: create.php");
    exit();
}


// Add a question, or replace the one being edited
if (isset($_POST['addQuestion']))
{
    $question = trim($_POST['question'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';
    $postedAnswers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    $correct = intval($_POST['correct'] ?? -1);
    $fillAnswer = trim($_POST['fillAnswer'] ?? '');
    $ignoreDiacritics = isset($_POST['ignoreDiacritics']);
    $allowTypos = isset($_POST['allowTypos']);
    $replaceIndex = intval($_POST['editing'] ?? -1);


    $answers = [];
    foreach ($postedAnswers as $answer)
    {
        $answers[] = trim((string)$answer);
    }


    $saved = null;

    if ($question === '')
    {
        $error = 'Please enter a question.';
    }
    else if ($inputType === 'fill-in')
    {
        if ($fillAnswer === '')
        {
            $error = 'Please enter the correct answer.';
        }
        else
        {
            $saved = [
                'question' => $question,
                'inputType' => 'fill-in',
                'answer' => $fillAnswer,
                'ignoreDiacritics' => $ignoreDiacritics,
                'allowTypos' => $allowTypos
            ];
        }
    }
    else if ($inputType !== 'multiple-choice' && $inputType !== 'true-false')
    {
        $error = 'Invalid input type.';
    }
    else if (count($answers) < 2)
    {
        $error = 'Please enter at least two answers.';
    }
    else if (count($answers) > 6)
    {
        $error = 'A question cannot have more than six answers.';
    }
    else if ($inputType === 'true-false' && count($answers) !== 2)
    {
        $error = 'True/false questions must have exactly two answers.';
    }
    else if (in_array('', $answers, true))
    {
        $error = 'Answer choices cannot be empty.';
    }
    else if (!array_key_exists($correct, $answers))
    {
        $error = 'Please select a correct answer.';
    }
    else
    {
        $saved = [
            'question' => $question,
            'inputType' => $inputType,
            'answers' => $answers,
            'correct' => $correct
        ];
    }

    if ($saved !== null)
    {
        $isEdit = $replaceIndex >= 0
            && $replaceIndex === intval($_SESSION['quiz']['editing'] ?? -1)
            && isset($_SESSION['quiz']['questions'][$replaceIndex]);

        if ($isEdit)
        {
            $_SESSION['quiz']['questions'][$replaceIndex] = $saved;
        }
        else
        {
            $_SESSION['quiz']['questions'][] = $saved;
        }

        $_SESSION['quiz']['editing'] = -1;
        header("Location: create.php");
        exit();
    }
}


// Create the quiz
if (isset($_POST['createQuiz']))
{
    $question = trim($_POST['question'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';
    $postedAnswers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    $correct = intval($_POST['correct'] ?? -1);
    $fillAnswer = trim($_POST['fillAnswer'] ?? '');
    $ignoreDiacritics = isset($_POST['ignoreDiacritics']);
    $allowTypos = isset($_POST['allowTypos']);

    $answers = [];
    foreach ($postedAnswers as $answer)
    {
        $answers[] = trim((string) $answer);
    }

    $pending = null;

    if ($question !== '' && $inputType === 'fill-in' && $fillAnswer !== '')
    {
        $pending = [
            'question' => $question,
            'inputType' => 'fill-in',
            'answer' => $fillAnswer,
            'ignoreDiacritics' => $ignoreDiacritics,
            'allowTypos' => $allowTypos
        ];
    }
    else if (
        $question !== ''
        && ($inputType === 'multiple-choice' || $inputType === 'true-false')
        && count($answers) >= 2
        && !in_array('', $answers, true)
        && array_key_exists($correct, $answers)
    )
    {
        $pending = [
            'question' => $question,
            'inputType' => $inputType,
            'answers' => $answers,
            'correct' => $correct
        ];
    }

    if ($pending !== null)
    {
        $_SESSION['quiz']['questions'][] = $pending;
    }

    $quizName = trim($_SESSION['quiz']['name']);
    $questions = $_SESSION['quiz']['questions'];

    if ($quizName === '' || count($questions) === 0)
    {
        $error = 'You must give the quiz a name and add at least one question.';
    }
    else
    {
        $databaseConnection = hum_conn_no_login();
        $checkSql = '
            SELECT "QUIZ_ID"
            FROM "QUIZ"
            WHERE "CREATOR_ID" = :creator_id AND "TITLE" = :title
        ';
        $checkStatement = oci_parse($databaseConnection, $checkSql);
        $creatorId = $_SESSION['user_id'];
        oci_bind_by_name($checkStatement, ':creator_id', $creatorId);
        oci_bind_by_name($checkStatement, ':title', $quizName);

        if (!oci_execute($checkStatement))
        {
            $error = 'Unable to check for existing quizzes.';
        }
        else if (oci_fetch_assoc($checkStatement) !== false)
        {
            $error = 'A quiz with that name already exists.';
        }
        else
        {
            $storedQuiz = [
                'name' => $quizName,
                'questions' => $questions
            ];
            $quizJson = json_encode($storedQuiz, JSON_UNESCAPED_UNICODE);
            $description = (string) count($questions);

            if ($quizJson === false)
            {
                $error = 'Unable to format the quiz data.';
            }
            else
            {
                $insertSql = '
                    INSERT INTO "QUIZ" (
                        "CREATOR_ID", "TITLE", "DESCRIPTION", "QUIZ_JSON"
                    ) VALUES (:creator_id, :title, :description, :quiz_json)
                ';
                $insertStatement = oci_parse($databaseConnection, $insertSql);
                $quizClob = oci_new_descriptor($databaseConnection, OCI_D_LOB);

                if ($quizClob === false)
                {
                    $error = 'Unable to create the quiz data object.';
                }
                else
                {
                    oci_bind_by_name($insertStatement, ':creator_id', $creatorId);
                    oci_bind_by_name($insertStatement, ':title', $quizName);
                    oci_bind_by_name($insertStatement, ':description', $description);
                    oci_bind_by_name($insertStatement, ':quiz_json', $quizClob, -1, OCI_B_CLOB);
                    $quizClob->writeTemporary($quizJson, OCI_TEMP_CLOB);

                    if (!oci_execute($insertStatement, OCI_NO_AUTO_COMMIT))
                    {
                        oci_rollback($databaseConnection);
                        $error = 'Unable to save the quiz.';
                    }
                    else
                    {
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


// Cancel quiz creation
if (isset($_POST['cancelQuiz']))
{
    unset($_SESSION['quiz']);
    header("Location: teacher.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    CREATE.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="create_quiz.css" />
</head>

<body>
    <?php require __DIR__ . '/header.php'; ?>

    <h1>Create a Quiz</h1>


<?php
    if ($error !== '')
    {
    ?>

    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>


    <?php
    }
    ?>

<!-- Go back to teacher page -->
<form method="post" action="create.php">
<input type="submit" name="cancelQuiz" value="Cancel" />
</form>


<?php
if ($_SESSION['quiz']['name'] == '')
{
?>


<!-- STEP 1: Name the quiz -->


<h2>1. Name Your Quiz</h2>
    <form method="post" action="create.php">
    <label for="quizName">Quiz Name</label>
    <input
        type="text"
        name="quizName"
        id="quizName"
        required="required"
    />

    <input
        type="submit"
        name="setQuizName"
        value="Continue"
    />

</form>

<?php
}
else
{
?>

<h2><?= htmlspecialchars($_SESSION['quiz']['name'], ENT_QUOTES, 'UTF-8') ?></h2>

<form method="post" action="create.php">
    <input type="submit" name="editQuizName" value="Back to quiz name" />
</form>

<?php


// Display questions that have already been added
if (count($_SESSION['quiz']['questions']) > 0)
    {
?>


<h3>Questions</h3>


<?php
foreach ($_SESSION['quiz']['questions'] as $index => $question)
        {
?>


<div>
    <p>
    <strong> Question <?= $index + 1 ?>: </strong>
        <?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p>
    Input Type: <?= htmlspecialchars($question['inputType'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php
    if ($question['inputType'] === 'fill-in')
    {
    ?>
    <p>
        Answer: <?= htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8') ?>
        <?php
        if (!empty($question['ignoreDiacritics']))
        {
        ?>
        <strong> (accents optional)</strong>
        <?php
        }
        if (!empty($question['allowTypos']))
        {
        ?>
        <strong> (typos allowed)</strong>
        <?php
        }
        ?>
    </p>
    <?php
    }
    else
    {
    ?>
    <ol>
        <?php
        foreach ($question['answers'] as $answerIndex => $answer)
                    {
        ?>
        <li>
            <?= htmlspecialchars($answer, ENT_QUOTES, 'UTF-8') ?>
            <?php
            if ($answerIndex == $question['correct'])
                            {
            ?>
            <strong> (Correct)</strong>
            <?php
                            }
            ?>
        </li>
        <?php
                    }
        ?>
    </ol>
    <?php
    }
    ?>

    <form method="post" action="create.php">
        <button type="submit" name="editQuestion" value="<?= $index ?>">Edit</button>
        <button type="submit" name="removeQuestion" value="<?= $index ?>">Remove</button>
    </form>


</div>


<?php
        }
    }
?>


<!-- STEP 2: Add another question, or edit the one that was reopened -->


<h2><?= $editingQuestion === null ? 'Add a Question' : 'Edit Question ' . ($editingIndex + 1) ?></h2>


<form method="post" action="create.php">
<input type="hidden" name="editing" value="<?= $editingIndex ?>" />


<div>
    <label for="question"> Question </label>


    <input
    type="text"
    name="question"
    id="question"
    required="required"
    value="<?= $editingQuestion === null ? '' : htmlspecialchars($editingQuestion['question'], ENT_QUOTES, 'UTF-8') ?>"
    />
</div>


<div>
    <label for="inputType">Input Type</label>


    <select name="inputType" id="inputType">
        <option value="multiple-choice" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'multiple-choice' ? 'selected="selected"' : '' ?>> Multiple Choice </option>
        <option value="true-false" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'true-false' ? 'selected="selected"' : '' ?>> True / False </option>
        <option value="fill-in" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'fill-in' ? 'selected="selected"' : '' ?>> Fill in the blank </option>
    </select>
</div>


<div id="choiceFields">
    <p>Answers</p>
    <div id="answers"></div>
    <button type="button" id="addAnswer">Add Answer</button>
</div>


<div id="fillFields">
    <label for="fillAnswer">Correct answer</label>
    <input
        type="text"
        name="fillAnswer"
        id="fillAnswer"
        value="<?= $editingQuestion !== null && $editingQuestion['inputType'] === 'fill-in' ? htmlspecialchars($editingQuestion['answer'], ENT_QUOTES, 'UTF-8') : '' ?>"
    />
    <label>
        <input type="checkbox" name="ignoreDiacritics" <?= $editingQuestion !== null && !empty($editingQuestion['ignoreDiacritics']) ? 'checked="checked"' : '' ?> />
        Accept missing accents
    </label>
    <label>
        <input type="checkbox" name="allowTypos" <?= $editingQuestion !== null && !empty($editingQuestion['allowTypos']) ? 'checked="checked"' : '' ?> />
        Accept a small typo
    </label>
</div>


    <div>
        <input
        type="submit"
        name="addQuestion"
        value="<?= $editingQuestion === null ? 'Add Question' : 'Save Question' ?>"
        />
    </div>


</form>

<?php
if ($editingQuestion !== null)
{
?>
<form method="post" action="create.php">
    <input type="submit" name="cancelEdit" value="Back to new question" />
</form>
<?php
}
?>


<?php
// Only show Create button after at least one question exists
if (count($_SESSION['quiz']['questions']) > 0)
    {
?>


<form method="post" action="create.php">


<input
    type="submit"
    name="createQuiz"
    value="Create Quiz (<?= count($_SESSION['quiz']['questions']) ?> questions)"
/>


</form>


    <?php
        }
    }
    ?>

<?php require __DIR__ . '/footer.php'; ?>

<script>
const inputType = document.getElementById('inputType');
const answersContainer = document.getElementById('answers');
const addAnswerButton = document.getElementById('addAnswer');
const choiceFields = document.getElementById('choiceFields');
const fillFields = document.getElementById('fillFields');
const fillAnswer = document.getElementById('fillAnswer');
const existingQuestion = <?= json_encode($editingQuestion, JSON_UNESCAPED_UNICODE) ?>;

function createAnswerRow(index, value = '', checked = false) {
    const row = document.createElement('div');
    const correct = document.createElement('input');
    const answer = document.createElement('input');
    const remove = document.createElement('button');

    row.className = 'answer-row';

    correct.type = 'radio';
    correct.name = 'correct';
    correct.value = index;
    correct.required = index === 0;
    correct.checked = checked;

    answer.type = 'text';
    answer.name = `answer[${index}]`;
    answer.placeholder = `Answer ${index + 1}`;
    answer.value = value;
    answer.required = true;

    remove.type = 'button';
    remove.textContent = 'Remove';
    remove.addEventListener('click', function () {
        row.remove();
        renumberAnswers();
    });

    row.append(correct, answer, remove);
    return row;
}

function renumberAnswers() {
    const rows = answersContainer.querySelectorAll('.answer-row');
    rows.forEach(function (row, index) {
        const correct = row.querySelector('input[type="radio"]');
        const answer = row.querySelector('input[type="text"]');
        const remove = row.querySelector('button');

        correct.value = index;
        correct.required = index === 0;
        answer.name = `answer[${index}]`;
        answer.placeholder = `Answer ${index + 1}`;
        remove.disabled = rows.length <= 2 || inputType.value === 'true-false';
    });

    addAnswerButton.disabled =
        inputType.value === 'true-false' || rows.length >= 6;
}

function renderAnswers() {
    const isFill = inputType.value === 'fill-in';

    choiceFields.hidden = isFill;
    fillFields.hidden = !isFill;
    fillAnswer.required = isFill;

    answersContainer.replaceChildren();

    if (isFill) {
        addAnswerButton.disabled = true;
        return;
    }

    if (inputType.value === 'true-false') {
        const saved = existingQuestion && existingQuestion.inputType === 'true-false'
            ? existingQuestion
            : null;
        answersContainer.append(
            createAnswerRow(0, saved ? saved.answers[0] : 'True', saved ? saved.correct === 0 : false),
            createAnswerRow(1, saved ? saved.answers[1] : 'False', saved ? saved.correct === 1 : false)
        );
    } else if (existingQuestion && existingQuestion.inputType === 'multiple-choice' && inputType.value === 'multiple-choice') {
        existingQuestion.answers.forEach(function (answer, index) {
            answersContainer.appendChild(createAnswerRow(index, answer, existingQuestion.correct === index));
        });
    } else {
        answersContainer.append(
            createAnswerRow(0),
            createAnswerRow(1)
        );
    }

    renumberAnswers();
}

if (inputType && answersContainer && addAnswerButton) {
    inputType.addEventListener('change', renderAnswers);
    addAnswerButton.addEventListener('click', function () {
        const rows = answersContainer.querySelectorAll('.answer-row');
        if (inputType.value !== 'true-false' && inputType.value !== 'fill-in' && rows.length < 6) {
            answersContainer.appendChild(createAnswerRow(rows.length));
            renumberAnswers();
        }
    });

    renderAnswers();
}
</script>

</body>
</html>
