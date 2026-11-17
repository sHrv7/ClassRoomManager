<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('profile.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu i pokušajte ponovno.';
    } else {
        $statement = db()->prepare(
            'SELECT Email, Password, Active FROM users WHERE Email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();

        if ($account && (int) $account['Active'] === 1 && password_verify($password, $account['Password'])) {
            session_regenerate_id(true);
            $_SESSION['user_email'] = $account['Email'];
            unset($_SESSION['csrf_token']);
            redirect('profile.php');
        }

        $error = 'E-mail adresa ili lozinka nisu ispravni, ili račun nije aktivan.';
    }
}

$pageTitle = 'Prijava';
require __DIR__ . '/header.php';
?>
<section class="form-layout">
    <div class="form-intro">
        <p class="eyebrow">DOBRO DOŠLI NATRAG</p>
        <h1>Prijavite se.</h1>
        <p class="lead">Pristupite svom korisničkom profilu.</p>
    </div>
    <form class="form-panel" method="post" action="login.php">
        <h2>Prijava</h2>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="email">E-mail adresa</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= e($email) ?>" required>
        <label for="password">Lozinka</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-primary button-full" type="submit">Prijavi se</button>
        <p class="form-footnote">Nemate račun? <a href="register.php">Registrirajte se</a>.</p>
    </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>