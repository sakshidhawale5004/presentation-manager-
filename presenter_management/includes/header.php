<?php
// Top part of every private page: sidebar + top bar.
// Set $pageTitle and $activePage BEFORE including this file.
$user       = current_user();
$pageTitle  = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? '';

// [key, label, icon, link, ready?, allowed roles (null = everyone)]
$menu = [
    ['dashboard', 'Dashboard',     'bi-speedometer2', 'dashboard.php',  true,  null],
    ['members',   'Members',       'bi-people',       'members.php',    true,  null],
    ['registrations', 'Registrations', 'bi-person-plus', 'registrations.php', true, null],
    ['presentations', 'Presentations', 'bi-easel', 'presentations.php', true, null],
    ['calendar',  'Calendar',      'bi-calendar3',    'calendar.php',   true,  null],
    ['reports',   'Reports',       'bi-bar-chart',    'reports.php',    true,  null],
    ['emails',    'Email Center',  'bi-envelope',     'emails.php',     true,  null],
    ['users',     'Admin Users',   'bi-person-gear',  'users.php',      true,  ['super_admin']],
    ['masters',   'Chapters & Categories', 'bi-diagram-3', 'masters.php',   true,  ['super_admin']],
    ['audit',     'Activity Log',  'bi-clock-history', 'audit_log.php',    true,  ['super_admin']],
    ['settings',  'Settings',      'bi-gear',         'settings.php',   true,  ['super_admin']],
];
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

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-easel2-fill"></i>
        <span><?= e(APP_NAME) ?></span>
    </div>

    <ul class="nav flex-column px-3 gap-1">
        <?php foreach ($menu as [$key, $label, $icon, $href, $ready, $roles]): ?>
            <?php
            if ($roles !== null && !in_array($user['role'], $roles, true)) {
                continue;
            }
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $activePage === $key ? 'active' : '' ?> <?= $ready ? '' : 'disabled' ?>"
                   href="<?= $ready ? e(url($href)) : '#' ?>"
                   <?= $ready ? '' : 'tabindex="-1" aria-disabled="true"' ?>>
                    <i class="bi <?= e($icon) ?>"></i>
                    <span><?= e($label) ?></span>
                    <?php if (!$ready): ?>
                        <span class="badge text-bg-secondary ms-auto">Soon</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============ MAIN AREA ============ -->
<div class="main-area">

    <header class="topbar d-flex align-items-center justify-content-between px-3 px-md-4 py-2">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" id="sidebarToggle" type="button" aria-label="Open menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="h5 mb-0 fw-semibold"><?= e($pageTitle) ?></h1>
        </div>

        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar"><?= e(initials($user['full_name'])) ?></span>
                <span class="d-none d-sm-block text-start lh-sm">
                    <span class="d-block fw-semibold small"><?= e($user['full_name']) ?></span>
                    <span class="d-block text-muted" style="font-size:.75rem"><?= e(role_label($user['role'])) ?></span>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="p-3 p-md-4">
        <?php render_flashes(); ?>
