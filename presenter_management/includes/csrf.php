<?php
declare(strict_types=1);

/*
 * CSRF protection: every form carries a secret token.
 * A fake form on another website cannot know this token.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Put this inside every <form method="post"> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. Stops the script if the token is wrong. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';

    if (!is_string($sent) || $real === '' || !hash_equals($real, $sent)) {
        http_response_code(419);
        exit('Security check failed (invalid or expired form). Please go back, refresh the page and try again.');
    }
}
