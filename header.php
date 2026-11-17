<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">CM</span> Classroom Manager</a>
    <nav aria-label="Glavna navigacija">
        <a href="index.php">Početna</a>
        <?php if ($user): ?>
            <a href="profile.php">Moj profil</a>
            <?php if ($user['Role'] === 'Admin'): ?>
                <a href="admin.php">Admin panel</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php">Prijava</a>
            <a class="nav-register" href="register.php">Registracija</a>
        <?php endif; ?>
    </nav>
</header>
<main class="page-shell">