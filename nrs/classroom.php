<?php
session_start();



    if (!isset($_SESSION['account']) || empty($_SESSION['teacher']))
    {
    header("Location: index.php");
    exit();
    }



    $slug = $_GET['slug'] ?? '';
    $error = '';
    $classroom = null;
    $filepath = '';



    if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug))
    {
        $error = 'Classroom not found.';
    }
    else
    {
        $filepath = __DIR__ . '/classrooms/' . $slug . '.json';



    if (!is_file($filepath))
        {
            $error = 'Classroom not found.';
        }
    else
        {
            $raw = file_get_contents($filepath);
            $data = json_decode($raw, true);



    if (!is_array($data))
            {
                $error = 'Could not read this classroom.';
            }
    else
            {
                $classroom = [
    'name' => $data['name'] ?? str_replace('_', ' ', $slug),
    'passcode' => $data['passcode'] ?? '',
    'students' => (isset($data['students']) && is_array($data['students'])) ? $data['students'] : [],
    'assignments' => (isset($data['assignments']) && is_array($data['assignments'])) ? $data['assignments'] : []
                ];
            }
        }
    }
    ?>



<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">



<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/25/26
    CLASSROOM.PHP
-->



<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="classroom.css" />
</head>



<body>
<?php require __DIR__ . '/header.php'; ?>

<?php
    if ($error !== '')
    {
    ?>
    <h2>Classroom</h2>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php
        }
        else
        {
        ?>

    <h2><?= htmlspecialchars($classroom['name'], ENT_QUOTES, 'UTF-8') ?></h2>



    <p>
    Passcode:
        <?php
        if ($classroom['passcode'] === '')
            {
        ?>
        <em>None</em>
        <?php
            }
        else
            {
        ?>
        <strong><?= htmlspecialchars($classroom['passcode'], ENT_QUOTES, 'UTF-8') ?></strong>
        <?php
            }
        ?>
    </p>



<h3>Students</h3>


    <?php
    if (count($classroom['students']) <= 0) {
    ?>
        <p>No students yet.</p>
    <?php
    } else {
    ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                </tr>
            </thead>
            <tbody>
    <?php
                $i = 1;
                foreach ($classroom['students'] as $student) {
    ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($student, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
    <?php
                }
    ?>
            </tbody>
        </table>
    <?php
    }
    ?>



<h3>Assignments</h3>


    <?php
        if (count($classroom['assignments']) <= 0)
            {
        ?>
        <p>No assignments yet.</p>
        <?php
            }
        else
            {
        ?>
        <ul>
        <?php
        foreach ($classroom['assignments'] as $assignment)
                {
                    $assignmentName = is_array($assignment) ? ($assignment['name'] ?? 'Assignment') : (string)$assignment;
        ?>
        <li><?= htmlspecialchars($assignmentName, ENT_QUOTES, 'UTF-8') ?></li>
        <?php
                }
        ?>
        </ul>
        <?php
            }
    ?>

    <?php
    }
    ?>

    <form method="get" action="teacher.php">
        <input type="submit" value="Go Back" />
    </form>

    <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>