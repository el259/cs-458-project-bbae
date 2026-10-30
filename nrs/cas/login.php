<?php

session_start();
require __DIR__ . '/bootstrap.php';

$next = casNext($_GET['next'] ?? null);
$_SESSION['cas_next'] = $next;
casRedirect(casRequiredSetting('CAS_LOGIN_URL'), [
    'service' => casServiceUrl()
]);
