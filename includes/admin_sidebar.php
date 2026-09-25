<?php
/**
 * Left sidebar navigation for the Admin Portal.
 * Expects $currentUser to be set.
 */
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$pdo = db();

$pendingVerificationCount = (int)$pdo->query("SELECT COUNT(*) FROM faculty_verifications WHERE status = 'pending'")->fetchColumn();

$navLinks = [
    ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
    ['users.php', 'bi-people-fill', 'User Management'],
    ['students.php', 'bi-mortarboard-fill', 'Students'],
    ['faculty.php', 'bi-person-workspace', 'Faculty Management'],
    ['faculty-verification.php', 'bi-patch-check-fill', 'Faculty Verification'],
    ['domains.php', 'bi-diagram-3-fill', 'Research Domains'],
    ['skills.php', 'bi-stars', 'Skills & Languages'],
    ['opportunities.php', 'bi-briefcase-fill', 'Research Opportunities'],
    ['applications.php', 'bi-inbox-fill', 'Applications'],
    ['teams.php', 'bi-people', 'Research Teams'],
    ['communities.php', 'bi-diagram-3', 'Communities'],
    ['repository.php', 'bi-database-fill', 'Repository Resources'],
    ['advisor-requests.php', 'bi-person-check-fill', 'Advisor Requests'],
    ['advisor-assignments.php', 'bi-mortarboard', 'Advisor Assignments'],
    ['connections.php', 'bi-link-45deg', 'Connections'],
    ['messages-monitor.php', 'bi-chat-dots', 'Messages / Moderation'],
    ['notifications.php', 'bi-bell', 'Notifications'],
    ['activity-logs.php', 'bi-clock-history', 'Activity Logs'],
    ['reports.php', 'bi-bar-chart-fill', 'Reports'],
    ['settings.php', 'bi-gear', 'Platform Settings'],
];
?>
<!-- LEFT SIDEBAR -->
<aside class="dashboard-sidebar">
    <nav class="sidebar-navigation">
        <?php foreach ($navLinks as [$file, $icon, $label]): ?>
            <a href="<?= e(url('/admin/' . $file)) ?>" class="sidebar-link<?= $currentFile === $file ? ' active' : '' ?>">
                <i class="bi <?= e($icon) ?>"></i>
                <span><?= e($label) ?></span>
                <?php if ($file === 'faculty-verification.php' && $pendingVerificationCount > 0): ?>
                    <span class="sidebar-count"><?= $pendingVerificationCount ?></span>
                <?php elseif ($file === 'notifications.php' && $unreadCount > 0): ?>
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

    <!-- QUICK STATUS CARD -->
    <div class="sidebar-profile-card">
        <h3>Needs Attention</h3>
        <p><?= $pendingVerificationCount ?> faculty verification(s) pending review.</p>
        <a href="<?= e(url('/admin/faculty-verification.php')) ?>" class="sidebar-update-button">Review Now</a>
    </div>
</aside>
