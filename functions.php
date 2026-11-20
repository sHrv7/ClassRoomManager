<?php
/** Zajednicke funkcije za prikaz, CSRF, preusmjeravanje i provjeru pristupa. */
declare(strict_types=1);

/** Sigurno pretvara vrijednost u tekst prikladan za HTML prikaz. */
function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cisti rich-text HTML i zadrzava samo dozvoljeno formatiranje i sigurne poveznice. */
function sanitize_rich_text(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return strip_tags($html);
    }

    $source = new DOMDocument('1.0', 'UTF-8');
    $previousErrorMode = libxml_use_internal_errors(true);
    $loaded = $source->loadHTML(
        '<!doctype html><html><head><meta charset="UTF-8"></head><body><div>'
        . $html
        . '</div></body></html>',
        LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorMode);

    $sourceRoot = $source->getElementsByTagName('div')->item(0);
    if (!$loaded || !$sourceRoot) {
        return strip_tags($html);
    }

    $allowedTags = [
        'a', 'b', 'blockquote', 'br', 'em', 'h1', 'h2', 'h3',
        'i', 'li', 'ol', 'p', 's', 'strong', 'u', 'ul',
    ];
    $discardContentTags = ['iframe', 'object', 'script', 'style', 'svg', 'template'];
    $cleanDocument = new DOMDocument('1.0', 'UTF-8');
    $cleanRoot = $cleanDocument->createElement('div');
    $cleanDocument->appendChild($cleanRoot);

    $copySafeNodes = function (DOMNode $sourceNode, DOMNode $targetNode) use (
        &$copySafeNodes,
        $allowedTags,
        $discardContentTags,
        $cleanDocument
    ): void {
        if ($sourceNode instanceof DOMText) {
            $targetNode->appendChild($cleanDocument->createTextNode($sourceNode->nodeValue ?? ''));
            return;
        }

        if (!$sourceNode instanceof DOMElement) {
            foreach ($sourceNode->childNodes as $child) {
                $copySafeNodes($child, $targetNode);
            }
            return;
        }

        $tag = strtolower($sourceNode->tagName);
        if (in_array($tag, $discardContentTags, true)) {
            return;
        }

        if (!in_array($tag, $allowedTags, true)) {
            foreach ($sourceNode->childNodes as $child) {
                $copySafeNodes($child, $targetNode);
            }
            return;
        }

        $cleanElement = $cleanDocument->createElement($tag);
        if ($tag === 'a') {
            $href = trim($sourceNode->getAttribute('href'));
            $scheme = parse_url($href, PHP_URL_SCHEME);
            $safeScheme = $scheme === null
                || in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);

            if ($href !== '' && $safeScheme && !str_starts_with($href, '//')
                && !preg_match('/[\x00-\x20]/', $href)) {
                $cleanElement->setAttribute('href', $href);
            }
        }

        foreach ($sourceNode->childNodes as $child) {
            $copySafeNodes($child, $cleanElement);
        }
        $targetNode->appendChild($cleanElement);
    };

    foreach ($sourceRoot->childNodes as $child) {
        $copySafeNodes($child, $cleanRoot);
    }

    $result = '';
    foreach ($cleanRoot->childNodes as $child) {
        $result .= $cleanDocument->saveHTML($child);
    }

    return $result;
}

/** Vraca siguran HTML za obavijest ili escapirani obican tekst sa sacuvanim redovima. */
function notification_message_html(string $message): string
{
    if (!preg_match('/<\s*\/?\s*(?:p|br|strong|b|em|i|u|s|ol|ul|li|blockquote|h[1-3]|a)\b/i', $message)) {
        return nl2br(e($message));
    }

    return sanitize_rich_text($message);
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