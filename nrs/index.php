<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/8/26
-->

<head>
	<title>HumGlot</title>
	<meta charset="utf-8" />

	<!-- <script src="index.js" defer="defer"></script> -->
</head>

<body>
<?php
	$welcome_msg = 'Welcome to HumGlot';

	$login = array_key_exists('login', $_POST);
	$signup = array_key_exists('signup', $_POST);

	if($login || $signup)
	{
		$name = htmlspecialchars($_POST['account']);

		$filepath = 'accounts.json';

		if(file_exists($filepath))
		{
			$jsonString = file_get_contents($filepath);
			$data = json_decode($jsonString, true);

			$names = $data['names'];
			$teacher = $data['teacher'];

			if($login)
			{
				$index = array_search($name, $names, true);

				if($index === false)
				{
?>
					<p>No account found, try again</p>
<?php
				}
				else
				{
					$_SESSION['account'] = $name;
					$_SESSION['teacher'] = $teacher[$index];
				}
			}
			else if($signup)
			{
				if(!in_array($name, $names))
				{
					$toName = $name;
					$toTeacher = false;

					$names[] = $toName;
					$teacher[] = $toTeacher;

					$data = [
						'names' => $names,
						'teacher' => $teacher
					];
					$jsonString = json_encode($data, JSON_PRETTY_PRINT);
					file_put_contents($filepath, $jsonString);

					$_SESSION['account'] = $toName;
					$_SESSION['teacher'] = $toTeacher;
				}
			}	
		}
	}

	

	if(isset($_SESSION['account']))
	{
		$welcome_msg = $welcome_msg . ', ' . $_SESSION['account'];
	}
?>
	<p><?= $welcome_msg . '!' ?></p>
<?php
	if(!isset($_SESSION['account']))
	{
?>
	<form method="post" action="index.php">
		<div>
			<label for="acc">Username</label>
			<input type="text" name="account" id="acc" required="required" />
		</div>
		<input type="submit" name="login" value="Log In" />
		<input type="submit" name="signup" value="Sign Up" />
	</form>	
<?php
	}
?>
</body>
</html>
