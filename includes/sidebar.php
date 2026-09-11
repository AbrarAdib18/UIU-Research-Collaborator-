<?php
/**
 * Left sidebar navigation + profile completion card.
 * Expects $studentProfile to be set.
 */
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$completion  = (int)($studentProfile['profile_completion'] ?? 0);

$navLinks = [
    ['dashboard.php', 'bi-house-fill', 'Dashboard'],
    ['profile.php', 'bi-person-fill', 'My Profile'],
    ['research-connect.php', 'bi-people', 'Research Connect'],
    ['opportunities.php', 'bi-file-earmark-text', 'Research Opportunities'],
    ['teams.php', 'bi-people-fill', 'My Teams'],
    ['communities.php', 'bi-diagram-3', 'Communities'],
    ['repository.php', 'bi-database-fill', 'Research Repositories'],
    ['saved-items.php', 'bi-bookmark', 'Saved Items'],
    ['notifications.php', 'bi-bell', 'Notifications'],
    ['settings.php', 'bi-gear', 'Settings'],
];
?>
<!-- LEFT SIDEBAR -->
<aside class="dashboard-sidebar">
    <nav class="sidebar-navigation">
        <?php foreach ($navLinks as [$file, $icon, $label]): ?>
            <a href="<?= e(url('/student/' . $file)) ?>" class="sidebar-link<?= $currentFile === $file ? ' active' : '' ?>">
                <i class="bi <?= e($icon) ?>"></i>
                <span><?= e($label) ?></span>
                <?php if ($file === 'notifications.php' && $unreadCount > 0): ?>
                    <span class="sidebar-count"><?= (int)$unreadCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <form action="<?= e(url('/logout.php')) ?>" method="post" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-link border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>

    <!-- PROFILE CARD -->
    <div class="sidebar-profile-card">
        <h3>Complete Your Profile</h3>
        <p>Get better matches by completing your profile.</p>
        <a href="<?= e(url('/student/profile.php')) ?>" class="sidebar-update-button">Update Profile</a>
        <div class="sidebar-completion">
            <div class="completion-header">
                <span>Profile Completion</span>
                <strong><?= $completion ?>%</strong>
            </div>
            <div class="completion-progress">
                <div class="completion-progress-bar" style="width: <?= $completion ?>%;"></div>
            </div>
        </div>
    </div>
</aside>
