<?php
session_start();
require __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (isset($_POST['logout'])) {
    $casSession = ($_SESSION['auth_provider'] ?? '') === 'humboldt-cas';
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'],
            $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    if ($casSession) {
        header('Location: cas/logout.php');
    } else {
        header('Location: signin.php');
    }
    exit();
}

$next = safeNext($_GET['next'] ?? $_POST['next'] ?? null);
$reason = $_GET['reason'] ?? $_POST['reason'] ?? '';
$error = '';
$localAuthEnabled = filter_var(
    getenv('LOCAL_AUTH_ENABLED') ?: 'true',
    FILTER_VALIDATE_BOOLEAN
);

$signinMessages = [
    'dashboard' => 'Sign in to view your dashboard!',
    'assignment' => 'Sign in to take this assignment!'
];
$signinMessage = $signinMessages[$reason] ?? 'Sign in to continue!';

if ($localAuthEnabled && (isset($_POST['login']) || isset($_POST['signup']))) {
    $username = trim((string) ($_POST['account'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $signup = isset($_POST['signup']);

    if ($username === '') {
        $error = 'Please enter a username.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $username)) {
        $error = 'Username may contain only letters, numbers, underscores, and hyphens.';
    } elseif ($password === '') {
        $error = 'Please enter a password.';
    } elseif ($signup && strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } else {
        $email = strtolower($username) . '@example.com';
        $connection = hum_conn_no_login();
        $statement = oci_parse($connection, '
            SELECT "USER_ID", "PWD_HASH", "IS_ACTIVE", "IS_ADMIN"
            FROM "APP_USER"
            WHERE "EMAIL" = :email
        ');
        oci_bind_by_name($statement, ':email', $email);

        if (!oci_execute($statement)) {
            $error = 'Unable to complete the account operation.';
        } else {
            $user = oci_fetch_assoc($statement);
            if (!$signup) {
                if ($user === false || $user['IS_ACTIVE'] !== 'Y'
                    || !is_string($user['PWD_HASH'])
                    || !password_verify($password, $user['PWD_HASH'])) {
                    $error = 'Invalid username or password.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['account'] = $username;
                    $_SESSION['user_id'] = (int) $user['USER_ID'];
                    $_SESSION['teacher'] = $user['IS_ADMIN'] === 'Y';
                    $_SESSION['auth_provider'] = 'local';
                    oci_free_statement($statement);
                    oci_close($connection);
                    header('Location: ' . ($next
                        ?? ($_SESSION['teacher'] ? 'teacher.php' : 'student.php')));
                    exit();
                }
            } elseif ($user !== false) {
                $error = 'That username is already taken.';
            } else {
                $insert = oci_parse($connection, '
                    INSERT INTO "APP_USER" (
                        "EMAIL", "FIRST_NAME", "LAST_NAME", "PWD_HASH"
                    ) VALUES (
                        :email, :first_name, :last_name, :pwd_hash
                    )
                ');
                $firstName = $username;
                $lastName = 'User';
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                oci_bind_by_name($insert, ':email', $email);
                oci_bind_by_name($insert, ':first_name', $firstName);
                oci_bind_by_name($insert, ':last_name', $lastName);
                oci_bind_by_name($insert, ':pwd_hash', $passwordHash);
                if (!oci_execute($insert, OCI_NO_AUTO_COMMIT)) {
                    oci_rollback($connection);
                    $error = 'Unable to create the account.';
                } else {
                    oci_commit($connection);
                    $user = ['USER_ID' => 0, 'IS_ADMIN' => 'N'];
                    $lookup = oci_parse($connection, '
                        SELECT "USER_ID" FROM "APP_USER" WHERE "EMAIL" = :email
                    ');
                    oci_bind_by_name($lookup, ':email', $email);
                    oci_execute($lookup);
                    $user = oci_fetch_assoc($lookup);
                    oci_free_statement($lookup);
                    session_regenerate_id(true);
                    $_SESSION['account'] = $username;
                    $_SESSION['user_id'] = (int) $user['USER_ID'];
                    $_SESSION['teacher'] = false;
                    $_SESSION['auth_provider'] = 'local';
                    oci_free_statement($insert);
                    oci_free_statement($statement);
                    oci_close($connection);
                    header('Location: ' . ($next ?? 'student.php'));
                    exit();
                }
                oci_free_statement($insert);
            }
        }
        oci_free_statement($statement);
        oci_close($connection);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
<?php if (!isset($_SESSION['account'])): ?>
        <h2><?= htmlspecialchars($signinMessage, ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="get" action="cas/login.php">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="submit" value="Sign in with Humboldt CAS" />
        </form>
<?php if ($localAuthEnabled): ?>
        <hr>
        <h3>Local account</h3>
        <form method="post" action="signin.php">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="reason" value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>" />
            <label for="acc">Username</label>
            <input type="text" name="account" id="acc" required="required" />
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required="required" />
            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <input type="submit" name="login" value="Log In" />
            <input type="submit" name="signup" value="Sign Up" />
        </form>
<?php endif; ?>
<?php else: ?>
        <form method="post" action="signin.php">
            <input type="submit" name="logout" value="Sign Out" />
        </form>
        <form method="get" action="<?= !empty($_SESSION['teacher']) ? 'teacher.php' : 'student.php' ?>">
            <input type="submit" value="<?= !empty($_SESSION['teacher']) ? 'Teacher page' : 'Student page' ?>" />
        </form>
<?php endif; ?>
    </div>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
