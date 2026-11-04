<?php

session_start();
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/provision.php';

$ticket = $_GET['ticket'] ?? '';
if (!is_string($ticket)) {
    $ticket = '';
}

try {
    $username = casValidateTicket($ticket);
    $account = provisionCasUser($username);

    session_regenerate_id(true);
    $_SESSION['account'] = $username;
    $_SESSION['user_id'] = $account['user_id'];
    $_SESSION['teacher'] = $account['is_admin'];
    $_SESSION['auth_provider'] = 'humboldt-cas';

    $next = casNext($_SESSION['cas_next'] ?? null);
    unset($_SESSION['cas_next']);
    header('Location: ' . $next);
    exit();
} catch (Throwable $exception) {
    unset($_SESSION['cas_next']);
    error_log('CAS authentication failed: ' . $exception->getMessage());
    http_response_code(401);
    echo 'Single sign-on could not be completed.';
}
