<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireTeacher('dashboard');


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
    else if (!isset($answers[$correct]) || $answers[$correct] === '')
    {
        $error = 'Please mark a valid correct answer.';
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
    $quizName = $_SESSION['quiz']['name'];


if ($quizName != '' && count($_SESSION['quiz']['questions']) > 0)
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '_', str_replace(' ', '_', $quizName));
        $slug = trim($slug, '_');


        if ($slug === '')
        {
            $error = 'Quiz name must include letters or numbers.';
        }
        else
        {
            $quizDir = __DIR__ . '/quizzes';


            if (!is_dir($quizDir))
            {
                mkdir($quizDir, 0755, true);
            }


            $filepath = $quizDir . '/' . $slug . '.json';


if (file_exists($filepath))
        {
            $error = 'Quiz already exists. Try again.';
        }
else
        {
            $jsonString = json_encode($_SESSION['quiz'], JSON_PRETTY_PRINT);


if (file_put_contents($filepath, $jsonString))
            {
unset($_SESSION['quiz']);


header("Location: teacher.php");
exit();
            }
else
            {
                $error = 'Error creating quiz. Try again.';
            }
        }
        }
    }
else
    {
        $error = 'You must give the quiz a name and add at least one question.';
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
    <link rel="stylesheet" href="global.css" />
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


    <div>
        <input
        type="radio"
        name="correct"
        value="0"
        required="required"
        />


        <input
        type="text"
        name="answer[0]"
        placeholder="Answer 1"
        required="required"
        />
    </div>


    <div>
        <input
        type="radio"
        name="correct"
        value="1"
        />


        <input
        type="text"
        name="answer[1]"
        placeholder="Answer 2"
        required="required"
        />
    </div>


    <div>
        <input
        type="radio"
        name="correct"
        value="2"
        />


        <input
        type="text"
        name="answer[2]"
        placeholder="Answer 3"
        required="required"
        />
    </div>


    <div>
        <input
        type="radio"
        name="correct"
        value="3"
        />


        <input
        type="text"
        name="answer[3]"
        placeholder="Answer 4"
        required="required"
        />
    </div>

</div>


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

</body>
</html>