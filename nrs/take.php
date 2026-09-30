<?php
session_start();

// Make sure the user is logged in
//   else, it sends to signin.php
require __DIR__ . '/auth.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

unset($_SESSION['quizId']);

$quizzes = [];
$error = '';
$databaseConnection = hum_conn_no_login();
$sql = '
    SELECT "QUIZ_ID", "TITLE"
    FROM "QUIZ"
    ORDER BY "TITLE"
';
$statement = oci_parse($databaseConnection, $sql);

if (!oci_execute($statement)) {
    $error = 'Unable to load quizzes.';
} else {
    while ($quiz = oci_fetch_assoc($statement)) {
        $quizzes[] = [
            'id' => $quiz['QUIZ_ID'],
            'name' => $quiz['TITLE']
        ];
    }
}

oci_free_statement($statement);
oci_close($databaseConnection);
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

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($quizzes) === 0): ?>
    <p>No quizzes available. Try again later.</p>
<?php else: ?>
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
<?php foreach ($quizzes as $quiz): ?>
                <tr>
                    <td><?= htmlspecialchars($quiz['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="submit" name="quizId" value="<?= htmlspecialchars($quiz['id'], ENT_QUOTES, 'UTF-8') ?>">
                            Take
                        </button>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>

<form method="get" action="signin.php">
    <input type="submit" value="Go Back" />
</form>

</body>
</html>