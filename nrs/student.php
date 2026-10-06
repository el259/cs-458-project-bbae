<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireAccount('dashboard');

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="student.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

    <h2><?= htmlspecialchars('Welcome, ' . $_SESSION['account'] . '!', ENT_QUOTES, 'UTF-8') ?></h2>


    <h3>Your classrooms</h3>

        <p>None.</p>

    <h3>Your assignments</h3>

        <p>None.</p>

    <form method="get" action="take.php">
        <input type="submit" value="Take a quiz" />
    </form>


    <form method="post" action="signin.php">
        <input type="submit" name="logout" value="Sign Out" />
    </form>

    <?php require __DIR__ . '/footer.php'; ?>

    </body>
</html>