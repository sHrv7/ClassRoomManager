
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'] ?? null;
$isProfessor = ($user['Role'] ?? '') === 'Professor';
$isAdmin = ($user['Role'] ?? '') === 'Admin';
$notice = (string) ($_SESSION['notification_notice'] ?? '');
unset($_SESSION['notification_notice']);

if (!$userId) {
    exit('User ID not found.');
}

// Get notifications for subjects the logged-in student is enrolled in
$stmt = db()->prepare("
    SELECT DISTINCT
        n.id,
        n.subject_id,
        n.professor_id,
        n.title,
        n.message,
        n.created_at,
        n.image,
        c.name AS category_name,
        u.username AS professor_name
    FROM notifications n
    JOIN users u ON u.id = n.professor_id
    LEFT JOIN categories c ON c.id = n.category_id
    LEFT JOIN user_subjects us
        ON us.subject_id = n.subject_id
        AND us.user_id = :user_id
    WHERE n.subject_id IS NULL
       OR us.user_id IS NOT NULL
       OR :is_admin = 1
       OR n.professor_id = :author_id
    ORDER BY n.created_at DESC
");

$stmt->execute([
    'user_id' => $userId,
    'is_admin' => $isAdmin ? 1 : 0,
    'author_id' => $userId,
]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Obavijesti';

require __DIR__ . '/header.php';
?>

<main class="page-shell notifications-page">

    <?php if ($notice !== ''): ?>
        <p class="success-message" role="status"><?= e($notice) ?></p>
    <?php endif; ?>

    <div class="notifications-heading">
        <div>
            <p class="eyebrow">CLASSROOM MANAGER</p>
            <h1>Obavijesti</h1>
            <p class="muted">
                Ovdje možete pregledati obavijesti profesora
                iz predmeta koje pohađate.
            </p>
        </div>

        <div class="notifications-actions">

            <div class="notification-count">
                <?= count($notifications) ?> obavijesti
            </div>

            <?php if ($isProfessor): ?>
                <a href="create_post.php" class="button button-primary">
                    + Nova objava
                </a>
            <?php endif; ?>

        </div>
    </div>

    <section class="notification-list">

    <?php if (empty($notifications)): ?>

        <div class="notification-empty">
            <h2>Trenutno nema obavijesti</h2>
            <p class="muted">
                Kada profesor objavi obavijest iz predmeta
                koje pohađate, ona će se pojaviti ovdje.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($notifications as $notification): ?>

            <a
                href="view_notification.php?id=<?= (int) $notification['id'] ?>"
                class="notification-card notification-card-link"
            >
                <div class="notification-card-top">
                    <div>
                        <h2><?= e($notification['title']) ?></h2>

                        <p class="notification-category">
                            <?= e($notification['category_name'] ?? 'Bez kategorije') ?>
                        </p>

                        <p class="notification-meta">
                            <?= e($notification['professor_name']) ?>
                            ·
                            <?= date(
                                'd.m.Y. H:i',
                                strtotime($notification['created_at'])
                            ) ?>
                        </p>
                    </div>
                </div>
            </a>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

</main>

<?php require __DIR__ . '/footer.php'; ?>
