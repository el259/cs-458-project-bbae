<?php
    session_start();

    // Make sure the user is logged in
    //   else, it sends to signin.php
    require __DIR__ . '/auth.php';
    requireTeacher('dashboard');



    // Placeholder DATE info until quiz/classroom metadata is stored for real.
    // Later: read created/edited from the JSON or db.
    function getFileDates($filepath)
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



        $dates = getFileDates($filepath);



        $quizzes[] = [
            'slug' => $slug,
            'name' => str_replace('_', ' ', $slug),
            'created' => $dates['created'],
            'edited' => $dates['edited']
        ];
    }



    $classroomPath = __DIR__ . '/classrooms/*.json';
    $classroomFiles = glob($classroomPath);



    if($classroomFiles === false)
    {
        $classroomFiles = [];
    }



    $classrooms = [];



    foreach ($classroomFiles as $filepath)
    {
        $slug = basename($filepath, '.json');



        if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug))
        {
            continue;
        }



        $dates = getFileDates($filepath);
        $studentCount = 0;
        $hasPasscode = false;
        $name = str_replace('_', ' ', $slug);



        $raw = file_get_contents($filepath);



        if ($raw !== false)
        {
            $data = json_decode($raw, true);



            if (is_array($data))
            {
                if (!empty($data['name']))
                {
                    $name = $data['name'];
                }



                if (!empty($data['passcode']))
                {
                    $hasPasscode = true;
                }



                if (isset($data['students']) && is_array($data['students']))
                {
                    $studentCount = count($data['students']);
                }
            }
        }



        $classrooms[] = [
            'slug' => $slug,
            'name' => $name,
            'students' => $studentCount,
            'passcode' => $hasPasscode,
            'created' => $dates['created'],
            'edited' => $dates['edited']
        ];
    }
?>



<!DOCTYPE html>
    <html lang="en" xmlns="http://www.w3.org/1999/xhtml">

    <!--
        Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
        Last Modified: 9/25/26
        TEACHER.PHP
    -->

    <head>
        <title>HumGlot</title>
        <meta charset="utf-8" />
        <link rel="stylesheet" href="global.css" />
        <link rel="stylesheet" href="teacher.css" />
    </head>
    <body>
        <?php require __DIR__ . '/header.php'; ?>

        <h2>Your classrooms</h2>



        <?php
        if(count($classrooms) <= 0)
        {
        ?>

        <p>No classrooms yet.</p>
        
        <?php
        }
        else
        {
        ?>

        <table>
            <thead>
            <tr>
                <th>Classroom name</th>
                <th>Students</th>
                <th>Passcode</th>
                <th>Date created</th>
                <th>Last edited</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach($classrooms as $classroom)
                    {
            ?>
                <tr>
                    <td><?= htmlspecialchars($classroom['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$classroom['students'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $classroom['passcode'] ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($classroom['created'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($classroom['edited'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="get" action="classroom.php">
                            <input type="hidden" name="slug" value="<?= htmlspecialchars($classroom['slug'], ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="submit" value="View" />
                        </form>
                    </td>
                </tr>
            <?php
                    }
            ?>
            </tbody>
        </table>
        <?php
        }
        ?>

        <form method="get" action="create_classroom.php">
            <input type="submit" value="Make classroom" />
        </form>

        <h2>Your quizzes</h2>

        <?php
        if(count($quizzes) <= 0)
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


        <form method="post" action="signin.php">
            <input type="submit" name="logout" value="Sign Out" />
        </form>


    <?php require __DIR__ . '/footer.php'; ?>
    </body>
</html>
