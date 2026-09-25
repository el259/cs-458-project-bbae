<?php
session_start();


if (!isset($_SESSION['account']))
{
header("Location: index.php");
exit();
}


if (!empty($_SESSION['teacher']))
{
header("Location: teacher.php");
exit();
}
?>


<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    STUDENT.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

    <p><?= htmlspecialchars('Welcome, ' . $_SESSION['account'] . '!', ENT_QUOTES, 'UTF-8') ?></p>


    <form method="get" action="take.php">
    <input type="submit" value="Take a quiz" />
    </form>


    <form method="post" action="index.php">
    <input type="submit" name="logout" value="Sign Out" />
    </form>

    <?php require __DIR__ . '/footer.php'; ?>

    </body>
</html>