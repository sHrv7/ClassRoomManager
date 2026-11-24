<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$admin = require_admin();
$roles = ['Student', 'Professor', 'Admin'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'user_update');
    if (!csrf_is_valid()) {
        $error = 'Zahtjev je istekao. Osvježite stranicu i pokušajte ponovno.';
    } elseif ($action === 'classroom_create' || $action === 'classroom_update') {
        $roomCode = trim((string) ($_POST['room_code'] ?? ''));
        $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $classroomId = filter_var($_POST['classroom_id'] ?? null, FILTER_VALIDATE_INT);

        if ($roomCode === '' || strlen($roomCode) > 30 || preg_match('/[\x00-\x1F\x7F]/', $roomCode)) {
            $error = 'Unesite valjanu oznaku učionice (najviše 30 znakova).';
        } elseif ($capacity === false) {
            $error = 'Kapacitet mora biti pozitivan cijeli broj.';
        } elseif ($action === 'classroom_update' && (!$classroomId || $classroomId < 1)) {
            $error = 'Učionica nije pronađena.';
        } else {
            $duplicateSql = 'SELECT id FROM classrooms WHERE room_code = :room_code';
            $duplicateParameters = ['room_code' => $roomCode];
            if ($action === 'classroom_update') {
                $duplicateSql .= ' AND id <> :id';
                $duplicateParameters['id'] = $classroomId;
            }
            $duplicateSql .= ' LIMIT 1';
            $duplicate = db()->prepare($duplicateSql);
            $duplicate->execute($duplicateParameters);

            if ($duplicate->fetchColumn()) {
                $error = 'Učionica s tom oznakom već postoji.';
            } elseif ($action === 'classroom_create') {
                $insertClassroom = db()->prepare(
                    'INSERT INTO classrooms (room_code, capacity) VALUES (:room_code, :capacity)'
                );
                $insertClassroom->execute(['room_code' => $roomCode, 'capacity' => $capacity]);
                $_SESSION['admin_notice'] = 'Učionica je dodana.';
                redirect('admin.php');
            } else {
                $overCapacityReservations = db()->prepare(
                    'SELECT COUNT(*) FROM reservations '
                    . 'WHERE classroom_id = :id AND expected_students > :capacity'
                );
                $overCapacityReservations->execute([
                    'id' => $classroomId,
                    'capacity' => $capacity,
                ]);

                if ($overCapacityReservations->fetchColumn() > 0) {
                    $error = 'Kapacitet ne može biti manji od broja studenata u postojećoj rezervaciji.';
                } else {
                    $updateClassroom = db()->prepare(
                        'UPDATE classrooms SET room_code = :room_code, capacity = :capacity WHERE id = :id'
                    );
                    $updateClassroom->execute([
                        'room_code' => $roomCode,
                        'capacity' => $capacity,
                        'id' => $classroomId,
                    ]);
                    $_SESSION['admin_notice'] = 'Podaci učionice su spremljeni.';
                    redirect('admin.php');
                }
            }
        }
    } elseif ($action === 'classroom_delete') {
        $classroomId = filter_var($_POST['classroom_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$classroomId || $classroomId < 1) {
            $error = 'Učionica nije pronađena.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $deleteReservations = $pdo->prepare(
                    'DELETE FROM reservations WHERE classroom_id = :classroom_id'
                );
                $deleteReservations->execute(['classroom_id' => $classroomId]);

                $deleteClassroom = $pdo->prepare('DELETE FROM classrooms WHERE id = :id');
                $deleteClassroom->execute(['id' => $classroomId]);
                if ($deleteClassroom->rowCount() !== 1) {
                    $pdo->rollBack();
                    $error = 'Učionica nije pronađena.';
                } else {
                    $pdo->commit();
                    $_SESSION['admin_notice'] = 'Učionica i povezane rezervacije su obrisane.';
                    redirect('admin.php');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Učionica nije obrisana. Pokušajte ponovno.';
            }
        }
    } elseif ($action === 'category_create' || $action === 'category_update') {
        $categoryName = trim((string) ($_POST['category_name'] ?? ''));
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);

        if (!preg_match('/\A.{1,100}\z/us', $categoryName)
            || preg_match('/[\x00-\x1F\x7F]/', $categoryName)) {
            $error = 'Unesite valjan naziv kategorije (najviše 100 znakova).';
        } elseif ($action === 'category_update' && (!$categoryId || $categoryId < 1)) {
            $error = 'Kategorija nije pronađena.';
        } else {
            $duplicateSql = 'SELECT id FROM categories WHERE name = :name';
            $duplicateParameters = ['name' => $categoryName];
            if ($action === 'category_update') {
                $duplicateSql .= ' AND id <> :id';
                $duplicateParameters['id'] = $categoryId;
            }
            $duplicateSql .= ' LIMIT 1';
            $duplicate = db()->prepare($duplicateSql);
            $duplicate->execute($duplicateParameters);

            if ($duplicate->fetchColumn()) {
                $error = 'Kategorija s tim nazivom već postoji.';
            } elseif ($action === 'category_create') {
                $insertCategory = db()->prepare('INSERT INTO categories (name) VALUES (:name)');
                $insertCategory->execute(['name' => $categoryName]);
                $_SESSION['admin_notice'] = 'Kategorija je dodana.';
                redirect('admin.php');
            } else {
                $updateCategory = db()->prepare('UPDATE categories SET name = :name WHERE id = :id');
                $updateCategory->execute(['name' => $categoryName, 'id' => $categoryId]);
                $_SESSION['admin_notice'] = 'Naziv kategorije je spremljen.';
                redirect('admin.php');
            }
        }
    } elseif ($action === 'category_delete') {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$categoryId || $categoryId < 1) {
            $error = 'Kategorija nije pronađena.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $findCategory = $pdo->prepare('SELECT id FROM categories WHERE id = :id FOR UPDATE');
                $findCategory->execute(['id' => $categoryId]);

                if (!$findCategory->fetchColumn()) {
                    $pdo->rollBack();
                    $error = 'Kategorija nije pronađena.';
                } else {
                    $countNotifications = $pdo->prepare(
                        'SELECT COUNT(*) FROM notifications WHERE category_id = :category_id'
                    );
                    $countNotifications->execute(['category_id' => $categoryId]);

                    if ($countNotifications->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $error = 'Kategoriju nije moguće obrisati dok je koriste obavijesti.';
                    } else {
                        $deleteCategory = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                        $deleteCategory->execute(['id' => $categoryId]);
                        $pdo->commit();
                        $_SESSION['admin_notice'] = 'Kategorija je obrisana.';
                        redirect('admin.php');
                    }
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Kategorija nije obrisana. Pokušajte ponovno.';
            }
        }
    } elseif ($action === 'user_update') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $active = (string) ($_POST['active'] ?? '');

        if (!in_array($role, $roles, true) || !in_array($active, ['0', '1'], true)) {
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
    } else {
        $error = 'Nepoznata administratorska radnja.';
    }
}

$message = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);
$users = db()->query('SELECT Username, Email, Role, Active FROM users ORDER BY Username, Email')->fetchAll();
$classrooms = db()->query('SELECT id, room_code, capacity FROM classrooms ORDER BY room_code')->fetchAll();
$categories = db()->query(
    'SELECT c.id, c.name, COUNT(n.id) AS notification_count '
    . 'FROM categories c LEFT JOIN notifications n ON n.category_id = c.id '
    . 'GROUP BY c.id, c.name ORDER BY c.name'
)->fetchAll();
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
                                    <input type="hidden" name="action" value="user_update">
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

    <br>
    <section class="classroom-admin-section" aria-labelledby="classroom-heading">
        <div class="admin-heading classroom-heading">
            <div>
                <p class="eyebrow">PROSTORIJE</p>
                <h2 id="classroom-heading">Upravljanje učionicama</h2>
                <p class="lead">Dodajte učionicu ili uredite postojeću oznaku i kapacitet.</p>
            </div>
            <span class="user-count"><?= count($classrooms) ?> učionica</span>
        </div>

        <form class="classroom-create-form" method="post" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="classroom_create">
            <div>
                <label for="new-room-code">Oznaka učionice</label>
                <input id="new-room-code" name="room_code" type="text" maxlength="30" required>
            </div>
            <div>
                <label for="new-room-capacity">Kapacitet</label>
                <input id="new-room-capacity" name="capacity" type="number" min="1" step="1" required>
            </div>
            <br>
            <button class="button button-primary" type="submit">Dodaj učionicu</button>            
        </form>

        <br>

        <div class="user-table-wrap">
            <table class="user-table classroom-table">
                <thead>
                    <tr>
                        <th scope="col">Učionica</th>
                        <th scope="col">Uredi podatke</th>
                        <th scope="col">Akcija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $classroom): ?>
                        <tr>
                            <td data-label="Učionica">
                                <strong><?= e($classroom['room_code']) ?></strong>
                            </td>
                            <td data-label="Uredi podatke">
                                <form class="classroom-edit-form category-edit-form" method="post" action="admin.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="classroom_update">
                                    <input type="hidden" name="classroom_id" value="<?= (int) $classroom['id'] ?>">
                                    <label class="visually-hidden" for="room-code-<?= (int) $classroom['id'] ?>">Oznaka učionice</label>
                                    <input id="room-code-<?= (int) $classroom['id'] ?>" name="room_code" type="text" maxlength="30" value="<?= e($classroom['room_code']) ?>" required>
                                    <label class="visually-hidden" for="room-capacity-<?= (int) $classroom['id'] ?>">Kapacitet učionice</label>
                                    <input id="room-capacity-<?= (int) $classroom['id'] ?>" name="capacity" type="number" min="1" step="1" value="<?= (int) $classroom['capacity'] ?>" required>
                                    <button class="button button-primary" type="submit">Spremi</button>
                                </form>
                            </td>
                            <td data-label="Akcija">
                                <form method="post" action="admin.php" onsubmit="return confirm('Brisanjem učionice brišu se i sve njezine rezervacije. Želite li nastaviti?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="classroom_delete">
                                    <input type="hidden" name="classroom_id" value="<?= (int) $classroom['id'] ?>">
                                    <button class="button button-danger" type="submit">Obriši</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($classrooms === []): ?>
                        <tr><td class="empty-users" colspan="3">Nema dodanih učionica.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <br>
    <section class="classroom-admin-section" aria-labelledby="category-heading">
        <div class="admin-heading classroom-heading">
            <div>
                <p class="eyebrow">OBAVIJESTI</p>
                <h2 id="category-heading">Upravljanje kategorijama</h2>
                <p class="lead">Dodajte ili preimenujte kategorije</p>
            </div>
            <span class="user-count"><?= count($categories) ?> kategorija</span>
        </div>

        <form class="classroom-create-form category-create-form" method="post" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="category_create">
            <div>
                <label for="new-category-name">Naziv kategorije</label>
                <input id="new-category-name" name="category_name" type="text" maxlength="100" required>
            </div>
            <br>
            <button class="button button-primary" type="submit">Dodaj kategoriju</button>
        </form>
        <br>

        <div class="user-table-wrap">
            <table class="user-table classroom-table">
                <thead>
                    <tr>
                        <th scope="col">Kategorija</th>
                        <th scope="col">Uredi naziv</th>
                        <th scope="col">Obavijesti</th>
                        <th scope="col">Akcija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td data-label="Kategorija"><?= e($category['name']) ?></td>
                            <td data-label="Uredi naziv">
                                <form class="classroom-edit-form" method="post" action="admin.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="category_update">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                    <label class="visually-hidden" for="category-name-<?= (int) $category['id'] ?>">Naziv kategorije</label>
                                    <input id="category-name-<?= (int) $category['id'] ?>" name="category_name" type="text" maxlength="100" value="<?= e($category['name']) ?>" required>
                                    <button class="button button-primary" type="submit">Spremi</button>
                                </form>
                            </td>
                            <td data-label="Obavijesti"><?= (int) $category['notification_count'] ?></td>
                            <td data-label="Akcija">
                                <form method="post" action="admin.php" onsubmit="return confirm('Obrisati ovu kategoriju?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="category_delete">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                    <button class="button button-danger" type="submit" <?= (int) $category['notification_count'] > 0 ? 'disabled title="Kategorija se koristi u obavijestima"' : '' ?>>Obriši</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($categories === []): ?>
                        <tr><td class="empty-users" colspan="4">Nema dodanih kategorija.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<?php require __DIR__ . '/footer.php'; ?>