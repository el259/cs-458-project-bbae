<?php
    session_start();
    require __DIR__ . '/auth.php';

    // Sign out
    if (isset($_POST['logout']))
    {
        header("Location: cas/logout.php");
        exit();
    }

    $next = safeNext($_GET['next'] ?? $_POST['next'] ?? null);
    $reason = $_GET['reason'] ?? $_POST['reason'] ?? '';

    $signinMessages = [
        'dashboard' => 'Sign in to view your dashboard!',
        'assignment' => 'Sign in to take this assignment!'
    ];
    $signinMessage = $signinMessages[$reason] ?? 'Sign in to continue!';

?>



<!DOCTYPE html>
    <html lang="en" xmlns="http://www.w3.org/1999/xhtml">
    <!--
        Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
        Last Modified: 9/20/26
        https://nrs-projects.humboldt.edu/~el259/cs-458-project-bbae/nrs/
        SIGNIN.PHP
    -->
    <head>
        <title>HumGlot</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
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

            <h2><?= htmlspecialchars($signinMessage, ENT_QUOTES, 'UTF-8') ?></h2>

            <form method="get" action="cas/login.php">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                <input type="submit" value="Sign in with Humboldt CAS" />
            </form>

            <?php
            }
            else
            {
            ?>


            <form method="post" action="signin.php">
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
