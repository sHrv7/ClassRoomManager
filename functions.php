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

/** Sends one EmailJS notice to active students, keeping additional recipients in BCC. */
function send_notification_emails(array $notification): array
{
    $pdo = db();
    $subjectId = $notification['subject_id'] ?? null;

    if ($subjectId === null) {
        $recipients = $pdo->query(
            "SELECT Email FROM users WHERE Role = 'Student' AND Active = 1 ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $recipientQuery = $pdo->prepare(
            "SELECT DISTINCT u.Email FROM users u "
            . "INNER JOIN user_subjects us ON us.user_id = u.id "
            . "WHERE us.subject_id = :subject_id "
            . "AND u.Role = 'Student' AND u.Active = 1 ORDER BY u.id"
        );
        $recipientQuery->execute(['subject_id' => (int) $subjectId]);
        $recipients = $recipientQuery->fetchAll(PDO::FETCH_COLUMN);
    }

    $recipients = array_values(array_unique(array_filter(
        $recipients,
        static fn($email): bool => is_string($email)
            && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false
    )));

    if ($recipients === []) {
        return [
            'status' => 'no_recipients',
            'count' => 0,
            'reason' => 'Nema aktivnih studenata s valjanom email adresom.',
        ];
    }

    $recipients = array_map('trim', $recipients);
    $recipientCount = count($recipients);

    $professorQuery = $pdo->prepare('SELECT Email FROM users WHERE id = :id LIMIT 1');
    $professorQuery->execute(['id' => (int) ($notification['professor_id'] ?? 0)]);
    $toEmail = trim((string) $professorQuery->fetchColumn());
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'status' => 'invalid_sender',
            'count' => $recipientCount,
            'reason' => 'Autor obavijesti nema valjanu email adresu.',
        ];
    }

    $serviceId = getenv('EMAILJS_SERVICE_ID') ?: 'service_5mj8guj';
    $templateId = getenv('EMAILJS_TEMPLATE_ID') ?: 'template_1nyncfm';
    $publicKey = getenv('EMAILJS_PUBLIC_KEY') ?: 'qRS6iAGIPIomMPrKt';
    if (!$serviceId || !$templateId || !$publicKey) {
        return ['status' => 'not_configured', 'count' => $recipientCount];
    }

    if (!function_exists('curl_init')) {
        return [
            'status' => 'curl_unavailable',
            'count' => $recipientCount,
            'reason' => 'PHP cURL ekstenzija nije omogućena.',
        ];
    }

    $message = (string) ($notification['message'] ?? '');
    $message = preg_replace('/<\s*br\s*\/?\s*>|<\s*\/\s*(p|li|h[1-6]|blockquote)\s*>/i', "\n", $message) ?? $message;
    $message = trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $payload = [
        'service_id' => $serviceId,
        'template_id' => $templateId,
        'user_id' => $publicKey,
        'template_params' => [
            'to_email' => $toEmail,
            'bcc_email' => implode(',', $recipients),
            'notification_title' => (string) ($notification['title'] ?? ''),
            'notification_message' => $message,
        ],
    ];
    $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($requestBody === false) {
        return [
            'status' => 'invalid_request',
            'count' => $recipientCount,
            'reason' => 'Zahtjev nije moguće kodirati kao JSON.',
        ];
    }

    $curl = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $requestBody,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $responseText = trim((string) $response);
    $decodedResponse = json_decode($responseText, true);
    $emailSent = $response !== false
        && $statusCode === 200
        && ($responseText === 'OK' || $decodedResponse === 'OK');

    if (!$emailSent) {
        $safeResponse = preg_replace(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '[email]',
            substr($responseText, 0, 500)
        ) ?? '';
        error_log(
            'EmailJS send failed; HTTP status ' . $statusCode
            . '; transport error: ' . $curlError
            . '; provider response: ' . $safeResponse
        );
        $reason = $response === false
            ? ($curlError !== '' ? $curlError : 'EmailJS nije vratio odgovor.')
            : ($safeResponse !== '' ? $safeResponse : 'EmailJS HTTP status ' . $statusCode . '.');
        return [
            'status' => 'send_failed',
            'count' => $recipientCount,
            'reason' => trim(preg_replace('/[\r\n\t]+/', ' ', $reason) ?? $reason),
        ];
    }

    return ['status' => 'sent', 'count' => $recipientCount];
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