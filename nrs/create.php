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
	<form method="get" action="index.html">
		<input type="submit" value="Go Back" />
	</form>
<?php
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST['quizName']))
		{
			$quizName = htmlspecialchars($_POST['quizName']);
			$filepath = 'quizzes/' . str_replace(' ', '_', $quizName) . '.json';

			if(file_exists($filepath))
			{
?>
				<p>Quiz already exists. Try again</p>
<?php
			}
			else
			{
				$questions = [ htmlspecialchars($_POST['question']) ];
				$answers = $_POST['answer'];
				$correct = [ $answers[0][$_POST['correct']] ];

				$data = [
					'questions' => $questions,
					'answers' => $answers,
					'correct' => $correct
				];

				$jsonString = json_encode($data, JSON_PRETTY_PRINT);

				if(file_put_contents($filepath, $jsonString))
				{
?>
					<p>Successfully created quiz!</p>
<?php
				}
				else
				{
?>
					<p>Error creating quiz. Try again</p>
<?php
				}
			}
		}	
	}
?>
	<form method="post" action="create.php">
		<div>
			<label for="name">Name your quiz</label>
			<input type="text" name="quizName" id="name" required="required" />
		</div>
		<div>
			<input type="text" name="question" required="required" />
			<div>
				<div>
					<input type="radio" name="correct" value="0" required="required" />
					<input type="text" name="answer[0][]" required="required" />
				</div>
				<div>
					<input type="radio" name="correct" value="1" required="required" />
					<input type="text" name="answer[0][]" required="required" />
				</div>
				<div>
					<input type="radio" name="correct" value="2" required="required" />
					<input type="text" name="answer[0][]" required="required" />
				</div>
				<div>
					<input type="radio" name="correct" value="3" required="required" />
					<input type="text" name="answer[0][]" required="required" />
				</div>
			</div>
		</div>
		<div>
			<input type="submit" value="Create" />
		</div>
	</form>
</body>
</html>
