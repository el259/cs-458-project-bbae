<?php

function casSetting(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function casRequiredSetting(string $name): string
{
    $value = casSetting($name);
    if ($value === null) {
        error_log("CAS configuration is missing {$name}.");
        http_response_code(503);
        exit('Single sign-on is not configured.');
    }

    return $value;
}

function casServiceUrl(): string
{
    $service = casRequiredSetting('CAS_SERVICE_URL');
    $parts = parse_url($service);
    if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        error_log('CAS_SERVICE_URL must be an HTTPS URL.');
        http_response_code(503);
        exit('Single sign-on is not configured.');
    }

    return $service;
}

function casNext(?string $next): string
{
    if (!$next || $next[0] !== '/' || str_starts_with($next, '//')) {
        return '/student.php';
    }

    return $next;
}

function casRedirect(string $base, array $parameters = []): never
{
    $separator = str_contains($base, '?') ? '&' : '?';
    header('Location: ' . $base . ($parameters === []
        ? ''
        : $separator . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)));
    exit();
}

function casValidateTicket(string $ticket): string
{
    if (!preg_match('/\ACAS-[A-Za-z0-9._~-]+\z/', $ticket)) {
        throw new RuntimeException('Invalid CAS ticket format.');
    }

    $url = casRequiredSetting('CAS_VALIDATE_URL') . '?' . http_build_query([
        'ticket' => $ticket,
        'service' => casServiceUrl()
    ], '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "Accept: text/plain\r\n"
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('CAS validation request failed.');
    }

    $lines = preg_split('/\R/', trim($response));
    if (($lines[0] ?? '') !== 'yes' || count($lines) < 2) {
        throw new RuntimeException('CAS rejected the service ticket.');
    }

    $username = trim($lines[1]);
    $returnedService = isset($lines[2]) ? trim($lines[2]) : null;
    if ($username === '' || ($returnedService !== null
        && !hash_equals(casServiceUrl(), $returnedService))) {
        throw new RuntimeException('CAS returned an invalid service response.');
    }
    if (!preg_match('/\A[A-Za-z0-9._@-]{1,255}\z/', $username)) {
        throw new RuntimeException('CAS returned an invalid username.');
    }

    return $username;
}
