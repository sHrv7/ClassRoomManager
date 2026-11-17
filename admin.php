<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$admin = require_admin();
$roles = ['Student', 'Professor', 'Admin'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = (string) ($_POST['role'] ?? '');
    $active = (string) ($_POST['active'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu i pokušajte ponovno.';
    } elseif (!in_array($role, $roles, true) || !in_array($active, ['0', '1'], true)) {
        $error = 'Odabrana uloga ili status računa nisu valjani.';
    } elseif (hash_equals($admin['Email'], $email)) {
        $error = 'Ne možete promijeniti vlastitu ulogu ili status.';
    } else {
        $findUser = db()->prepare('SELECT Email FROM users WHERE Email = :email LIMIT 1');
        $findUser->execute(['email' => $email]);

        if (!$findUser->fetch()) {
            $error = 'Korisnički račun nije pronađen.';
        } else {
            $updateUser = db()->prepare(
                'UPDATE users SET Role = :role, Active = :active WHERE Email = :email'
            );
            $updateUser->execute([
                'role' => $role,
                'active' => (int) $active,
                'email' => $email,
            ]);
            $_SESSION['admin_notice'] = 'Podaci korisnika su spremljeni.';
            redirect('admin.php');
        }
    }
}

$message = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);
$users = db()->query('SELECT Username, Email, Role, Active FROM users ORDER BY Username, Email')->fetchAll();
$pageTitle = 'Admin panel';
require __DIR__ . '/header.php';
?>
<section class="admin-layout">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">ADMINISTRACIJA</p>
            <h1>Korisnički računi</h1>
            <p class="lead">Upravljajte ulogama i pristupom korisnika.</p>
        </div>
        <span class="user-count"><?= count($users) ?> korisnika</span>
    </div>

    <?php if ($message !== ''): ?><p class="notice notice-success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="user-table-wrap">
        <table class="user-table">
            <thead>
                <tr>
                    <th scope="col">Korisnik</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Uloga</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Akcija</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $listedUser): ?>
                    <tr>
                        <td data-label="Korisnik"><?= e($listedUser['Username']) ?></td>
                        <td class="user-email" data-label="E-mail"><?= e($listedUser['Email']) ?></td>
                        <?php if (hash_equals($admin['Email'], $listedUser['Email'])): ?>
                            <td data-label="Uloga"><?= e($listedUser['Role']) ?></td>
                            <td data-label="Status"><span class="status-pill status-active">Aktivan</span></td>
                            <td class="user-action">Vaš račun</td>
                        <?php else: ?>
                            <td colspan="3" class="user-edit-cell">
                                <form class="user-edit-form" method="post" action="admin.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="email" value="<?= e($listedUser['Email']) ?>">
                                    <label class="visually-hidden" for="role-<?= e(md5($listedUser['Email'])) ?>">Uloga za <?= e($listedUser['Username']) ?></label>
                                    <select id="role-<?= e(md5($listedUser['Email'])) ?>" name="role">
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= e($role) ?>" <?= $listedUser['Role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="visually-hidden" for="active-<?= e(md5($listedUser['Email'])) ?>">Status računa za <?= e($listedUser['Username']) ?></label>
                                    <select id="active-<?= e(md5($listedUser['Email'])) ?>" name="active">
                                        <option value="1" <?= (int) $listedUser['Active'] === 1 ? 'selected' : '' ?>>Aktivan</option>
                                        <option value="0" <?= (int) $listedUser['Active'] !== 1 ? 'selected' : '' ?>>Deaktiviran</option>
                                    </select>
                                    <button class="button button-primary" type="submit">Spremi</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr><td class="empty-users" colspan="5">Nema registriranih korisnika.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>