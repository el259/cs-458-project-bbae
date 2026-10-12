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

// Starting a brand-new quiz from a classroom link — clear any prior edit state
if (isset($_GET['id']) && intval($_GET['id']) > 0)
{
    $_SESSION['quiz'] = [
        'name' => '',
        'questions' => [],
        'editing' => -1,
        'classroom_id' => intval($_GET['id']),
        'due_at' => null,
        'quiz_id' => null
    ];
}

// Resolve classroom ID from (in order):
// 1. ?id= query param
// 2. $_SESSION['classroomId'] (set by viewassignments.php)
// 3. already-stored value in the quiz session
$requestedClassroomId = intval($_GET['id'] ?? 0);
if ($requestedClassroomId <= 0 && isset($_SESSION['classroomId']))
{
    $requestedClassroomId = intval($_SESSION['classroomId']);
}
if ($requestedClassroomId <= 0 && isset($_SESSION['quiz']['classroom_id']))
{
    $requestedClassroomId = intval($_SESSION['quiz']['classroom_id']);
}

if ($requestedClassroomId > 0)
{
    $databaseConnection = hum_conn_no_login();
    $verifySql = '
        SELECT c."CLASSROOM_ID"
        FROM "CLASSROOM" c
        JOIN "CLASSROOM_MEMBERSHIP" m
          ON m."CLASSROOM_ID" = c."CLASSROOM_ID"
        WHERE c."CLASSROOM_ID" = :classroom_id
          AND m."USER_ID" = :user_id
          AND m."ROLE" = \'teacher\'
    ';
    $verifyStatement = oci_parse($databaseConnection, $verifySql);
    $userId = $_SESSION['user_id'];
    oci_bind_by_name($verifyStatement, ':classroom_id', $requestedClassroomId);
    oci_bind_by_name($verifyStatement, ':user_id', $userId);

    if (oci_execute($verifyStatement) && oci_fetch_assoc($verifyStatement) !== false)
    {
        if (!isset($_SESSION['quiz']))
        {
            $_SESSION['quiz'] = [
                'name' => '',
                'questions' => [],
                'editing' => -1,
                'classroom_id' => $requestedClassroomId,
                'due_at' => null
            ];
        }
        else
        {
            $_SESSION['quiz']['classroom_id'] = $requestedClassroomId;
        }
    }
    oci_free_statement($verifyStatement);
    oci_close($databaseConnection);
}

// Start a new quiz session if needed
if (!isset($_SESSION['quiz']))
{
    $_SESSION['quiz'] = [
        'name' => '',
        'questions' => [],
        'editing' => -1,
        'classroom_id' => null,
        'due_at' => null
    ];
}

if (!isset($_SESSION['quiz']['editing']))
{
    $_SESSION['quiz']['editing'] = -1;
}

if (!isset($_SESSION['quiz']['classroom_id']))
{
    $_SESSION['quiz']['classroom_id'] = null;
}

if (!isset($_SESSION['quiz']['due_at']))
{
    $_SESSION['quiz']['due_at'] = null;
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


        header("Location: create_quiz.php");
        exit();
    }
}


// Go back to the quiz name
if (isset($_POST['editQuizName']))
{
    $_SESSION['quiz']['name'] = '';
    $_SESSION['quiz']['editing'] = -1;
    header("Location: create_quiz.php");
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
        header("Location: create_quiz.php");
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

    header("Location: create_quiz.php");
    exit();
}


// Leave the form without writing the open question
if (isset($_POST['cancelEdit']))
{
    $_SESSION['quiz']['editing'] = -1;
    header("Location: create_quiz.php");
    exit();
}


// Add a question, or replace the one being edited
if (isset($_POST['addQuestion']))
{
    $question = trim($_POST['question'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';
    $postedAnswers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    $correct = intval($_POST['correct'] ?? -1);
    $fillAnswer = trim($_POST['fillAnswer'] ?? '');
    $ignoreDiacritics = isset($_POST['ignoreDiacritics']);
    $allowTypos = isset($_POST['allowTypos']);
    $replaceIndex = intval($_POST['editing'] ?? -1);
    $existingImage = trim($_POST['existingImage'] ?? '');


    $answers = [];
    foreach ($postedAnswers as $answer)
    {
        $answers[] = trim((string)$answer);
    }

    // Handle optional question image
    $imagePath = $existingImage;
    if (isset($_FILES['questionImage']) && is_array($_FILES['questionImage']) && $_FILES['questionImage']['error'] !== UPLOAD_ERR_NO_FILE)
    {
        $uploadError = $_FILES['questionImage']['error'];
        if ($uploadError !== UPLOAD_ERR_OK)
        {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'Image is larger than the server allows (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'Image is larger than the form allows.',
                UPLOAD_ERR_PARTIAL => 'Image was only partially uploaded. Try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the temporary file.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.'
            ];
            $error = $messages[$uploadError] ?? 'Upload failed (error code ' . $uploadError . ').';
        }
        else
        {
            $tmp = $_FILES['questionImage']['tmp_name'];
            $info = @getimagesize($tmp);
            $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
            if ($info === false || !in_array($info[2], $allowed, true))
            {
                $error = 'Please upload a JPG, PNG, GIF, or WebP image.';
            }
            else if ($_FILES['questionImage']['size'] > 5 * 1024 * 1024)
            {
                $error = 'Image must be 5 MB or smaller.';
            }
            else
            {
                $ext = image_type_to_extension($info[2], false) ?: 'jpg';
                $uploadDir = __DIR__ . '/uploads/quiz_images';
                if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true))
                {
                    $error = 'Cannot create upload folder: ' . $uploadDir . '. Create it and make it writable by the web server.';
                }
                else if (!is_writable($uploadDir))
                {
                    $error = 'Upload folder is not writable: ' . $uploadDir . '. Run: chmod 775 uploads/quiz_images';
                }
                else
                {
                    $filename = 'q_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($tmp, $dest))
                    {
                        $imagePath = 'uploads/quiz_images/' . $filename;
                    }
                    else
                    {
                        $error = 'Unable to save the image to ' . $dest . '. Check folder permissions.';
                    }
                }
            }
        }
    }

    $saved = null;

    if ($error === '' && $question === '')
    {
        $error = 'Please enter a question.';
    }
    else if ($error === '' && $inputType === 'fill-in')
    {
        if ($fillAnswer === '')
        {
            $error = 'Please enter the correct answer.';
        }
        else
        {
            $saved = [
                'question' => $question,
                'description' => $description,
                'image' => $imagePath,
                'inputType' => 'fill-in',
                'answer' => $fillAnswer,
                'ignoreDiacritics' => $ignoreDiacritics,
                'allowTypos' => $allowTypos
            ];
        }
    }
    else if ($error === '' && $inputType !== 'multiple-choice' && $inputType !== 'true-false')
    {
        $error = 'Invalid input type.';
    }
    else if ($error === '' && count($answers) < 2)
    {
        $error = 'Please enter at least two answers.';
    }
    else if ($error === '' && count($answers) > 6)
    {
        $error = 'A question cannot have more than six answers.';
    }
    else if ($error === '' && $inputType === 'true-false' && count($answers) !== 2)
    {
        $error = 'True/false questions must have exactly two answers.';
    }
    else if ($error === '' && in_array('', $answers, true))
    {
        $error = 'Answer choices cannot be empty.';
    }
    else if ($error === '' && !array_key_exists($correct, $answers))
    {
        $error = 'Please select a correct answer.';
    }
    else if ($error === '')
    {
        $saved = [
            'question' => $question,
            'description' => $description,
            'image' => $imagePath,
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
        header("Location: create_quiz.php");
        exit();
    }
}


// Create the quiz
if (isset($_POST['createQuiz']))
{
    $question = trim($_POST['question'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $inputType = $_POST['inputType'] ?? 'multiple-choice';
    $postedAnswers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    $correct = intval($_POST['correct'] ?? -1);
    $fillAnswer = trim($_POST['fillAnswer'] ?? '');
    $ignoreDiacritics = isset($_POST['ignoreDiacritics']);
    $allowTypos = isset($_POST['allowTypos']);
    $existingImage = trim($_POST['existingImage'] ?? '');

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
            'description' => $description,
            'image' => $existingImage,
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
            'description' => $description,
            'image' => $existingImage,
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
    $dueAt = trim($_POST['dueAt'] ?? ($_SESSION['quiz']['due_at'] ?? ''));
    $_SESSION['quiz']['due_at'] = $dueAt !== '' ? $dueAt : null;

    if ($quizName === '' || count($questions) === 0)
    {
        $error = 'You must give the quiz a name and add at least one question.';
    }
    else
    {
        $databaseConnection = hum_conn_no_login();
        if ($databaseConnection === false)
        {
            $error = 'Unable to connect to the database. Check hum_conn_no_login().';
        }
        else
        {
            // Classroom ID comes from the quiz session (set via ?id= and verified above)
            $classroomId = $_SESSION['quiz']['classroom_id'] ?? null;
            if ($classroomId === null || $classroomId === '' || intval($classroomId) <= 0)
            {
                $error = 'No classroom selected. Open this page from a classroom (create_quiz.php?id=CLASSROOM_ID).';
            }
            else
            {
                $quizId = intval($_SESSION['quiz']['quiz_id'] ?? 0);
                if ($quizId > 0)
                {
                    $checkSql = '
                        SELECT "QUIZ_ID"
                        FROM "QUIZ"
                        WHERE "CLASSROOM_ID" = :classroom_id
                          AND "TITLE" = :title
                          AND "QUIZ_ID" != :quiz_id
                    ';
                }
                else
                {
                    $checkSql = '
                        SELECT "QUIZ_ID"
                        FROM "QUIZ"
                        WHERE "CLASSROOM_ID" = :classroom_id AND "TITLE" = :title
                    ';
                }
                $checkStatement = oci_parse($databaseConnection, $checkSql);
                if ($checkStatement === false)
                {
                    $e = oci_error($databaseConnection);
                    $error = 'Unable to parse quiz check query: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
                }
                else
                {
                    oci_bind_by_name($checkStatement, ':classroom_id', $classroomId);
                    oci_bind_by_name($checkStatement, ':title', $quizName);
                    if ($quizId > 0)
                    {
                        oci_bind_by_name($checkStatement, ':quiz_id', $quizId);
                    }

                    if (!oci_execute($checkStatement))
                    {
                        $e = oci_error($checkStatement);
                        $error = 'Unable to check for existing quizzes: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
                    }
                    else if (oci_fetch_assoc($checkStatement) !== false)
                    {
                        $error = 'A quiz with that name already exists in this classroom.';
                    }
                    else
                    {
                        $storedQuiz = [
                            'name' => $quizName,
                            'questions' => $questions
                        ];
                        $quizJson = json_encode($storedQuiz, JSON_UNESCAPED_UNICODE);

                        if ($quizJson === false)
                        {
                            $error = 'Unable to format the quiz data.';
                        }
                        else
                        {
                            $dueAtValue = $_SESSION['quiz']['due_at'];
                            $editingQuizId = intval($_SESSION['quiz']['quiz_id'] ?? 0);

                            if ($editingQuizId > 0)
                            {
                                if ($dueAtValue !== null && $dueAtValue !== '')
                                {
                                    $saveSql = '
                                        UPDATE "QUIZ"
                                        SET "TITLE" = :title,
                                            "QUIZ_JSON" = :quiz_json,
                                            "DUE_AT" = TO_TIMESTAMP(:due_at, \'YYYY-MM-DD"T"HH24:MI\')
                                        WHERE "QUIZ_ID" = :quiz_id
                                          AND "CLASSROOM_ID" = :classroom_id
                                    ';
                                }
                                else
                                {
                                    $saveSql = '
                                        UPDATE "QUIZ"
                                        SET "TITLE" = :title,
                                            "QUIZ_JSON" = :quiz_json,
                                            "DUE_AT" = NULL
                                        WHERE "QUIZ_ID" = :quiz_id
                                          AND "CLASSROOM_ID" = :classroom_id
                                    ';
                                }
                            }
                            else if ($dueAtValue !== null && $dueAtValue !== '')
                            {
                                $saveSql = '
                                    INSERT INTO "QUIZ" (
                                        "CLASSROOM_ID", "TITLE", "QUIZ_JSON", "DUE_AT"
                                    ) VALUES (
                                        :classroom_id, :title, :quiz_json,
                                        TO_TIMESTAMP(:due_at, \'YYYY-MM-DD"T"HH24:MI\')
                                    )
                                ';
                            }
                            else
                            {
                                $saveSql = '
                                    INSERT INTO "QUIZ" (
                                        "CLASSROOM_ID", "TITLE", "QUIZ_JSON"
                                    ) VALUES (:classroom_id, :title, :quiz_json)
                                ';
                            }
                            $insertStatement = oci_parse($databaseConnection, $saveSql);
                            $quizClob = oci_new_descriptor($databaseConnection, OCI_D_LOB);

                            if ($quizClob === false)
                            {
                                $error = 'Unable to create the quiz data object.';
                            }
                            else
                            {
                                oci_bind_by_name($insertStatement, ':classroom_id', $classroomId);
                                oci_bind_by_name($insertStatement, ':title', $quizName);
                                oci_bind_by_name($insertStatement, ':quiz_json', $quizClob, -1, OCI_B_CLOB);
                                if ($dueAtValue !== null && $dueAtValue !== '')
                                {
                                    oci_bind_by_name($insertStatement, ':due_at', $dueAtValue);
                                }
                                if ($editingQuizId > 0)
                                {
                                    oci_bind_by_name($insertStatement, ':quiz_id', $editingQuizId);
                                }
                                $quizClob->writeTemporary($quizJson, OCI_TEMP_CLOB);

                                if (!oci_execute($insertStatement, OCI_NO_AUTO_COMMIT))
                                {
                                    $e = oci_error($insertStatement);
                                    oci_rollback($databaseConnection);
                                    $error = 'Unable to save the quiz: ' . htmlspecialchars($e['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8');
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

                            if (isset($insertStatement))
                            {
                                oci_free_statement($insertStatement);
                            }
                        }
                    }

                    if (isset($checkStatement))
                    {
                        oci_free_statement($checkStatement);
                    }
                }
            }

            oci_close($databaseConnection);
        }
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
    create_quiz.php
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

    <h1><?= !empty($_SESSION['quiz']['quiz_id']) ? 'Edit Quiz' : 'Create a Quiz' ?></h1>


<?php
    if ($error !== '')
    {
    ?>

    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>


    <?php
    }
    ?>

<!-- Go back to teacher page -->
<form method="post" action="create_quiz.php">
<input type="submit" name="cancelQuiz" value="Cancel" />
</form>


<?php
if ($_SESSION['quiz']['name'] == '')
{
?>


<!-- STEP 1: Name the quiz -->


<h2>1. Name Your Quiz</h2>
    <form method="post" action="create_quiz.php">
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

<form method="post" action="create_quiz.php">
    <input type="submit" name="editQuizName" value="Back to quiz name" />
</form>

<?php
// Display questions that have already been added
if (count($_SESSION['quiz']['questions']) > 0)
{
?>
<h3>Questions</h3>
<div class="question-list">
<?php
foreach ($_SESSION['quiz']['questions'] as $index => $question)
{
?>
<article class="question-card">
    <header class="question-card-header">
        <span class="q-number">Q<?= $index + 1 ?></span>
        <span class="q-type"><?= htmlspecialchars($question['inputType'], ENT_QUOTES, 'UTF-8') ?></span>
    </header>
    <h4><?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') ?></h4>
    <?php if (!empty($question['description'])): ?>
        <p class="q-description"><?= htmlspecialchars($question['description'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($question['image'])): ?>
        <img class="q-image" src="<?= htmlspecialchars($question['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Question image" />
    <?php endif; ?>
    <?php if ($question['inputType'] === 'fill-in'): ?>
        <p>Answer: <strong><?= htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($question['ignoreDiacritics'])): ?><em> (accents optional)</em><?php endif; ?>
            <?php if (!empty($question['allowTypos'])): ?><em> (typos allowed)</em><?php endif; ?>
        </p>
    <?php else: ?>
        <ol class="answer-preview">
            <?php foreach ($question['answers'] as $answerIndex => $answer): ?>
                <li<?= $answerIndex == $question['correct'] ? ' class="is-correct"' : '' ?>>
                    <?= htmlspecialchars($answer, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($answerIndex == $question['correct']): ?><strong> (Correct)</strong><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <form method="post" action="create_quiz.php" class="card-actions">
        <button type="submit" name="editQuestion" value="<?= $index ?>">Edit</button>
        <button type="submit" name="removeQuestion" value="<?= $index ?>" class="danger">Remove</button>
    </form>
</article>
<?php
}
?>
</div>
<?php
}
?>

<h2><?= $editingQuestion === null ? 'Add a Question' : 'Edit Question ' . ($editingIndex + 1) ?></h2>

<form method="post" action="create_quiz.php" enctype="multipart/form-data" class="question-editor">
<input type="hidden" name="editing" value="<?= $editingIndex ?>" />
<input type="hidden" name="existingImage" value="<?= $editingQuestion !== null && !empty($editingQuestion['image']) ? htmlspecialchars($editingQuestion['image'], ENT_QUOTES, 'UTF-8') : '' ?>" />

<div>
    <label for="question">Question</label>
    <input
        type="text"
        name="question"
        id="question"
        required="required"
        placeholder="What is the capital of France?"
        value="<?= $editingQuestion === null ? '' : htmlspecialchars($editingQuestion['question'], ENT_QUOTES, 'UTF-8') ?>"
    />
</div>

<div>
    <label for="description">Description <span class="optional">(optional)</span></label>
    <textarea
        name="description"
        id="description"
        rows="3"
        placeholder="Extra context or instructions shown above the answers"
    ><?= $editingQuestion !== null && !empty($editingQuestion['description']) ? htmlspecialchars($editingQuestion['description'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
</div>

<div>
    <label for="questionImage">Image <span class="optional">(optional)</span></label>
    <?php if ($editingQuestion !== null && !empty($editingQuestion['image'])): ?>
        <img class="q-image preview" src="<?= htmlspecialchars($editingQuestion['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current image" />
        <p class="hint">Upload a new image to replace the current one.</p>
    <?php endif; ?>
    <input type="file" name="questionImage" id="questionImage" accept="image/jpeg,image/png,image/gif,image/webp" />
</div>

<div>
    <label for="inputType">Answer type</label>
    <select name="inputType" id="inputType">
        <option value="multiple-choice" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'multiple-choice' ? 'selected="selected"' : '' ?>>Multiple Choice</option>
        <option value="true-false" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'true-false' ? 'selected="selected"' : '' ?>>True / False</option>
        <option value="fill-in" <?= $editingQuestion !== null && $editingQuestion['inputType'] === 'fill-in' ? 'selected="selected"' : '' ?>>Fill in the blank</option>
    </select>
</div>

<div id="choiceFields">
    <p class="field-label">Answers <span class="hint">— select the correct one</span></p>
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
    <label class="check">
        <input type="checkbox" name="ignoreDiacritics" <?= $editingQuestion !== null && !empty($editingQuestion['ignoreDiacritics']) ? 'checked="checked"' : '' ?> />
        Accept missing accents
    </label>
    <label class="check">
        <input type="checkbox" name="allowTypos" <?= $editingQuestion !== null && !empty($editingQuestion['allowTypos']) ? 'checked="checked"' : '' ?> />
        Accept a small typo
    </label>
</div>

<div class="form-actions">
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
<form method="post" action="create_quiz.php">
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


<form method="post" action="create_quiz.php" class="create-block">
    <input
        type="datetime-local"
        name="dueAt"
        id="dueAt"
        value="<?= htmlspecialchars($_SESSION['quiz']['due_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    />
</div>

<input
    type="submit"
    name="createQuiz"
    value="<?= !empty($_SESSION['quiz']['quiz_id']) ? 'Save Quiz' : 'Create Quiz' ?> (<?= count($_SESSION['quiz']['questions']) ?> questions)"
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
