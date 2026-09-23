<?php
session_start();

require_once dirname(__DIR__, 3) . '/hum_conn_no_login.php';

if (
    !isset($_SESSION['account']) ||
    empty($_SESSION['teacher']) ||
    !isset($_SESSION['user_id'])
) {
    header('Location: index.php');
    exit();
}

$quizzes = [];
$error = '';

$databaseConnection = hum_conn_no_login();

$sql = '
    SELECT
        "QUIZ_ID",
        "TITLE",
        TO_CHAR("CREATED_AT", \'YYYY-MM-DD\') AS "CREATED_DATE"
    FROM "QUIZ"
    WHERE "CREATOR_ID" = :creator_id
    ORDER BY "TITLE"
';

$statement = oci_parse($databaseConnection, $sql);
$creatorId = $_SESSION['user_id'];

oci_bind_by_name($statement, ':creator_id', $creatorId);

if (!oci_execute($statement)) {
    $error = 'Unable to load quizzes.';
} else {
    while ($quiz = oci_fetch_assoc($statement)) {
        $quizzes[] = [
            'id' => $quiz['QUIZ_ID'],
            'name' => $quiz['TITLE'],
            'created' => $quiz['CREATED_DATE'],
            'edited' => $quiz['CREATED_DATE']
        ];
    }
}

oci_free_statement($statement);
oci_close($databaseConnection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HumGlot</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php require __DIR__ . '/header.php'; ?>

<h2>Your classrooms</h2>
<p><i>empty</i></p>

<h2>Your quizzes</h2>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($quizzes) === 0): ?>
    <p>No quizzes yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Quiz name</th>
                <th>Date created</th>
                <th>Last edited</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($quizzes as $quiz): ?>
            <tr>
                <td>
                    <?= htmlspecialchars(
                        $quiz['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
                <td>
                    <?= htmlspecialchars(
                        $quiz['created'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
                <td>
                    <?= htmlspecialchars(
                        $quiz['edited'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<form method="get" action="create.php">
    <input type="submit" value="Create a quiz">
</form>

<form method="get" action="take.php">
    <input type="submit" value="Take a quiz">
</form>

<form method="post" action="index.php">
    <input type="submit" name="logout" value="Sign Out">
</form>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>