<?php
session_start();


if (!isset($_SESSION['account']))
{
header("Location: index.php");
exit();
}


$error = '';


if(!isset($_SESSION['quizName']))
{
if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
if(isset($_POST['quizName']))
        {
            $_SESSION['quizName'] = trim($_POST['quizName']);
        }
else
        {
            $error = 'No quiz selected. Try Again';
        }
    }
else
    {
        $error = 'Error loading quiz. Try Again';
    }
}


$questions = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;


if($error === '' && isset($_SESSION['quizName']))
{
    $name = $_SESSION['quizName'];


    if (!preg_match('/^[A-Za-z0-9_-]+$/', $name))
    {
        $error = 'Error loading quiz. Try Again';
    }
    else
    {
        $filepath = __DIR__ . '/quizzes/' . $name . '.json';


        if(!is_file($filepath))
        {
            $error = 'Error loading quiz. Try Again';
        }
        else
        {
            $jsonString = file_get_contents($filepath);
            $data = json_decode($jsonString, true);


            if(isset($data['questions']) && is_array($data['questions']))
            {
                $rawQuestions = $data['questions'];


                for($q = 0; $q < count($rawQuestions); $q++)
                {
                    if(is_array($rawQuestions[$q]) && isset($rawQuestions[$q]['question']))
                    {
                        $optionList = isset($rawQuestions[$q]['answers']) && is_array($rawQuestions[$q]['answers'])
                            ? $rawQuestions[$q]['answers']
                            : [];
                        $correctIndex = isset($rawQuestions[$q]['correct'])
                            ? intval($rawQuestions[$q]['correct'])
                            : -1;
                        $questionText = $rawQuestions[$q]['question'];
                    }
                    else
                    {
                        $optionList = [];
                        $correctIndex = -1;
                        $questionText = (string)$rawQuestions[$q];


                        if(isset($data['answers'][$q]) && is_array($data['answers'][$q]))
                        {
                            $optionList = $data['answers'][$q];
                        }


                        if(isset($data['correct'][$q]))
                        {
                            $correctValue = $data['correct'][$q];


                            if(is_int($correctValue) || (is_string($correctValue) && ctype_digit($correctValue)))
                            {
                                $correctIndex = intval($correctValue);
                            }
                            else
                            {
                                $found = array_search($correctValue, $optionList, true);
                                $correctIndex = ($found === false) ? -1 : $found;
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


            if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guesses']) && is_array($_POST['guesses']))
            {
                $guesses = $_POST['guesses'];
            }


            if(count($questions) > 0 && count($guesses) == count($questions))
            {
                $showScore = true;


                for($i = 0; $i < count($questions); $i++)
                {
                    $correctIndex = isset($questions[$i]['correct']) ? intval($questions[$i]['correct']) : -1;


                    if(isset($guesses[$i]) && intval($guesses[$i]) === $correctIndex)
                    {
                        $totalCorrect++;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    QUIZ.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

    <form method="get" action="take.php">
        <input type="submit" value="Go Back" />
    </form>


<?php
if($error !== '')
{
?>
<p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php
}
else if(count($questions) <= 0)
{
?>
<p>Error loading quiz. Try Again</p>
<?php
}
else
{
if($showScore)
    {
        $percent = ($totalCorrect / count($questions)) * 100;
?>
<p> Percent: <?= htmlspecialchars((string)$percent, ENT_QUOTES, 'UTF-8') ?></p>
<?php
    }
?>


<form method="post" action="quiz.php">
<?php
for($i = 0; $i < count($questions); $i++)
    {
        $question = isset($questions[$i]['question']) ? $questions[$i]['question'] : '';
        $options = isset($questions[$i]['answers']) && is_array($questions[$i]['answers']) ? $questions[$i]['answers'] : [];
        $correctIndex = isset($questions[$i]['correct']) ? intval($questions[$i]['correct']) : -1;
?>
<div>
<p><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></p>
<?php
for($o = 0; $o < count($options); $o++)
        {
            $optionId = 'q' . $i . 'o' . $o;
            $isChecked = isset($guesses[$i]) && intval($guesses[$i]) === $o;
?>
<div>
<input type="radio" name="guesses[<?= $i ?>]" value="<?= $o ?>" id="<?= $optionId ?>" required="required"
<?php
if($isChecked)
            {
?>
checked="checked"
<?php
            }
?>
/>
<label for="<?= $optionId ?>"><?= htmlspecialchars((string)$options[$o], ENT_QUOTES, 'UTF-8') ?></label>
</div>
<?php
        }


if($showScore)
        {
if(isset($guesses[$i]) && intval($guesses[$i]) === $correctIndex)
            {
?>
<p class="correct">Correct!</p>
<?php
            }
else
            {
?>
<p class="incorrect">Incorrect</p>
<?php
            }
        }
?>
</div>
<?php
    }
?>
<input type="submit" value="Submit" />
</form>
<?php
}
?>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
