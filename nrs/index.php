<?php
session_start();

require_once dirname(__DIR__, 3) . '/hum_conn_no_login.php';

$welcome_msg = 'Welcome to HumGlot';
$error = '';

function redirectForUser(bool $isTeacher): void
{
    header('Location: ' . ($isTeacher ? 'teacher.php' : 'student.php'));
    exit();
}

/* Sign out */
if (isset($_POST['logout']))
{
    session_unset();
    session_destroy();

    header('Location: index.php');
    exit();
}

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
        /*
         * The current application treats usernames as email usernames.
         * For example, "julie" becomes "julie@example.com".
         */
        $email = strtolower($username) . '@example.com';

        $databaseConnection = hum_conn_no_login();

        if ($login)
        {
            $sql = '
                SELECT
                    "USER_ID",
                    "PWD_HASH",
                    "IS_ACTIVE",
                    "IS_ADMIN"
                FROM "APP_USER"
                WHERE "EMAIL" = :email
            ';

            $statement = oci_parse($databaseConnection, $sql);

            if ($statement === false)
            {
                $error = 'Unable to prepare the login query.';
            }
            else
            {
                oci_bind_by_name($statement, ':email', $email);

                if (!oci_execute($statement))
                {
                    $error = 'Unable to complete the login.';
                }
                else
                {
                    $user = oci_fetch_assoc($statement);

                    if (
                        $user === false ||
                        $user['IS_ACTIVE'] !== 'Y' ||
                        !password_verify($password, $user['PWD_HASH'])
                    )
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

                        redirectForUser($_SESSION['teacher']);
                    }
                }

                oci_free_statement($statement);
            }
        }
        else
        {
            $checkSql = '
                SELECT "USER_ID"
                FROM "APP_USER"
                WHERE "EMAIL" = :email
            ';

            $checkStatement = oci_parse(
                $databaseConnection,
                $checkSql
            );

            if ($checkStatement === false)
            {
                $error = 'Unable to prepare the account query.';
            }
            else
            {
                oci_bind_by_name(
                    $checkStatement,
                    ':email',
                    $email
                );

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
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_BCRYPT,
                        ['cost' => 12]
                    );

                    $insertSql = '
                        INSERT INTO "APP_USER" (
                            "EMAIL",
                            "FIRST_NAME",
                            "LAST_NAME",
                            "PWD_HASH",
                            "IS_ACTIVE",
                            "IS_ADMIN"
                        )
                        VALUES (
                            :email,
                            :first_name,
                            :last_name,
                            :pwd_hash,
                            :is_active,
                            :is_admin
                        )
                    ';

                    $insertStatement = oci_parse(
                        $databaseConnection,
                        $insertSql
                    );

                    $firstName = $username;
                    $lastName = 'User';
                    $isActive = 'Y';
                    $isAdmin = 'N';

                    oci_bind_by_name(
                        $insertStatement,
                        ':email',
                        $email
                    );
                    oci_bind_by_name(
                        $insertStatement,
                        ':first_name',
                        $firstName
                    );
                    oci_bind_by_name(
                        $insertStatement,
                        ':last_name',
                        $lastName
                    );
                    oci_bind_by_name(
                        $insertStatement,
                        ':pwd_hash',
                        $passwordHash
                    );
                    oci_bind_by_name(
                        $insertStatement,
                        ':is_active',
                        $isActive
                    );
                    oci_bind_by_name(
                        $insertStatement,
                        ':is_admin',
                        $isAdmin
                    );

                    if (
                        !oci_execute(
                            $insertStatement,
                            OCI_NO_AUTO_COMMIT
                        )
                    )
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

                        $userStatement = oci_parse(
                            $databaseConnection,
                            $userSql
                        );

                        oci_bind_by_name(
                            $userStatement,
                            ':email',
                            $email
                        );

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

                        redirectForUser(false);
                    }

                    oci_free_statement($insertStatement);
                }

                oci_free_statement($checkStatement);
            }
        }

        oci_close($databaseConnection);
    }
}
?>

<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title>HumGlot</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php require __DIR__ . '/header.php'; ?>

    <p>
        <?= htmlspecialchars($welcome_msg, ENT_QUOTES, 'UTF-8') ?>!
    </p>

    <?php if ($error !== ''): ?>
        <p>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (!isset($_SESSION['account'])): ?>
        <form method="post" action="index.php">
            <div>
                <label for="acc">Username</label>
                <input
                    type="text"
                    name="account"
                    id="acc"
                    required
                >
            </div>

            <div>
                <label for="password">Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                >
            </div>

            <input type="submit" name="login" value="Log In">
            <input type="submit" name="signup" value="Sign Up">
        </form>
    <?php else: ?>
        <form method="post" action="index.php">
            <input type="submit" name="logout" value="Sign Out">
        </form>

        <?php if (!empty($_SESSION['teacher'])): ?>
            <form method="get" action="teacher.php">
                <input type="submit" value="Teacher page">
            </form>
        <?php else: ?>
            <form method="get" action="student.php">
                <input type="submit" value="Student page">
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/footer.php'; ?>
</body>

</html>