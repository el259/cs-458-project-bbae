<?php
session_start();


// Sign out
if (isset($_POST['logout']))
{
session_unset();
session_destroy();
header("Location: index.php");
exit();
}


$welcome_msg = 'Welcome to HumGlot';
$error = '';


$login = array_key_exists('login', $_POST);
$signup = array_key_exists('signup', $_POST);


if($login || $signup)
{
    $name = trim($_POST['account'] ?? '');
    $filepath = __DIR__ . '/accounts.json';


    if($name === '')
    {
        $error = 'Please enter a username.';
    }
    else if(file_exists($filepath))
    {
        $jsonString = file_get_contents($filepath);
        $data = json_decode($jsonString, true);


        if(!is_array($data))
        {
            $data = [
                'names' => [],
                'teacher' => []
            ];
        }


        $names = isset($data['names']) ? $data['names'] : [];
        $teacher = isset($data['teacher']) ? $data['teacher'] : [];


        if($login)
        {
            $index = array_search($name, $names, true);


            if($index === false)
            {
                $error = 'No account found, try again';
            }
            else
            {
                $_SESSION['account'] = $name;
                $_SESSION['teacher'] = !empty($teacher[$index]);


                if ($_SESSION['teacher'])
                {
                    header("Location: teacher.php");
                    exit();
                }
                else
                {
                    header("Location: student.php");
                    exit();
                }
            }
        }
        else if($signup)
        {
            if(!in_array($name, $names, true))
            {
                $toName = $name;
                $toTeacher = false;


                $names[] = $toName;
                $teacher[] = $toTeacher;


                $data = [
                    'names' => $names,
                    'teacher' => $teacher
                ];


                $jsonString = json_encode($data, JSON_PRETTY_PRINT);
                file_put_contents($filepath, $jsonString);


                $_SESSION['account'] = $toName;
                $_SESSION['teacher'] = $toTeacher;


                header("Location: student.php");
                exit();
            }
            else
            {
                $error = 'That username is already taken.';
            }
        }
    }
    else
    {
        $error = 'Could not load accounts. Try again.';
    }
}


if(isset($_SESSION['account']))
{
    $welcome_msg = $welcome_msg . ', ' . $_SESSION['account'];
}
?>


<!DOCTYPE html>


<html lang="en" xmlns="http://www.w3.org/1999/xhtml">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/20/26
    https://nrs-projects.humboldt.edu/~el259/cs-458-project-bbae/nrs/
    INDEX.PHP
-->


<head>
    <title>HumGlot</title>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style.css" />
</head>


<body>

    <p><?= htmlspecialchars($welcome_msg, ENT_QUOTES, 'UTF-8') . '!' ?></p>


    <?php
    if($error !== '')
    {
    ?>


    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>


    <?php
    }


    if(!isset($_SESSION['account']))
    {
    ?>


    <form method="post" action="index.php">
        <div>
            <label for="acc">Username</label>
            <input type="text" name="account" id="acc" required="required" />
        </div>


            <input type="submit" name="login" value="Log In" />
            <input type="submit" name="signup" value="Sign Up" />
    </form>


    <?php
    }
    else
    {
    ?>


    <form method="post" action="index.php">
        <input type="submit" name="logout" value="Sign Out" />
    </form>


    <?php
        if(!empty($_SESSION['teacher']))
        {
    ?>


    <form method="get" action="teacher.php">
        <input type="submit" value="Teacher page" />
    </form>


    <?php
        }
        else
        {
    ?>


    <form method="get" action="student.php">
        <input type="submit" value="Student page" />
    </form>


    <?php
        }
    }
    ?>

    <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
