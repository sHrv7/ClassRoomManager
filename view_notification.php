
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'] ?? null;
$isAdmin = ($user['Role'] ?? '') === 'Admin';

if (!$userId) {
    exit('User ID not found.');
}


$notificationId = (int)($_GET['id'] ?? 0);

if ($notificationId <= 0) {
    http_response_code(400);
    exit('Neispravan ID obavijesti.');
}

// Release the session lock before making the API request.
session_write_close();

// API URL
$apiUrl = 'http://' . $_SERVER['HTTP_HOST']
    . '/ClassRoomManager/api/v1/notifications/'
    . $notificationId;

// Send GET request to the API
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' =>
            "Accept: application/json\r\n" .
            "Cookie: " . session_name() . "=" . session_id() . "\r\n",
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($apiUrl, false, $context);

if ($response === false) {
    error_log('Notification API connection failed: ' . print_r(error_get_last(), true));
    http_response_code(500);
    exit('Obavijest trenutno nije moguće učitati.');
}

$result = json_decode($response, true);

if (!is_array($result) || !($result['success'] ?? false)) {
    $statusLine = $http_response_header[0] ?? '';

    if (str_contains($statusLine, '404')) {
        http_response_code(404);
        exit('Obavijest nije pronađena ili nemate pristup.');
    }

    error_log('Notification API error: ' . $response);
    http_response_code(500);
    exit('Došlo je do pogreške prilikom učitavanja obavijesti.');
}

$notification = $result['data'];

// Ensure all expected notification fields are available.
if (!is_array($notification)) {
    http_response_code(500);
    exit('Neispravan odgovor API-ja.');
}


$pageTitle = 'Obavijest';

require __DIR__ . '/header.php';
?>

<main class="page-shell notification-detail-page">

    <a href="notifications.php" class="notification-back-link">
        &larr; Natrag na obavijesti
    </a>

    <article class="notification-detail">

        <header class="notification-detail-header">

            <?php if (!empty($notification['image'])): ?>
                <div class="notification-detail-image-wrapper">
                    <img
                        src="<?= e($notification['image']) ?>"
                        alt="Slika uz obavijest"
                        class="notification-image"
                    >
                </div>
            <?php endif; ?>

            <div class="notification-detail-heading">

                <div class="notification-detail-title-row">
                    <h1><?= e($notification['title'] !== '' ? $notification['title'] : 'Nacrt bez naslova') ?></h1>
                    <?php if ((int) $notification['is_draft'] === 1): ?>
                        <span class="notification-draft-badge">Nacrt</span>
                    <?php endif; ?>

                    <span class="notification-detail-date">
                        <?= date('d.m.Y. H:i', strtotime($notification['created_at'])) ?>
                    </span>
                </div>

                <span class="notification-detail-category">
                    <?= e($notification['category_name'] ?? 'Bez kategorije') ?>
                </span>

                <div class="notification-author">
                    <div class="notification-author-avatar">
                        <?= e(strtoupper(substr($notification['professor_name'], 0, 1))) ?>
                    </div>

                    <div>
                        <span class="notification-author-label">Objavio/la</span>
                        <strong><?= e($notification['professor_name']) ?></strong>
                    </div>
                </div>

            </div>

        </header>

        <div class="notification-detail-divider"></div>

        <div class="notification-detail-content">
            <div class="notification-message">
                <?= notification_message_html((string)$notification['message']) ?>
            </div>
        </div>

        <?php if ($isAdmin || (int)$notification['professor_id'] === (int)$userId): ?>
            <footer class="notification-detail-footer">
                <a
                    class="button button-primary"
                    href="edit_post.php?id=<?= (int)$notification['id'] ?>"
                >
                    Uredi obavijest
                </a>
            </footer>
        <?php endif; ?>

    </article>

</main>

<?php require __DIR__ . '/footer.php'; ?>
