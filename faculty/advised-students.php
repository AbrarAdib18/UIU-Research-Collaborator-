<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$statusFilter = (string)($_GET['status'] ?? 'active');
if (!in_array($statusFilter, ['active', 'completed', 'cancelled', ''], true)) {
    $statusFilter = 'active';
}

$where  = ['aa.faculty_user_id = ?', 'aa.student_user_id IS NOT NULL'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'aa.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare(
    "SELECT aa.*, u.name AS student_name, sp.department, sp.profile_photo, sp.id AS student_profile_id,
            (SELECT MAX(dm.created_at) FROM direct_messages dm JOIN direct_conversations dc ON dc.id = dm.conversation_id
                WHERE (dc.user_one_id = aa.faculty_user_id AND dc.user_two_id = aa.student_user_id)
                   OR (dc.user_two_id = aa.faculty_user_id AND dc.user_one_id = aa.student_user_id)) AS last_message_at
     FROM advisor_assignments aa
     JOIN users u ON u.id = aa.student_user_id
     LEFT JOIN student_profiles sp ON sp.user_id = aa.student_user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY aa.assigned_at DESC"
);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$pageTitle = 'My Advised Students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Advised Students || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .avatar-sm{width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:14px;font-weight:700;flex-shrink:0}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">My Advised Students</h2>

        <form method="get" class="filter-bar">
            <select name="status" class="form-select" style="max-width:180px;">
                <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', '' => 'All'] as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$assignments): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-mortarboard"></i><p>No advised students yet. Accept a mentorship request to get started.</p></div>
        <?php endif; ?>

        <?php foreach ($assignments as $a): ?>
            <div class="app-panel d-flex align-items-center gap-3 flex-wrap">
                <?php if (!empty($a['profile_photo'])): ?>
                    <img class="avatar-sm" src="<?= e(url('/uploads/avatars/' . $a['profile_photo'])) ?>" alt="<?= e($a['student_name']) ?>">
                <?php else: ?>
                    <span class="avatar-sm"><?= e(initials($a['student_name'])) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($a['student_name']) ?></div>
                    <div class="text-muted small"><?= e($a['department'] ?: 'Student') ?> &middot; <?= e(ucwords(str_replace('_', ' ', $a['assignment_type']))) ?> &middot; Since <?= format_date($a['assigned_at']) ?></div>
                    <?php if ($a['last_message_at']): ?><div class="text-muted small">Last message <?= e(time_ago($a['last_message_at'])) ?></div><?php endif; ?>
                </div>
                <span class="badge bg-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-emphasis"><?= e(ucfirst($a['status'])) ?></span>
                <a href="<?= e(url('/faculty/student-profile.php?id=' . $a['student_user_id'])) ?>" class="btn btn-sm btn-outline-secondary">Profile</a>
                <a href="<?= e(url('/faculty/conversation.php?user=' . $a['student_user_id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Message</a>
                <?php if ($a['status'] === 'active'): ?>
                <form method="post" action="<?= e(url('/faculty/advised-projects.php')) ?>" onsubmit="return confirm('Mark this advisor assignment as completed?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete_assignment">
                    <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-outline-dark">Complete</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
