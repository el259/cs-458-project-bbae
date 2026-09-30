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
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/29/26
    TAKE.PHP
-->

<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="take.css" />
</head>

<body>
<?php require __DIR__ . '/header.php'; ?>

<?php
$path = __DIR__ . '/quizzes/*.json';
$files = glob($path);

if ($files === false || count($files) <= 0) {
?>
    <p>No quizzes available. Try again later.</p>
<?php
} else {
    for ($i = 0; $i < count($files); $i++) {
        $files[$i] = basename($files[$i], '.json');
    }
?>
    <p>Choose a quiz</p>

    <form method="post" action="quiz.php">
        <table>
            <thead>
                <tr>
                    <th>Quiz</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
<?php
    foreach ($files as $file) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $file)) {
            continue;
        }
?>
                <tr>
                    <td><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="submit" name="quizName" value="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>">
                            Take
                        </button>
                    </td>
                </tr>
<?php
    }
?>
            </tbody>
        </table>
    </form>
<?php
}
?>

<?php require __DIR__ . '/footer.php'; ?>

<form method="get" action="signin.php">
    <input type="submit" value="Go Back" />
</form>

</body>
</html>