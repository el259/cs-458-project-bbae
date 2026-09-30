<?php
    session_start();
    require __DIR__ . '/auth.php';
    require_once dirname(__DIR__) . '/hum_conn_no_login.php';

    // Sign out
    if (isset($_POST['logout']))
    {
        session_unset();
        session_destroy();
        header("Location: signin.php");
        exit();
    }

    $error = '';

    $next = safeNext($_GET['next'] ?? $_POST['next'] ?? null);
    $reason = $_GET['reason'] ?? $_POST['reason'] ?? '';

    $signinMessages = [
        'dashboard' => 'Sign in to view your dashboard!',
        'assignment' => 'Sign in to take this assignment!'
    ];
    $signinMessage = $signinMessages[$reason] ?? 'Sign in to continue!';

    $login = isset($_POST['login']);
    $signup = isset($_POST['signup']);

    if ($login || $signup)
    {
        $username = trim($_POST['account'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '')
        {
            $error = 'Please enter a username.';
        }
        else if (!preg_match('/^[A-Za-z0-9_-]+$/', $username))
        {
            $error = 'Username may contain only letters, numbers, underscores, and hyphens.';
        }
        else if ($password === '')
        {
            $error = 'Please enter a password.';
        }
        else if ($signup && strlen($password) < 8)
        {
            $error = 'Password must contain at least 8 characters.';
        }
        else
        {
            $email = strtolower($username) . '@example.com';
            $databaseConnection = hum_conn_no_login();

            if ($login)
            {
                $sql = '
                    SELECT "USER_ID", "PWD_HASH", "IS_ACTIVE", "IS_ADMIN"
                    FROM "APP_USER"
                    WHERE "EMAIL" = :email
                ';
                $statement = oci_parse($databaseConnection, $sql);
                oci_bind_by_name($statement, ':email', $email);

                if (!oci_execute($statement))
                {
                    $error = 'Unable to complete the login.';
                }
                else
                {
                    $user = oci_fetch_assoc($statement);

                    if ($user === false || $user['IS_ACTIVE'] !== 'Y' ||
                        !password_verify($password, $user['PWD_HASH']))
                    {
                        $error = 'Invalid username or password.';
                    }
                    else
                    {
                        session_regenerate_id(true);
                        $_SESSION['account'] = $username;
                        $_SESSION['user_id'] = $user['USER_ID'];
                        $_SESSION['teacher'] = $user['IS_ADMIN'] === 'Y';

                        oci_free_statement($statement);
                        oci_close($databaseConnection);

                        header('Location: ' . ($next ??
                            ($_SESSION['teacher'] ? 'teacher.php' : 'student.php')));
                        exit();
                    }
                }

                oci_free_statement($statement);
            }
            else
            {
                $checkSql = '
                    SELECT "USER_ID"
                    FROM "APP_USER"
                    WHERE "EMAIL" = :email
                ';
                $checkStatement = oci_parse($databaseConnection, $checkSql);
                oci_bind_by_name($checkStatement, ':email', $email);

                if (!oci_execute($checkStatement))
                {
                    $error = 'Unable to check the account.';
                }
                else if (oci_fetch_assoc($checkStatement) !== false)
                {
                    $error = 'That username is already taken.';
                }
                else
                {
                    $insertSql = '
                        INSERT INTO "APP_USER" (
                            "EMAIL", "FIRST_NAME", "LAST_NAME", "PWD_HASH"
                        ) VALUES (
                            :email, :first_name, :last_name, :pwd_hash
                        )
                    ';
                    $insertStatement = oci_parse($databaseConnection, $insertSql);
                    $firstName = $username;
                    $lastName = 'User';
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                    oci_bind_by_name($insertStatement, ':email', $email);
                    oci_bind_by_name($insertStatement, ':first_name', $firstName);
                    oci_bind_by_name($insertStatement, ':last_name', $lastName);
                    oci_bind_by_name($insertStatement, ':pwd_hash', $passwordHash);

                    if (!oci_execute($insertStatement, OCI_NO_AUTO_COMMIT))
                    {
                        oci_rollback($databaseConnection);
                        $error = 'Unable to create the account.';
                    }
                    else
                    {
                        oci_commit($databaseConnection);
                        $userSql = '
                            SELECT "USER_ID"
                            FROM "APP_USER"
                            WHERE "EMAIL" = :email
                        ';
                        $userStatement = oci_parse($databaseConnection, $userSql);
                        oci_bind_by_name($userStatement, ':email', $email);
                        oci_execute($userStatement);
                        $newUser = oci_fetch_assoc($userStatement);

                        session_regenerate_id(true);
                        $_SESSION['account'] = $username;
                        $_SESSION['user_id'] = $newUser['USER_ID'];
                        $_SESSION['teacher'] = false;

                        oci_free_statement($userStatement);
                        oci_free_statement($insertStatement);
                        oci_free_statement($checkStatement);
                        oci_close($databaseConnection);

                        header('Location: ' . ($next ?? 'student.php'));
                        exit();
                    }

                    oci_free_statement($insertStatement);
                }

                oci_free_statement($checkStatement);
            }

            oci_close($databaseConnection);
        }
    }
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

            <!-- Login and Signup Form -->
            <form method="post" action="signin.php">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                <input type="hidden" name="reason" value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>" />
                <label for="acc">Username</label>
                <input type="text" name="account" id="acc" required="required" placeholder="Enter your username" />
                <?php
                if($error !== '') {
                ?>
                    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php
                }
                ?>

                <label for="password">Password</label>
                <input type="password" name="password" id="password" required="required" placeholder="Enter your password" />

                <input type="submit" name="login" value="Log In" />
                <input type="submit" name="signup" value="Sign Up" />
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
