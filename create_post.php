
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'] ?? null;

if (($user['Role'] ?? '') !== 'Profesor') {
    http_response_code(403);
    exit('Access denied.');
}

if (!$userId) {
    exit('User ID not found.');
}

$error = '';
$pageTitle = 'Nova objava';

// Check whether the image column exists.
$imageColumnExists = (bool) db()->query(
    "SHOW COLUMNS FROM notifications LIKE 'image'"
)->fetch(PDO::FETCH_ASSOC);

// Get categories.
$stmt = db()->query("
    SELECT id, name
    FROM categories
    ORDER BY name
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects available to this professor.
$stmt = db()->prepare("
    SELECT s.id, s.name
    FROM subjects s
    INNER JOIN user_subjects us ON us.subject_id = s.id
    WHERE us.user_id = :user_id
    ORDER BY s.name
");

$stmt->execute(['user_id' => $userId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_is_valid()) {
        $error = 'Neispravan sigurnosni token. Pokušajte ponovno.';
    } else {

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate category.
        $stmt = db()->prepare("
            SELECT name
            FROM categories
            WHERE id = :category_id
        ");

        $stmt->execute(['category_id' => $categoryId]);
        $categoryName = $stmt->fetchColumn();

        if (!$categoryName) {
            $error = 'Odaberite valjanu kategoriju.';
        } elseif ($title === '' || $message === '') {
            $error = 'Naslov i tekst objave su obavezni.';
        } else {

            // General news do not require a subject.
            if ($categoryName === 'Opće vijesti') {

                $subjectId = null;

            } else {

                // All other categories require a valid subscribed subject.
                if ($subjectId <= 0) {
                    $error = 'Za odabranu kategoriju morate odabrati predmet.';
                } else {

                    $stmt = db()->prepare("
                        SELECT id
                        FROM subjects
                        WHERE id = :subject_id
                        AND EXISTS (
                            SELECT 1
                            FROM user_subjects
                            WHERE subject_id = subjects.id
                            AND user_id = :user_id
                        )
                    ");

                    $stmt->execute([
                        'subject_id' => $subjectId,
                        'user_id' => $userId
                    ]);

                    if (!$stmt->fetchColumn()) {
                        $error = 'Odaberite valjani predmet.';
                    }
                }
            }

            $imagePath = null;
            $imageFile = $_FILES['image'] ?? null;

            $hasImage = $imageFile &&
                $imageFile['error'] !== UPLOAD_ERR_NO_FILE;

            // Image upload.
            if (!$error && $hasImage) {

                if (!$imageColumnExists) {
                    $error = 'Za objavu slike prvo dodajte image stupac u tablicu notifications.';
                } elseif ($imageFile['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Došlo je do pogreške prilikom prijenosa slike.';
                } elseif ($imageFile['size'] > 5 * 1024 * 1024) {
                    $error = 'Slika ne smije biti veća od 5 MB.';
                } else {

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($imageFile['tmp_name']);

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif'
                    ];

                    if (!isset($allowedTypes[$mimeType])) {
                        $error = 'Dopuštene su JPG, PNG, WEBP i GIF slike.';
                    } else {

                        $uploadDirectory = __DIR__ . '/uploads/announcements/';

                        if (
                            !is_dir($uploadDirectory) &&
                            !mkdir($uploadDirectory, 0755, true)
                        ) {
                            $error = 'Nije moguće stvoriti mapu za slike.';
                        } else {

                            $fileName = bin2hex(random_bytes(16))
                                . '.' . $allowedTypes[$mimeType];

                            $destination = $uploadDirectory . $fileName;

                            if (move_uploaded_file(
                                $imageFile['tmp_name'],
                                $destination
                            )) {
                                $imagePath = 'uploads/announcements/' . $fileName;
                            } else {
                                $error = 'Slika nije uspješno spremljena.';
                            }
                        }
                    }
                }
            }

            // Save the post.
            if ($error === '') {
                try {

                    $stmt = db()->prepare("
                        INSERT INTO notifications
                            (subject_id, professor_id, category_id,
                             title, message, image)
                        VALUES
                            (:subject_id, :professor_id, :category_id,
                             :title, :message, :image)
                    ");

                    $stmt->execute([
                        'subject_id' => $subjectId,
                        'professor_id' => $userId,
                        'category_id' => $categoryId,
                        'title' => $title,
                        'message' => $message,
                        'image' => $imagePath
                    ]);

                    header('Location: create_post.php?created=1');
                    exit;

                } catch (PDOException $e) {

                    // Remove uploaded image if database insertion fails.
                    if ($imagePath !== null) {
                        $file = __DIR__ . '/' . $imagePath;

                        if (is_file($file)) {
                            unlink($file);
                        }
                    }

                    $error = 'Objava nije spremljena. Pokušajte ponovno.';
                }
            }
        }
    }
}

require __DIR__ . '/header.php';
?>

<main class="page-shell create-post-page">

    <div class="notifications-heading">
        <div>
            <p class="eyebrow">CLASSROOM MANAGER</p>
            <h1>Nova objava</h1>
            <p class="muted">
                Objavite obavijest studentima.
            </p>
        </div>

        <a href="notifications.php" class="button button-secondary">
            Natrag na obavijesti
        </a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <p class="success-message">
            Objava je uspješno objavljena.
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error-message">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <section class="notification-card">

        <form method="POST"
              action="create_post.php"
              enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <div class="form-group">
                <label for="category_id">Kategorija</label>

                <select name="category_id"
                        id="category_id"
                        required>

                    <option value="">Odaberite kategoriju...</option>

                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int)$category['id'] ?>"
                            <?= (int)($_POST['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group" id="subjectGroup">
                <label for="subject_id">Predmet</label>

                <select name="subject_id" id="subject_id">
                    <option value="">Odaberite predmet...</option>

                    <?php foreach ($subjects as $subject): ?>
                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= (int)($_POST['subject_id'] ?? 0) === (int)$subject['id'] ? 'selected' : '' ?>
                        >
                            <?= e($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="title">Naslov objave</label>

                <input
                    type="text"
                    name="title"
                    id="title"
                    maxlength="255"
                    value="<?= e($_POST['title'] ?? '') ?>"
                    placeholder="Unesite naslov objave..."
                    required
                >
            </div>

            <div class="form-group">
                <label for="message">Tekst objave</label>

                <textarea
                    name="message"
                    id="message"
                    rows="7"
                    placeholder="Napišite svoju obavijest..."
                    required
                ><?= e($_POST['message'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="image">Slika (nije obavezna)</label>

                <input
                    type="file"
                    name="image"
                    id="image"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                >

                <small class="muted">
                    JPG, PNG, WEBP ili GIF. Maksimalna veličina: 5 MB.
                </small>
            </div>

            <button type="submit" class="button button-primary">
                Objavi
            </button>

        </form>

    </section>

</main>

<script>
const categorySelect = document.getElementById('category_id');
const subjectGroup = document.getElementById('subjectGroup');
const subjectSelect = document.getElementById('subject_id');

function updateSubjectField() {
    const selectedCategory =
        categorySelect.options[categorySelect.selectedIndex];

    const isGeneralNews =
        selectedCategory &&
        selectedCategory.text.trim() === 'Opće vijesti';

    subjectGroup.style.display = isGeneralNews ? 'none' : '';
    subjectSelect.required = !isGeneralNews;

    if (isGeneralNews) {
        subjectSelect.value = '';
    }
}

categorySelect.addEventListener('change', updateSubjectField);
updateSubjectField();
</script>

<?php require __DIR__ . '/footer.php'; ?>
