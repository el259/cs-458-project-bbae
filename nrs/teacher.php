<?php
session_start();


if (!isset($_SESSION['account']) || empty($_SESSION['teacher']))
{
header("Location: index.php");
exit();
}


$path = __DIR__ . '/quizzes/*.json';
$files = glob($path);


if($files === false)
{
    $files = [];
}


for($i = 0; $i < count($files); $i++)
{
    $files[$i] = basename($files[$i], '.json');
}
?>


<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    TEACHER.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style.css" />
</head>


<body>
    <p><?= htmlspecialchars('Welcome, ' . $_SESSION['account'] . '!', ENT_QUOTES, 'UTF-8') ?></p>

    <form method="get" action="create.php">
        <input type="submit" value="Create a quiz" />
    </form>


    <form method="get" action="take.php">
        <input type="submit" value="Take a quiz" />
    </form>


    <form method="post" action="index.php">
        <input type="submit" name="logout" value="Sign Out" />
    </form>


<h2>Your quizzes</h2>


<?php
if(count($files) <= 0)
{
?>
<p>No quizzes yet.</p>
<?php
}
else
{
?>
<ul>
<?php
foreach($files as $file)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $file))
        {
            continue;
        }
?>
<li><?= htmlspecialchars(str_replace('_', ' ', $file), ENT_QUOTES, 'UTF-8') ?></li>
<?php
    }
?>
</ul>
<?php
}
?>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
