<?php

session_start();

if(!isset($_SESSION['quizName']))
{
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST['quizName']))
		{
			$_SESSION['quizName'] = htmlspecialchars($_POST['quizName']);
		}
		else
		{
?>
			<p>No quiz selected. Try Again</p>
<?php
			exit;
		}
	}
	else
	{
?>
		<p>Error loading quiz. Try Again</p>
<?php
		exit;
	}
}

?>
<!DOCTYPE html>
<html lang="en">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/2/26
-->

<head>
	<title>HumGlot</title>
	<meta charset="utf-8" />
</head>

<body>
	<form method="get" action="take.php">
		<input type="submit" value="Go Back" />
	</form>

<?php
	$filepath = 'quizzes/';
	$guesses = [];

	if(isset($_SESSION['quizName']))
	{
		$name = htmlspecialchars($_SESSION['quizName']);

		$filepath = $filepath . $name;	
	}
	else
	{
		exit;
	}

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST['guesses']))
		{
			$guesses = $_POST['guesses'];
		}
	}

	$filepath = $filepath . '.json';
	$jsonString = file_get_contents($filepath);
	$data = json_decode($jsonString, true);

	$questions = $data['questions'];
	$answers = $data['answers'];
	$correct = $data['correct'];

	if(count($guesses) == count($correct))
	{
		$totalCorrect = 0;

		for($i = 0; $i < count($guesses); $i++)
		{
			if($guesses[$i] == $correct[$i])
			{
				$totalCorrect++;
			}
		}
?>
		<p> Percent: <?= ($totalCorrect / count($correct)) * 100 ?></p>
<?php
	}
?>

	<form method="post" action="quiz.php">
<?php	
	for($i = 0; $i < count($questions); $i++)
	{
		$question = $questions[$i];
		$options = $answers[$i];
?>
		<div>
		<p><?= $question ?></p>
<?php
		for($o = 0; $o < count($options); $o++)
		{
?>
		<div>
			<input type="radio" name="guesses[<?= $i ?>]" value="<?= $options[$o] ?>" id="<?= $options[$o] ?>" required="required"
<?php
		if(count($guesses) > $i)
		{
			if($options[$o] === $guesses[$i])
			{
?>
	checked="checked"
<?php
			}
		}
?>
/>
			<label for="<?= $options[$o] ?>"><?= $options[$o] ?></label>
		</div>
<?php
		}

		if(count($guesses) > $i)
		{
			if($guesses[$i] === $correct[$i])
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
</body>
</html>
