<?php
/** Uredjuje objavu uz provjeru vlasnika ili administratorskih ovlasti. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_login();
$userId = (int) ($user['id'] ?? $user['ID'] ?? 0);
$isAdmin = ($user['Role'] ?? '') === 'Admin';
$notificationId = filter_var(
    $_POST['notification_id'] ?? $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$notificationId || $notificationId < 1) {
    http_response_code(404);
    exit('Obavijest nije pronađena.');
}

$findNotification = db()->prepare(
    'SELECT id, subject_id, professor_id, category_id, title, message, image '
    . 'FROM notifications WHERE id = :id LIMIT 1'
);
$findNotification->execute(['id' => $notificationId]);
$notification = $findNotification->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    http_response_code(404);
    exit('Obavijest nije pronađena.');
}

if (!$isAdmin && (int) $notification['professor_id'] !== $userId) {
    http_response_code(403);
    exit('Nemate ovlasti za uređivanje ove obavijesti.');
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$subjectsQuery = db()->prepare(
    'SELECT s.id, s.name FROM subjects s '
    . 'INNER JOIN user_subjects us ON us.subject_id = s.id '
    . 'WHERE us.user_id = :user_id ORDER BY s.name'
);
$subjectsQuery->execute(['user_id' => $notification['professor_id']]);
$subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);

$title = (string) ($_POST['title'] ?? $notification['title']);
$message = (string) ($_POST['message'] ?? $notification['message']);
$categoryId = (int) ($_POST['category_id'] ?? $notification['category_id']);
$subjectId = (int) ($_POST['subject_id'] ?? $notification['subject_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_is_valid()) {
        $error = 'Neispravan sigurnosni token. Osvježite stranicu i pokušajte ponovno.';
    } else {
        $deleteSql = $isAdmin
            ? 'DELETE FROM notifications WHERE id = :id'
            : 'DELETE FROM notifications WHERE id = :id AND professor_id = :professor_id';
        $deleteNotification = db()->prepare($deleteSql);
        $deleteParameters = ['id' => $notificationId];
        if (!$isAdmin) {
            $deleteParameters['professor_id'] = $userId;
        }

        try {
            $deleteNotification->execute($deleteParameters);
            if ($deleteNotification->rowCount() !== 1) {
                http_response_code(404);
                exit('Obavijest nije pronađena.');
            }

            $imagePath = (string) ($notification['image'] ?? '');
            if ($imagePath !== ''
                && str_starts_with($imagePath, 'uploads/announcements/')
                && basename($imagePath) === substr($imagePath, strlen('uploads/announcements/'))) {
                $imageFile = __DIR__ . '/' . $imagePath;
                if (is_file($imageFile)) {
                    unlink($imageFile);
                }
            }

            $_SESSION['notification_notice'] = 'Obavijest je obrisana.';
            redirect('notifications.php');
        } catch (PDOException $exception) {
            $error = 'Obavijest nije obrisana. Pokušajte ponovno.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
    $title = trim($title);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $message = sanitize_rich_text(trim($message));
    $plainMessage = preg_replace(
        '/[\s\x{00A0}]+/u',
        '',
        html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    ) ?? '';

    if (!csrf_is_valid()) {
        $error = 'Neispravan sigurnosni token. Osvježite stranicu i pokušajte ponovno.';
    } elseif ($title === '' || strlen($title) > 255 || $plainMessage === '') {
        $error = 'Naslov i tekst obavijesti su obavezni.';
    } else {
        $categoryQuery = db()->prepare('SELECT name FROM categories WHERE id = :id');
        $categoryQuery->execute(['id' => $categoryId]);
        $categoryName = $categoryQuery->fetchColumn();

        if (!$categoryName) {
            $error = 'Odaberite valjanu kategoriju.';
        } elseif ($categoryName === 'Opće vijesti') {
            $subjectId = 0;
        } elseif ($subjectId < 1) {
            $error = 'Za odabranu kategoriju morate odabrati predmet.';
        } else {
            $validSubject = db()->prepare(
                'SELECT s.id FROM subjects s '
                . 'INNER JOIN user_subjects us ON us.subject_id = s.id '
                . 'WHERE s.id = :subject_id AND us.user_id = :professor_id'
            );
            $validSubject->execute([
                'subject_id' => $subjectId,
                'professor_id' => $notification['professor_id'],
            ]);
            if (!$validSubject->fetchColumn()) {
                $error = 'Odaberite valjani predmet autora obavijesti.';
            }
        }
    }

    $newImagePath = null;
    $imageFile = $_FILES['image'] ?? null;
    $hasImage = $imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE;

    if ($error === '' && $hasImage) {
        if ($imageFile['error'] !== UPLOAD_ERR_OK) {
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
                        $newImagePath = 'uploads/announcements/' . $fileName;
                    } else {
                        $error = 'Slika nije uspješno spremljena.';
                    }
                }
            }
        }
    }

    if ($error === '') {
        $imagePath = $newImagePath
            ?? (!empty($_POST['remove_image']) ? null : $notification['image']);

        try {
            $updateNotification = db()->prepare(
                'UPDATE notifications SET subject_id = :subject_id, category_id = :category_id, '
                . 'title = :title, message = :message, image = :image WHERE id = :id'
            );
            $updateNotification->execute([
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'category_id' => $categoryId,
                'title' => $title,
                'message' => $message,
                'image' => $imagePath,
                'id' => $notificationId,
            ]);

            $oldImage = $notification['image'];
            if ($oldImage && $oldImage !== $imagePath
                && str_starts_with($oldImage, 'uploads/announcements/')
                && basename($oldImage) === substr($oldImage, strlen('uploads/announcements/'))) {
                $oldImageFile = __DIR__ . '/' . $oldImage;
                if (is_file($oldImageFile)) {
                    unlink($oldImageFile);
                }
            }

            $_SESSION['notification_notice'] = 'Obavijest je uspješno ažurirana.';
            redirect('notifications.php');
        } catch (PDOException $exception) {
            if ($newImagePath !== null && is_file(__DIR__ . '/' . $newImagePath)) {
                unlink(__DIR__ . '/' . $newImagePath);
            }
            $error = 'Obavijest nije spremljena. Pokušajte ponovno.';
        }
    } elseif ($newImagePath !== null && is_file(__DIR__ . '/' . $newImagePath)) {
        unlink(__DIR__ . '/' . $newImagePath);
    }
}

$pageTitle = 'Uredi obavijest';
require __DIR__ . '/header.php';
$formHeading = 'Uredi obavijest';
$formAction = 'edit_post.php?id=' . (int) $notificationId;
$formSubmitLabel = 'Spremi promjene';
$formError = $error;
$formTitle = $title;
$formMessage = $message;
$formCategoryId = $categoryId;
$formSubjectId = $subjectId;
$formImage = $notification['image'];
$formNotificationId = (int) $notificationId;
require __DIR__ . '/post_form.php';
require __DIR__ . '/footer.php';