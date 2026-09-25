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
        <link rel="stylesheet" href="global.css" />
        <link rel="stylesheet" href="signin.css" />
    </head>
    <body>
        <div class="header">
            <h1 class="special-title">HumGlot</h1>
            <p class="description">A person who knows and is able to use several languages.</p>
        </div>

        <div class="signin-panel">
            <?php
            if(!isset($_SESSION['account']))
            {
            ?>

            <h2>Sign in to view your dashboard!</h2>

            <!-- Login and Signup Form -->
            <form method="post" action="index.php">
                <label for="acc">Username</label>
                <input type="text" name="account" id="acc" required="required" placeholder="Enter your username" />
                <?php
                if($error !== '') {
                ?>
                    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php
                }
                ?>

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

        </div>
        <!-- End of Signin Panel -->

        <?php require __DIR__ . '/footer.php'; ?>

    </body>
</html>
