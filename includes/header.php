<?php
/**
 * Authenticated student header (top bar + dashboard header).
 * Expects $currentUser and $studentProfile to already be set
 * (via includes/student_guard.php) and an optional $pageTitle.
 */
$unreadCount = unread_notification_count(db(), (int)$currentUser['id']);
$avatarPath  = $studentProfile['profile_photo'] ?? null;
$searchQ     = $_GET['q'] ?? '';
?>
<!-- TOP BAR -->
<div class="top-bar">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-6">
                <div class="portal-title">UIU Research Collaboration Portal</div>
            </div>
            <div class="col-6 text-end">
                <div class="portal-tagline">Connecting Minds. Creating Research.</div>
            </div>
        </div>
    </div>
</div>

<!-- 1ST NAVBAR -->
<header class="dashboard-header">
    <div class="dashboard-header-left">
        <a href="<?= e(url('/student/dashboard.php')) ?>" class="dashboard-logo-link">
            <img src="<?= e(url('/IMAGES/logo.jpg')) ?>" alt="UIU ResearchCollab Logo" class="dashboard-logo">
        </a>
        <div class="dashboard-brand">
            <h1>UIU ResearchCollab</h1>
            <p>Connecting Minds. Creating Research.</p>
        </div>
    </div>

    <div class="dashboard-header-right">
        <!-- SEARCH (searches researchers by keyword) -->
        <form class="dashboard-search" action="<?= e(url('/student/research-connect.php')) ?>" method="get">
            <input type="text" name="q" value="<?= e($searchQ) ?>" placeholder="Search for researchers, papers, communities..." aria-label="Search">
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>

        <!-- NOTIFICATION -->
        <div class="header-icon notification-icon">
            <a href="<?= e(url('/student/notifications.php')) ?>" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?= (int)$unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="header-divider"></div>

        <!-- USER PROFILE -->
        <div class="dropdown dashboard-user-dropdown">
            <button class="dashboard-user btn p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                <?php if ($avatarPath): ?>
                    <img src="<?= e(url('/uploads/avatars/' . $avatarPath)) ?>" alt="<?= e($currentUser['name']) ?>" class="user-avatar">
                <?php else: ?>
                    <span class="user-avatar avatar-initials"><?= e(initials($currentUser['name'])) ?></span>
                <?php endif; ?>
                <div class="user-information">
                    <h3 class="user-first-name"><?= e(explode(' ', trim($currentUser['name']))[0] ?? $currentUser['name']) ?></h3>
                    <p><?= e($studentProfile['department'] ?: 'Student') ?></p>
                </div>
                <span class="user-dropdown-button"><i class="bi bi-chevron-down"></i></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                <li><a class="dropdown-item" href="<?= e(url('/student/profile.php')) ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/student/settings.php')) ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="<?= e(url('/logout.php')) ?>" method="post" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- BODY -->
<div class="dashboard-wrapper">
