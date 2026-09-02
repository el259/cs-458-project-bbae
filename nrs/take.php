<!DOCTYPE html>
<html lang="en">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 8/31/26
-->

<head>
	<title>HumGlot</title>
	<meta charset="utf-8" />
</head>

<body>
	<form method="post" action="index.html">
		<input type="submit" value="Go back" />
	</form>

<?php
	$questions = [ 'What is the best letter?', 'What is the square root of pi?' ];
	$answers = [ ['a', 'b', 'c', 'd'], ['3.14', 'apple', '1.77', '0'] ];
	$correct = [ 'a', '1.77' ];
	
	$guesses = [];

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST['guesses']))
		{
			$guesses = $_POST['guesses'];

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
	}

	$toCheck = [ 'a', 'b', 'c', 'd' ];
	$checkStrings = [ '', '', '', ''];

	for($i = 0; $i < count($toCheck); $i++)
	{
		if($toCheck[$i] === $guesses[0])
		{
			$checkStrings[$i] = 'checked="checked"';
			break;
		}
	}
?>

	<form method="post" action="take.php">
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
			<!-- <input type="radio" name="guesses[]" value="b" id="b" <?= $checkStrings[1]; ?>/> 
			<label for="b">B</label> -->
		</div>
<?php
	}
?>
		<input type="submit" value="Submit" />
	</form>
</body>
</html>
