<?php
	if(!isset($_SESSION['account']))
	{
?>
	<p>Welcome to HumGlot! Please log in to continue</p>
	<form method="post" action="index.php">
		<input type="submit" name="login" value="Log In" />
	</form>
<?php
		exit;
	}
?>
	<form method="post" action="index.php">
		<input type="submit" value="Go back" />
	</form>
