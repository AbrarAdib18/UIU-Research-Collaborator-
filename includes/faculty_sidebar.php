<?php
/**
 * Left sidebar navigation + profile completion card for the Faculty Portal.
 * Expects $facultyProfile and $currentUser to be set.
 */
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$completion  = calculate_faculty_profile_completion(db(), (int)$currentUser['id']);

$sidebarMsgStmt = db()->prepare(
    "SELECT COUNT(*) FROM direct_messages dm JOIN direct_conversations dc ON dc.id = dm.conversation_id
     WHERE (dc.user_one_id = ? OR dc.user_two_id = ?) AND dm.sender_id != ? AND dm.is_read = 0"
);
$sidebarMsgStmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id']]);
$sidebarUnreadMessages = (int)$sidebarMsgStmt->fetchColumn();

$navLinks = [
    ['dashboard.php', 'bi-house-fill', 'Dashboard'],
    ['profile.php', 'bi-person-fill', 'My Faculty Profile'],
    ['research-connect.php', 'bi-people', 'Research Connect'],
    ['opportunities.php', 'bi-file-earmark-text', 'Research Opportunities'],
    ['applications.php', 'bi-inbox-fill', 'Applications'],
    ['mentorship-requests.php', 'bi-person-check-fill', 'Mentor / Advisor Requests'],
    ['advised-students.php', 'bi-mortarboard-fill', 'My Advised Students'],
    ['advised-teams.php', 'bi-people-fill', 'My Advised Teams'],
    ['advised-projects.php', 'bi-kanban-fill', 'Guided Projects & Papers'],
    ['faculty-connections.php', 'bi-link-45deg', 'Connections'],
    ['messages.php', 'bi-chat-dots-fill', 'Messages'],
    ['communities.php', 'bi-diagram-3', 'Communities'],
    ['repository.php', 'bi-database-fill', 'Research Repository'],
    ['notifications.php', 'bi-bell', 'Notifications'],
    ['settings.php', 'bi-gear', 'Settings'],
];
?>
<!-- LEFT SIDEBAR -->
<aside class="dashboard-sidebar">
    <nav class="sidebar-navigation">
        <?php foreach ($navLinks as [$file, $icon, $label]): ?>
            <a href="<?= e(url('/faculty/' . $file)) ?>" class="sidebar-link<?= $currentFile === $file ? ' active' : '' ?>">
                <i class="bi <?= e($icon) ?>"></i>
                <span><?= e($label) ?></span>
                <?php if ($file === 'notifications.php' && $unreadCount > 0): ?>
                    <span class="sidebar-count"><?= (int)$unreadCount ?></span>
                <?php elseif ($file === 'messages.php' && $sidebarUnreadMessages > 0): ?>
                    <span class="sidebar-count"><?= (int)$sidebarUnreadMessages ?></span>
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
        <p>A complete profile helps students find and trust you as an advisor.</p>
        <a href="<?= e(url('/faculty/profile-edit.php')) ?>" class="sidebar-update-button">Update Profile</a>
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
