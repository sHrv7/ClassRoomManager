<?php
declare(strict_types=1);

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    return isset($_SESSION['csrf_token'], $_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_email'])) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT Username, Email, Password, Role, Active FROM users WHERE Email = :email LIMIT 1'
    );
    $statement->execute(['email' => $_SESSION['user_email']]);
    $user = $statement->fetch();

    if (!$user || (int) $user['Active'] !== 1) {
        unset($_SESSION['user_email']);
        session_regenerate_id(true);
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['Role'] !== 'Admin') {
        http_response_code(403);
        exit('Nemate ovlasti za pristup ovoj stranici.');
    }

    return $user;
}