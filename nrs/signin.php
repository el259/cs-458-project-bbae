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
$username = '';
$signup = isset($_POST['signup']);
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
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '') {
        $error = 'Please enter a username.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $username)) {
        $error = 'Username may contain only letters, numbers, underscores, and hyphens.';
    } elseif ($password === '') {
        $error = 'Please enter a password.';
    } elseif ($signup && strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($signup && $password !== $confirmPassword) {
        $error = 'Passwords do not match.';
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
            <p class="center-btn">
                <a class="button-tertiary" href="<?= htmlspecialchars('cas/login.php' . ($next !== null ? '?' . http_build_query(['next' => $next], '', '&', PHP_QUERY_RFC3986) : ''), ENT_QUOTES, 'UTF-8') ?>">Continue with Humboldt SSO</a>
            </p>
        <?php if ($localAuthEnabled): ?>
            <div class="auth-tabs" role="tablist" aria-label="Local account" hidden>
                <button type="button" role="tab" id="login-tab" aria-controls="login-panel">Sign in</button>
                <button type="button" role="tab" id="signup-tab" aria-controls="signup-panel">Create account</button>
            </div>
            <div class="auth-forms" data-active-tab="<?= $signup ? 'signup' : 'login' ?>">
                <form class="auth-form" method="post" action="signin.php" id="login-panel" role="tabpanel" aria-labelledby="login-tab">
                    <noscript><h3>Sign in</h3></noscript>
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="reason" value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>" />
                    <label for="login-account">Username</label>
                    <input type="text" name="account" id="login-account" autocomplete="username" value="<?= htmlspecialchars(!$signup ? $username : '', ENT_QUOTES, 'UTF-8') ?>" required />
                    <label for="login-password">Password</label>
                    <input type="password" name="password" id="login-password" autocomplete="current-password" required />
                    <?php if (!$signup && $error !== ''): ?>
                        <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <input class="button-primary" type="submit" name="login" value="Log In" />
                </form>
                <form class="auth-form" method="post" action="signin.php" id="signup-panel" role="tabpanel" aria-labelledby="signup-tab">
                    <noscript><h3>Create account</h3></noscript>
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="reason" value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>" />
                    <label for="signup-account">Username</label>
                    <input type="text" name="account" id="signup-account" autocomplete="username" value="<?= htmlspecialchars($signup ? $username : '', ENT_QUOTES, 'UTF-8') ?>" aria-describedby="signup-username-hint" required />
                    <p class="form-hint" id="signup-username-hint">Letters, numbers, underscores, and hyphens only.</p>
                    <label for="signup-password">Password</label>
                    <input type="password" name="password" id="signup-password" autocomplete="new-password" aria-describedby="signup-password-hint" required />
                    <p class="form-hint" id="signup-password-hint">At least 8 characters.</p>
                    <label for="confirm-password">Confirm password</label>
                    <input type="password" name="confirm_password" id="confirm-password" autocomplete="new-password" required />
                    <?php if ($signup && $error !== ''): ?>
                        <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <input class="button-primary" type="submit" name="signup" value="Create account" />
                </form>
            </div>
            
            <script src="signin.js" defer></script>
        <?php endif; ?>
        <?php else: ?>
            <div class="row-btns">
                <form method="post" action="signin.php">
                    <input class="button-secondary" type="submit" name="logout" value="Sign Out" />
                </form>
                <form method="get" action="<?= !empty($_SESSION['teacher']) ? 'teacher.php' : 'student.php' ?>">
                    <input class="button-primary" type="submit" value="<?= !empty($_SESSION['teacher']) ? 'Teacher page' : 'Student page' ?>" />
                </form>
            </div>
        <?php endif; ?>
        </div>
        <?php require __DIR__ . '/footer.php'; ?>
    </body>
</html>
