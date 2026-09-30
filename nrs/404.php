<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireAccount('assignment');

if (isset($_SESSION['quizName'])) {
    unset($_SESSION['quizName']);
}
?>

<!DOCTYPE html>
<html lang="en">
    
<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="404.css" /> 
</head>

<body>
    <h2>404</h2>
    <p>Whoops! The page you're looking for doesn't exist.</p>

    <form method="get" action="teacher.php">
        <input type="submit" value="Go Back" />
    </form>

    <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>