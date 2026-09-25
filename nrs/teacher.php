<?php
session_start();


if (!isset($_SESSION['account']) || empty($_SESSION['teacher']))
{
header("Location: index.php");
exit();
}


// Placeholder DATE info until quiz metadata is stored for real.
// Later: read created/edited from the quiz JSON or db.
function getQuizDates($filepath)
{
    $dummyCreated = '2026-09-01';
    $dummyEdited = '2026-09-20';


    if (is_file($filepath))
    {
        $mtime = filemtime($filepath);


        if ($mtime !== false)
        {
            $dummyEdited = date('Y-m-d', $mtime);
        }
    }


    return [
        'created' => $dummyCreated,
        'edited' => $dummyEdited
    ];
}


$path = __DIR__ . '/quizzes/*.json';
$files = glob($path);


if($files === false)
{
    $files = [];
}


$quizzes = [];


foreach ($files as $filepath)
{
    $slug = basename($filepath, '.json');


    if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug))
    {
        continue;
    }


    $dates = getQuizDates($filepath);


    $quizzes[] = [
        'slug' => $slug,
        'name' => str_replace('_', ' ', $slug),
        'created' => $dates['created'],
        'edited' => $dates['edited']
    ];
}
?>


<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/21/26
    TEACHER.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
</head>


<body>
    <?php require __DIR__ . '/header.php'; ?>

<h2>Your classrooms</h2>

<p><i>empty</i></p>


<h2>Your quizzes</h2>


<?php   if(count($quizzes) <= 0)
    {
?>
    <p>No quizzes yet.</p>
<?php
    }
else
    {
?>
    <table>
        <thead>
            <tr>
                <th>Quiz name</th>
                <th>Date created</th>
                <th>Last edited</th>
            </tr>
        </thead>
        <tbody>
<?php
    foreach($quizzes as $quiz)
    {
?>
            <tr>
                <td><?= htmlspecialchars($quiz['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($quiz['created'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($quiz['edited'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
<?php
    }
?>
        </tbody>
    </table>
<?php
}
?>
    <form method="get" action="create.php">
        <input type="submit" value="Create a quiz" />
    </form>


    <form method="get" action="take.php">
        <input type="submit" value="Take a quiz" />
    </form>


    <form method="post" action="index.php">
        <input type="submit" name="logout" value="Sign Out" />
    </form>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>