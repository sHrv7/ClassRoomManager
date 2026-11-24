
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

$stmt = db()->prepare("
    SELECT
        n.id,
        n.subject_id,
        n.professor_id,
        n.title,
        n.message,
        n.created_at,
        n.image,
        n.is_draft,
        c.name AS category_name,
        u.username AS professor_name
    FROM notifications n
    JOIN users u ON u.id = n.professor_id
    LEFT JOIN categories c ON c.id = n.category_id
    LEFT JOIN user_subjects us
        ON us.subject_id = n.subject_id
        AND us.user_id = :user_id
    WHERE n.id = :notification_id
      AND (
          n.subject_id IS NULL
          OR us.user_id IS NOT NULL
          OR :is_admin = 1
          OR n.professor_id = :author_id
      )
      AND (
          n.is_draft = 0
          OR :is_admin_draft = 1
          OR n.professor_id = :draft_author_id
      )
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId,
    'notification_id' => $notificationId,
    'is_admin' => $isAdmin ? 1 : 0,
    'author_id' => $userId,
    'is_admin_draft' => $isAdmin ? 1 : 0,
    'draft_author_id' => $userId,
]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    http_response_code(404);
    exit('Obavijest nije pronađena ili nemate pristup.');
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
