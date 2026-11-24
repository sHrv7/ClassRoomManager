<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_login();
if (($user['Role'] ?? '') !== 'Professor') {
    http_response_code(403);
    exit('Access denied.');
}

$userId = (int) ($user['id'] ?? $user['ID'] ?? 0);
if ($userId < 1) {
    exit('User ID not found.');
}

$error = '';
$message = '';
$title = '';
$categoryId = 0;
$subjectId = 0;
$imageColumnExists = (bool) db()->query(
    "SHOW COLUMNS FROM notifications LIKE 'image'"
)->fetch(PDO::FETCH_ASSOC);
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$subjectsQuery = db()->prepare(
    'SELECT s.id, s.name FROM subjects s '
    . 'INNER JOIN user_subjects us ON us.subject_id = s.id '
    . 'WHERE us.user_id = :user_id ORDER BY s.name'
);
$subjectsQuery->execute(['user_id' => $userId]);
$subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['post_action'] ?? 'publish');
    $title = trim((string) ($_POST['title'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $message = sanitize_rich_text(trim((string) ($_POST['message'] ?? '')));
    $plainMessage = preg_replace(
        '/[\s\x{00A0}]+/u',
        '',
        html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    ) ?? '';

    if (!in_array($postAction, ['draft', 'publish'], true)) {
        $error = 'Odabrana akcija nije valjana.';
    } elseif (!csrf_is_valid()) {
        $error = 'Neispravan sigurnosni token. Pokušajte ponovno.';
    } elseif ($postAction === 'publish' && ($title === '' || $plainMessage === '')) {
        $error = 'Naslov i tekst objave su obavezni.';
    } elseif (strlen($title) > 255) {
        $error = 'Naslov može imati najviše 255 znakova.';
    } else {
        $categoryQuery = db()->prepare('SELECT name FROM categories WHERE id = :id');
        $categoryQuery->execute(['id' => $categoryId]);
        $categoryName = $categoryQuery->fetchColumn();

        if (!$categoryName) {
            $error = 'Odaberite valjanu kategoriju.';
        } elseif ($categoryName === 'Opće vijesti') {
            $subjectId = 0;
        } elseif ($subjectId < 1) {
            if ($postAction === 'publish') {
                $error = 'Za odabranu kategoriju morate odabrati predmet.';
            }
        } else {
            $validSubject = db()->prepare(
                'SELECT s.id FROM subjects s '
                . 'INNER JOIN user_subjects us ON us.subject_id = s.id '
                . 'WHERE s.id = :subject_id AND us.user_id = :user_id'
            );
            $validSubject->execute(['subject_id' => $subjectId, 'user_id' => $userId]);
            if (!$validSubject->fetchColumn()) {
                $error = 'Odaberite valjani predmet.';
            }
        }
    }

    $imagePath = null;
    $imageFile = $_FILES['image'] ?? null;
    $hasImage = $imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE;

    if ($error === '' && $hasImage) {
        if (!$imageColumnExists) {
            $error = 'Za objavu slike prvo dodajte image stupac u tablicu notifications.';
        } elseif ($imageFile['error'] !== UPLOAD_ERR_OK) {
            $error = 'Došlo je do pogreške prilikom prijenosa slike.';
        } elseif ($imageFile['size'] > 5 * 1024 * 1024) {
            $error = 'Slika ne smije biti veća od 5 MB.';
        } else {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($imageFile['tmp_name']);
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            if (!isset($allowedTypes[$mimeType])) {
                $error = 'Dopuštene su JPG, PNG, WEBP i GIF slike.';
            } else {
                $uploadDirectory = __DIR__ . '/uploads/announcements/';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                    $error = 'Nije moguće stvoriti mapu za slike.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                    if (move_uploaded_file($imageFile['tmp_name'], $uploadDirectory . $fileName)) {
                        $imagePath = 'uploads/announcements/' . $fileName;
                    } else {
                        $error = 'Slika nije uspješno spremljena.';
                    }
                }
            }
        }
    }

    if ($error === '') {
        try {
            $insert = db()->prepare(
                'INSERT INTO notifications '
                . '(subject_id, professor_id, category_id, title, message, image, is_draft) '
                . 'VALUES (:subject_id, :professor_id, :category_id, :title, :message, :image, :is_draft)'
            );
            $insert->execute([
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'professor_id' => $userId,
                'category_id' => $categoryId,
                'title' => $title,
                'message' => $message,
                'image' => $imagePath,
                'is_draft' => $postAction === 'draft' ? 1 : 0,
            ]);
            if ($postAction === 'draft') {
                $_SESSION['notification_notice'] = 'Obavijest je spremljena kao nacrt.';
            } else {
                $delivery = send_notification_emails([
                    'subject_id' => $subjectId > 0 ? $subjectId : null,
                    'professor_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                ]);
                $_SESSION['notification_notice_type'] = in_array($delivery['status'], ['sent', 'no_recipients'], true)
                    ? 'success'
                    : 'error';
                $_SESSION['notification_notice'] = match ($delivery['status']) {
                    'sent' => 'Obavijest je objavljena i poslana na e-mail '
                        . $delivery['count'] . ' studenata.',
                    'no_recipients' => 'Obavijest je objavljena; nema aktivnih studenata za obavijest.',
                    'not_configured' => 'Obavijest je objavljena, ali EmailJS nije konfiguriran.',
                    default => 'Obavijest je objavljena, ali slanje e-maila nije uspjelo: '
                        . ($delivery['reason'] ?? $delivery['status']),
                };
            }
            redirect('notifications.php');
        } catch (PDOException $exception) {
            if ($imagePath !== null && is_file(__DIR__ . '/' . $imagePath)) {
                unlink(__DIR__ . '/' . $imagePath);
            }
            $error = 'Objava nije spremljena. Pokušajte ponovno.';
        }
    } elseif ($imagePath !== null && is_file(__DIR__ . '/' . $imagePath)) {
        unlink(__DIR__ . '/' . $imagePath);
    }
}

$pageTitle = 'Nova objava';
require __DIR__ . '/header.php';
$formHeading = 'Nova objava';
$formAction = 'create_post.php';
$formSubmitLabel = 'Objavi';
$formError = $error;
$formTitle = $title;
$formMessage = $message;
$formCategoryId = $categoryId;
$formSubjectId = $subjectId;
$formImage = null;
$formNotificationId = null;
$showCreated = isset($_GET['created']);
require __DIR__ . '/post_form.php';
require __DIR__ . '/footer.php';