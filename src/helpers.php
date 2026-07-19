<?php
declare(strict_types=1);

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    $session = (string) ($_SESSION['_csrf'] ?? '');
    if ($session === '' || $token === '' || !hash_equals($session, $token)) {
        http_response_code(400);
        echo '<h1>Invalid form token</h1><p>Please go back and try again.</p>';
        exit;
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'superuser' => 'Superuser',
        'admin' => 'Admin',
        default => 'Member',
    };
}
