<?php

// Only allow redirecting back to a local page, never an external URL (open redirect protection)
function safeNext(?string $next): ?string
{
    if (!$next || $next[0] !== '/' || str_starts_with($next, '//'))
    {
        return null;
    }

    return $next;
}

// Send the user to sign in, remembering where they were headed and why
function goToSignin(string $reason)
{
    $next = $_SERVER['REQUEST_URI'];
    header("Location: signin.php?next=" . urlencode($next) . "&reason=" . urlencode($reason));
    exit();
}

function requireAccount(string $reason = '')
{
    if (!isset($_SESSION['account']))
    {
        goToSignin($reason);
    }
}

function requireTeacher(string $reason = '')
{
    requireAccount($reason);

    if (empty($_SESSION['teacher']))
    {
        // Already logged in, just the wrong role - no need to sign in again
        header("Location: student.php");
        exit();
    }
}
