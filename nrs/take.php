<?php
session_start();


if (!isset($_SESSION['account']))
{
header("Location: index.php");
exit();
}


if(isset($_SESSION['quizName']))
{
	unset($_SESSION['quizName']);
}
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    TAKE.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

    <form method="get" action="index.php">
        <input type="submit" value="Go Back" />
    </form>
    
    <?php
    $path = __DIR__ . '/quizzes/*.json';
    $files = glob($path);


    if($files === false || count($files) <= 0)
    {
    ?>
    <p>No quizzes available. Try again later.</p>
    <?php
    }
    else
    {
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
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $file))
            {
                continue;
            }
    ?>
    <div>
    <input type="submit" name="quizName" value="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>" />
    </div>
    <?php
        }
    ?>
    </form>
    <?php
    }
    ?>

    <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
