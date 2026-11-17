<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('profile.php');
}

$error = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu i pokušajte ponovno.';
    } elseif (!preg_match('/\A.{1,150}\z/us', $username) || preg_match('/[\x00-\x1F\x7F]/', $username)) {
        $error = 'Unesite valjano korisničko ime.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Unesite valjanu e-mail adresu.';
    } elseif (strlen($password) < 8) {
        $error = 'Lozinka mora sadržavati najmanje 8 znakova.';
    } elseif ($password !== $confirmation) {
        $error = 'Lozinke se ne podudaraju.';
    } else {
        try {
            $statement = db()->prepare(
                'INSERT INTO users (Username, Email, Password) VALUES (:username, :email, :password)'
            );
            $statement->execute([
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            session_regenerate_id(true);
            $_SESSION['user_email'] = $email;
            unset($_SESSION['csrf_token']);
            redirect('profile.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Korisničko ime ili e-mail adresa već se koriste.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Registracija';
require __DIR__ . '/header.php';
?>
<section class="form-layout">
    <div class="form-intro">
        <p class="eyebrow">NOVI RAČUN</p>
        <h1>Pridružite se.</h1>
        <p class="lead">Registracijom dobivate osnovni korisnički račun.</p>
    </div>
    <form class="form-panel" method="post" action="register.php">
        <h2>Registracija</h2>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="username">Korisničko ime</label>
        <input id="username" name="username" type="text" maxlength="150" autocomplete="username" value="<?= e($username) ?>" required>
        <label for="email">E-mail adresa</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= e($email) ?>" required>
        <label for="password">Lozinka</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        <label for="password_confirmation">Ponovite lozinku</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
        <button class="button button-primary button-full" type="submit">Izradi račun</button>
        <p class="form-footnote">Već imate račun? <a href="login.php">Prijavite se</a>.</p>
    </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>