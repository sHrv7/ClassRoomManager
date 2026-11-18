<?php
/** Zajednicke funkcije za prikaz, CSRF, preusmjeravanje i provjeru pristupa. */
declare(strict_types=1);

/** Sigurno pretvara vrijednost u tekst prikladan za HTML prikaz. */
function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Vraca CSRF token sesije ili stvara novi kriptografski nasumican token. */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Provjerava odgovara li poslani CSRF token tokenu spremljenom u sesiji. */
function csrf_is_valid(): bool
{
    return isset($_SESSION['csrf_token'], $_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

/** Preusmjerava preglednik na zadanu lokaciju i prekida izvrsavanje. */
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** Dohvaca prijavljenog korisnika i prekida sesiju ako je racun neaktivan. */
function current_user(): ?array
{
    if (!isset($_SESSION['user_email'])) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, Username, Email, Password, Role, Active FROM users WHERE Email = :email LIMIT 1'
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

/** Zahtijeva prijavljenog korisnika; inace ga preusmjerava na prijavu. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }

    return $user;
}

/** Zahtijeva prijavljenog administratora; ostalim korisnicima vraca 403. */
function require_admin(): array
{
    $user = require_login();
    if ($user['Role'] !== 'Admin') {
        http_response_code(403);
        exit('Nemate ovlasti za pristup ovoj stranici.');
    }

    return $user;
}