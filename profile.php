<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$user = require_login();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu i pokušajte ponovno.';
    } elseif (!password_verify($currentPassword, $user['Password'])) {
        $error = 'Trenutna lozinka nije ispravna.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Nova lozinka mora sadržavati najmanje 8 znakova.';
    } elseif ($newPassword !== $confirmation) {
        $error = 'Nove lozinke se ne podudaraju.';
    } else {
        $statement = db()->prepare('UPDATE users SET Password = :password WHERE Email = :email');
        $statement->execute([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'email' => $user['Email'],
        ]);
        unset($_SESSION['csrf_token']);
        $message = 'Lozinka je uspješno promijenjena.';
        $user = current_user();
    }
}


// Get subscribed subjects
$statement = db()->prepare("
    SELECT s.id, s.name
    FROM subjects s
    JOIN user_subjects us ON us.subject_id = s.id
    WHERE us.user_id = :user_id
    ORDER BY s.name
");
$statement->execute(['user_id' => $user['ID'] ?? $user['id']]);
$subscribedSubjects = $statement->fetchAll(PDO::FETCH_ASSOC);

// Get available subjects
$statement = db()->prepare("
    SELECT s.id, s.name
    FROM subjects s
    WHERE NOT EXISTS (
        SELECT 1
        FROM user_subjects us
        WHERE us.subject_id = s.id
        AND us.user_id = :user_id
    )
    ORDER BY s.name
");
$statement->execute(['user_id' => $user['ID'] ?? $user['id']]);
$availableSubjects = $statement->fetchAll(PDO::FETCH_ASSOC);

// Handle subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_action'])) {

    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu.';
    } else {
        $subjectId = (int)($_POST['subject_id'] ?? 0);

        if ($_POST['subject_action'] === 'subscribe') {
            $stmt = db()->prepare("
                INSERT IGNORE INTO user_subjects (user_id, subject_id)
                SELECT :user_id, id
                FROM subjects
                WHERE id = :subject_id
            ");
            $stmt->execute([
                'user_id' => $user['ID'] ?? $user['id'],
                'subject_id' => $subjectId
            ]);

            $message = 'Uspješno ste se pretplatili na predmet.';
        }

        if ($_POST['subject_action'] === 'unsubscribe') {
            $stmt = db()->prepare("
                DELETE FROM user_subjects
                WHERE user_id = :user_id AND subject_id = :subject_id
            ");
            $stmt->execute([
                'user_id' => $user['ID'] ?? $user['id'],
                'subject_id' => $subjectId
            ]);

            $message = 'Odjavili ste se s predmeta.';
        }

        // Refresh subject lists
        redirect('profile.php');
    }
}


$pageTitle = 'Moj profil';
require __DIR__ . '/header.php';
?>
<section class="profile-layout">
    <div class="profile-heading">
        <p class="eyebrow">KORISNIČKI RAČUN</p>
        <h1>Moj profil</h1>
        <p class="lead">Upravljajte svojim podacima za prijavu.</p>
    </div>
    <div class="profile-grid">
        <section class="profile-panel" aria-labelledby="account-heading">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">01</span>
                <h2 id="account-heading">Podaci računa</h2>
            </div>
            <dl class="account-details">
                <div><dt>Korisničko ime</dt><dd><?= e($user['Username']) ?></dd></div>
                <div><dt>E-mail adresa</dt><dd><?= e($user['Email']) ?></dd></div>
                <div><dt>Uloga</dt><dd><?= e($user['Role']) ?></dd></div>
            </dl>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="button button-secondary" type="submit">Odjavi se</button>
            </form>
        </section>
        <form class="profile-panel password-form" method="post" action="profile.php">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">02</span>
                <h2>Promjena lozinke</h2>
            </div>
            <?php if ($message !== ''): ?><p class="notice notice-success" role="status"><?= e($message) ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="current_password">Trenutna lozinka</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            <label for="new_password">Nova lozinka</label>
            <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
            <label for="password_confirmation">Ponovite novu lozinku</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            <button class="button button-primary" type="submit">Promijeni lozinku</button>
        </form>

    <section class="profile-panel subject-panel">

    <div class="panel-heading">
        <span class="panel-icon" aria-hidden="true">03</span>
        <h2>Moji predmeti</h2>
    </div>

    <p class="muted">
        Pretplatite se na predmete kako biste primali njihove obavijesti.
    </p>

    <form method="POST" action="profile.php" class="subject-subscribe-form">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="subject_action" value="subscribe">

        <label for="subject_id">Odaberite predmet</label>

        <select name="subject_id" id="subject_id" required>
            <option value="">Odaberite predmet...</option>

            <?php foreach ($availableSubjects as $subject): ?>
                <option value="<?= (int)$subject['id'] ?>">
                    <?= e($subject['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="button button-primary" type="submit">
            Pretplati se
        </button>

    </form>

    <div class="subscribed-subjects">
        <h3>Moje pretplate</h3>

        <?php if (empty($subscribedSubjects)): ?>

            <p class="muted">Trenutno niste pretplaćeni ni na jedan predmet.</p>

        <?php else: ?>

            <?php foreach ($subscribedSubjects as $subject): ?>

                <div class="subject-item">
                    <span><?= e($subject['name']) ?></span>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="subject_action" value="unsubscribe">
                        <input type="hidden" name="subject_id" value="<?= (int)$subject['id'] ?>">

                        <button type="submit" class="subject-remove" title="Odjavi se s predmeta" aria-label="Odjavi se s predmeta">
                            &times;
                        </button>
                    </form>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>

</section>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>