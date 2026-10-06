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
'questions' => []
    ];
}


$error = '';


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


// Add a question
if (isset($_POST['addQuestion']))
{
    $question = trim($_POST['question'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';
    $postedAnswers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    $correct = intval($_POST['correct'] ?? -1);


    $answers = [];
    foreach ($postedAnswers as $answer)
    {
        $answers[] = trim((string)$answer);
    }


    if ($question === '')
    {
        $error = 'Please enter a question.';
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
        $_SESSION['quiz']['questions'][] = [
'question' => $question,
'inputType' => $inputType,
'answers' => $answers,
'correct' => $correct
        ];


header("Location: create.php");
exit();
    }
}


// Create the quiz
if (isset($_POST['createQuiz']))
{
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
            $quizJson = json_encode($_SESSION['quiz'], JSON_UNESCAPED_UNICODE);
            if ($quizJson === false)
            {
                $error = 'Unable to format the quiz data.';
            }
            else
            {
                $insertSql = '
                    INSERT INTO "QUIZ" (
                        "CREATOR_ID", "TITLE", "DESCRIPTION", "QUIZ_JSON"
                    ) VALUES (:creator_id, :title, NULL, :quiz_json)
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


</div>


<?php
        }
    }
?>


<!-- STEP 2: Add another question -->


<h2>Add a Question</h2>


<form method="post" action="create.php">


<div>
    <label for="question"> Question </label>


    <input
    type="text"
    name="question"
    id="question"
    required="required"
    />
</div>


<div>
    <label for="inputType">Input Type</label>


    <select name="inputType" id="inputType">
        <option value="multiple-choice"> Multiple Choice </option>
        <option value="true-false"> True / False </option>
    </select>
</div>


<div>
    <p>Answers</p>
    <div id="answers"></div>
</div>

<button type="button" id="addAnswer">Add Answer</button>


    <div>
        <input
        type="submit"
        name="addQuestion"
        value="Add Question"
        />
    </div>


</form>


<?php
// Only show Create button after at least one question exists
if (count($_SESSION['quiz']['questions']) > 0)
    {
?>


<form method="post" action="create.php">


<input
    type="submit"
    name="createQuiz"
    value="Create Quiz"
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

function createAnswerRow(index, value = '') {
    const row = document.createElement('div');
    const correct = document.createElement('input');
    const answer = document.createElement('input');
    const remove = document.createElement('button');

    row.className = 'answer-row';

    correct.type = 'radio';
    correct.name = 'correct';
    correct.value = index;
    correct.required = index === 0;

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
    answersContainer.replaceChildren();

    if (inputType.value === 'true-false') {
        answersContainer.append(
            createAnswerRow(0, 'True'),
            createAnswerRow(1, 'False')
        );
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
        if (inputType.value !== 'true-false' && rows.length < 6) {
            answersContainer.appendChild(createAnswerRow(rows.length));
            renumberAnswers();
        }
    });

    renderAnswers();
}
</script>

</body>
</html>