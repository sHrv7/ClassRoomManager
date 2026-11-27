
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

session_write_close();

if (!$userId) {
    exit('User ID not found.');
}

// Get notifications for subjects the logged-in student is enrolled in

// Read filters from the URL
$selectedCategory = (int)($_GET['category'] ?? 0);
$selectedSubject = (int)($_GET['subject'] ?? 0);
$searchTerm = trim($_GET['search'] ?? '');

$perPage = 8;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Build API URL
$apiBase = 'http://' . $_SERVER['HTTP_HOST'] . '/ClassRoomManager/api/v1/';


function notification_api_get(string $url): array
{
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

    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        throw new RuntimeException('API connection failed.');
    }

    $result = json_decode($response, true);

    if (!is_array($result) || !($result['success'] ?? false)) {
        throw new RuntimeException('Could not load data from the API.');
    }

    return $result;
}


try {
    // Get categories from the API
    $categoryResponse = notification_api_get(
        $apiBase . 'categories'
    );

    $categories = [];

    foreach ($categoryResponse['data'] as $category) {
        $categories[(int)$category['id']] = $category['name'];
    }

    // Get subjects for the current user
    if ($isAdmin) {
        $subjectStmt = db()->query(
            'SELECT id, name FROM subjects ORDER BY name ASC'
        );
    } else {
        $subjectStmt = db()->prepare(
            'SELECT DISTINCT s.id, s.name
             FROM subjects s
             JOIN user_subjects us ON us.subject_id = s.id
             WHERE us.user_id = :user_id
             ORDER BY s.name ASC'
        );

        $subjectStmt->execute(['user_id' => $userId]);
    }

    $subjects = [];

    foreach ($subjectStmt->fetchAll(PDO::FETCH_ASSOC) as $subject) {
        $subjects[(int)$subject['id']] = $subject['name'];
    }

    // Detect general news category
    $isGeneralNewsCategory = (
        isset($categories[$selectedCategory]) &&
        $categories[$selectedCategory] === 'Opće vijesti'
    );

    if ($isGeneralNewsCategory) {
        $selectedSubject = 0;
    }

    // Build API query parameters
    $apiParams = [
        'page' => $currentPage,
        'limit' => $perPage
    ];

    if ($selectedCategory > 0) {
        $apiParams['category_id'] = $selectedCategory;
    }

    if ($selectedSubject > 0) {
        $apiParams['subject_id'] = $selectedSubject;
    }

    if ($searchTerm !== '') {
        $apiParams['search'] = $searchTerm;
    }

    $notificationsUrl = $apiBase . 'notifications?' .
        http_build_query($apiParams);

    // Get notifications through the API
    $notificationResponse = notification_api_get(
        $notificationsUrl
    );

    $notifications = $notificationResponse['data'];

    // Read pagination information from the API
    $pagination = $notificationResponse['pagination'];

    $totalNotifications = (int)$pagination['total'];
    $totalPages = max(1, (int)$pagination['total_pages']);
    $currentPage = (int)$pagination['page'];

} catch (Throwable $exception) {
    error_log($exception->getMessage());

    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 1;
    $currentPage = 1;

    $notice = 'Obavijesti trenutno nije moguće učitati.';
    $noticeType = 'error';
}

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
