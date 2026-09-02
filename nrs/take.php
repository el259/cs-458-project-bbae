<?php

session_start();

$_SESSION = [];
session_destroy();

?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/2/26
-->

<head>
	<title>HumGlot</title>
	<meta charset="utf-8" />

	<script src="index.js" defer="defer"></script>
</head>

<body>
	<form method="get" action="index.html">
		<input type="submit" value="Go Back" />
	</form>
<?php
$path = 'quizzes/*.json';
$files = glob($path);

if(count($files) <= 0)
{
?>
	<p>Error loading quizzes. Try again</p>
<?php
	exit;
}

for($i = 0; $i < count($files); $i++)
{
	$files[$i] = basename($files[$i], '.json');
}

?>
	<p>Choose a quiz</p>

	<form method="post" action="quiz.php">
<?php
	foreach($files as $file)
	{
?>
		<div>
			<input type="submit" name="quizName" value="<?= $file ?>" />
		</div>
<?php
	}
?>
	</form>
</body>
</html>
