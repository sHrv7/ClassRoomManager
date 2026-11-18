
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'] ?? null;

if (!$userId) {
    exit('User ID not found.');
}

// Get notifications for subjects the logged-in student is enrolled in
$stmt = db()->prepare("
    SELECT DISTINCT
        n.id,
        n.title,
        n.message,
        n.created_at,
        u.username AS professor_name
    FROM notifications n
    JOIN users u ON u.id = n.professor_id
    JOIN user_subjects us ON us.subject_id = n.subject_id
    WHERE us.user_id = :user_id
    ORDER BY n.created_at DESC
");

$stmt->execute(['user_id' => $userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Obavijesti';

require __DIR__ . '/header.php';
?>

<main class="page-shell notifications-page">

    <div class="notifications-heading">
        <div>
            <p class="eyebrow">CLASSROOM MANAGER</p>
            <h1>Obavijesti</h1>
            <p class="muted">
                Ovdje možete pregledati obavijesti profesora
                iz predmeta koje pohađate.
            </p>
        </div>

        <div class="notification-count">
            <?= count($notifications) ?> obavijesti
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

                <article class="notification-card">

                    <div class="notification-card-top">
                        <div>
                            <h2><?= e($notification['title']) ?></h2>

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

                    <p class="notification-message">
                        <?= nl2br(e($notification['message'])) ?>
                    </p>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<?php require __DIR__ . '/footer.php'; ?>
