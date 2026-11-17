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
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>