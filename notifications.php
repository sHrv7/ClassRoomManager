
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'] ?? null;
$isProfessor = ($user['Role'] ?? '') === 'Professor';
$isAdmin = ($user['Role'] ?? '') === 'Admin';
$notice = (string) ($_SESSION['notification_notice'] ?? '');
$noticeType = (string) ($_SESSION['notification_notice_type'] ?? 'success');
unset($_SESSION['notification_notice']);
unset($_SESSION['notification_notice_type']);

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
        n.category_id,
        n.is_draft,
        c.name AS category_name,
        s.name AS subject_name,
        u.username AS professor_name
    FROM notifications n
    JOIN users u ON u.id = n.professor_id
    LEFT JOIN categories c ON c.id = n.category_id
    LEFT JOIN subjects s ON s.id = n.subject_id
    LEFT JOIN user_subjects us
        ON us.subject_id = n.subject_id
        AND us.user_id = :user_id
    WHERE (
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
    ORDER BY n.created_at DESC
");

$stmt->execute([
    'user_id' => $userId,
    'is_admin' => $isAdmin ? 1 : 0,
    'author_id' => $userId,
    'is_admin_draft' => $isAdmin ? 1 : 0,
    'draft_author_id' => $userId,
]);

$allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCategory = (int)($_GET['category'] ?? 0);
$selectedSubject = (int)($_GET['subject'] ?? 0);
$searchTerm = trim($_GET['search'] ?? '');

// Get available categories and subjects from visible notifications
$categories = [];
$subjects = [];

foreach ($allNotifications as $notification) {
    if (!empty($notification['category_id'])) {
        $categories[$notification['category_id']] =
            $notification['category_name'];
    }

    if (!empty($notification['subject_id'])) {
        $subjects[$notification['subject_id']] =
            $notification['subject_name'];
    }
}

$isGeneralNewsCategory = (
    isset($categories[$selectedCategory]) &&
    $categories[$selectedCategory] === 'Opće vijesti'
);

if ($isGeneralNewsCategory) {
    $selectedSubject = 0;
}

// Apply filters and search
$notifications = array_filter(
    $allNotifications,
    function ($notification) use (
        $selectedCategory,
        $selectedSubject,
        $searchTerm
    ) {

        // Category filter
        if (
            $selectedCategory > 0 &&
            (int)$notification['category_id'] !== $selectedCategory
        ) {
            return false;
        }

        // Subject filter
        if (
            $selectedSubject > 0 &&
            (int)$notification['subject_id'] !== $selectedSubject
        ) {
            return false;
        }

        // Search title and message
        if ($searchTerm !== '') {
            $searchTitle = stripos(
                $notification['title'],
                $searchTerm
            ) !== false;

            $searchMessage = stripos(
                $notification['message'],
                $searchTerm
            ) !== false;

            if (!$searchTitle && !$searchMessage) {
                return false;
            }
        }

        return true;
    }
);

$notifications = array_values($notifications);

// Pagination
$totalNotifications = count($notifications);
$perPage = 8;

$totalPages = max(1, (int)ceil($totalNotifications / $perPage));

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $perPage;

$notifications = array_slice($notifications, $offset, $perPage);

$pageTitle = 'Obavijesti';

require __DIR__ . '/header.php';
?>

<main class="page-shell notifications-page">

    <?php if ($notice !== ''): ?>
        <p class="<?= $noticeType === 'error' ? 'notice notice-error' : 'notice notice-success' ?>" role="status">
            <?= e($notice) ?>
        </p>
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
                <?= $totalNotifications ?> obavijesti
            </div>

            <?php if ($isProfessor): ?>
                <a href="create_post.php" class="button button-primary">
                    + Nova objava
                </a>
            <?php endif; ?>

        </div>
    </div>

    <form method="GET" class="notification-filters">

        <input
            type="search"
            name="search"
            placeholder="Pretraži obavijesti..."
            value="<?= e($searchTerm) ?>"
            class="notification-search"
        >

        <select name="category" onchange="this.form.submit()">
            <option value="0">Sve kategorije</option>

            <?php foreach ($categories as $id => $name): ?>
                <option
                    value="<?= (int)$id ?>"
                    <?= $selectedCategory === (int)$id ? 'selected' : '' ?>
                >
                    <?= e($name) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select
            name="subject"
            onchange="this.form.submit()"
            <?= $isGeneralNewsCategory ? 'disabled' : '' ?>
        >
            <option value="0">Svi predmeti</option>

            <?php foreach ($subjects as $id => $name): ?>
                <option
                    value="<?= (int)$id ?>"
                    <?= $selectedSubject === (int)$id ? 'selected' : '' ?>
                >
                    <?= e($name) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-primary">
            Pretraži
        </button>

        <a href="notifications.php" class="button button-secondary">
            Poništi filtere
        </a>

    </form>

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
                            <h2>
                                <?= e($notification['title'] !== '' ? $notification['title'] : 'Nacrt bez naslova') ?>
                                <?php if ((int) $notification['is_draft'] === 1): ?>
                                    <span class="notification-draft-badge">Nacrt</span>
                                <?php endif; ?>
                            </h2>

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

    <?php if ($totalPages > 1): ?>

    <?php
    $paginationBase = 'notifications.php?' . http_build_query([
        'category' => $selectedCategory,
        'subject' => $selectedSubject,
        'search' => $searchTerm
    ]);
    ?>

    <nav class="notification-pagination" aria-label="Stranice obavijesti">

        <?php if ($currentPage > 1): ?>
            <a
                href="<?= e($paginationBase . '&page=' . ($currentPage - 1)) ?>"
                class="pagination-button"
            >
                &laquo; Prethodna
            </a>
        <?php endif; ?>

        <div class="pagination-numbers">

            <?php for ($page = 1; $page <= $totalPages; $page++): ?>

                <a
                    href="<?= e($paginationBase . '&page=' . $page) ?>"
                    class="pagination-number <?= $page === $currentPage ? 'active' : '' ?>"
                    <?= $page === $currentPage ? 'aria-current="page"' : '' ?>
                >
                    <?= $page ?>
                </a>

            <?php endfor; ?>

        </div>

        <?php if ($currentPage < $totalPages): ?>
            <a
                href="<?= e($paginationBase . '&page=' . ($currentPage + 1)) ?>"
                class="pagination-button"
            >
                Sljedeća &raquo;
            </a>
        <?php endif; ?>

    </nav>

<?php endif; ?>

</main>

<?php require __DIR__ . '/footer.php'; ?>
