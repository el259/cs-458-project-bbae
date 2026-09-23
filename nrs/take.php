<?php
session_start();

require_once dirname(__DIR__, 3) . '/hum_conn_no_login.php';

if (!isset($_SESSION['account'])) {
    header('Location: index.php');
    exit();
}

unset($_SESSION['quizId']);

$quizzes = [];
$error = '';

$databaseConnection = hum_conn_no_login();

$sql = '
    SELECT
        "QUIZ_ID",
        "TITLE"
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
<html lang="en">
<head>
    <title>Choose a Quiz</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php require __DIR__ . '/header.php'; ?>

<form method="get" action="index.php">
    <input type="submit" value="Go Back">
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($quizzes) === 0): ?>
    <p>No quizzes available. Try again later.</p>
<?php else: ?>
    <p>Choose a quiz</p>

    <form method="post" action="quiz.php">
        <?php foreach ($quizzes as $quiz): ?>
            <div>
                <button
                    type="submit"
                    name="quizId"
                    value="<?= htmlspecialchars(
                        $quiz['id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    <?= htmlspecialchars(
                        $quiz['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
            </button>
            </div>
        <?php endforeach; ?>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>