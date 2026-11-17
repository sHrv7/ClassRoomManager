<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$user = current_user();
$pageTitle = 'Početna';
require __DIR__ . '/header.php';
?>
<section class="welcome-layout">
    <div class="welcome-copy">
        <p class="eyebrow">FAKULTETSKE UČIONICE, JEDNOSTAVNIJE</p>
        <h1>Dobro došli u<br><span>Classroom Manager.</span></h1>
        <p class="lead">Jedno mjesto za nastavu, učionice i obavijesti.</p>
        <?php if ($user): ?>
            <a class="button button-primary" href="profile.php">Otvori moj profil</a>
        <?php else: ?>
            <div class="button-row">
                <a class="button button-primary" href="login.php">Prijava</a>
                <a class="button button-secondary" href="register.php">Izradi račun</a>
            </div>
        <?php endif; ?>
    </div>
    <aside class="welcome-aside" aria-label="Informacije">
        <span class="aside-index">01 / POČETAK</span>
        <h2>Vaš račun</h2>
        <p><?= $user
            ? 'Prijavljeni ste kao ' . e($user['Username']) . '. Ovdje možete upravljati svojim profilom.'
            : 'Registracijom se izrađuje osnovni studentski račun. Ulogu nije moguće odabrati pri registraciji.' ?></p>
        <?php if ($user): ?>
            <a class="text-link" href="profile.php">Postavke računa <span aria-hidden="true">&rarr;</span></a>
        <?php else: ?>
            <a class="text-link" href="register.php">Registriraj se <span aria-hidden="true">&rarr;</span></a>
        <?php endif; ?>
    </aside>
</section>
<?php require __DIR__ . '/footer.php'; ?>