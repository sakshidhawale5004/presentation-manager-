<?php
// Top part of login / setup pages (no sidebar).
$pageTitle = $pageTitle ?? 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card card shadow-lg border-0 <?= !empty($authWide) ? 'auth-wide' : '' ?>">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="auth-logo"><i class="bi bi-easel2-fill"></i></div>
                <h1 class="h4 fw-bold mt-3 mb-1"><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0"><?= e($pageTitle) ?></p>
            </div>
            <?php render_flashes(); ?>
