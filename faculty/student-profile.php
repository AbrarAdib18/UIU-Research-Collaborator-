<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$studentUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT sp.*, u.name, u.email FROM student_profiles sp JOIN users u ON u.id = sp.user_id WHERE u.id = ? AND u.role = 'student' LIMIT 1");
$stmt->execute([$studentUserId]);
$student = $stmt->fetch();

if (!$student || !can_view_student_profile($pdo, $userId, $student)) {
    flash('error', 'That student profile is not available.');
    redirect('/faculty/research-connect.php');
}

$profileId = (int)$student['id'];
$domainStmt = $pdo->prepare('SELECT rd.name FROM profile_research_domains prd JOIN research_domains rd ON rd.id = prd.domain_id WHERE prd.profile_id = ? ORDER BY rd.name');
$domainStmt->execute([$profileId]);
$domainNames = $domainStmt->fetchAll(PDO::FETCH_COLUMN);

$skillStmt = $pdo->prepare('SELECT sk.name FROM profile_skills ps JOIN skills sk ON sk.id = ps.skill_id WHERE ps.profile_id = ? ORDER BY sk.name');
$skillStmt->execute([$profileId]);
$skillNames = $skillStmt->fetchAll(PDO::FETCH_COLUMN);

$projStmt = $pdo->prepare('SELECT * FROM projects WHERE profile_id = ? ORDER BY created_at DESC LIMIT 5');
$projStmt->execute([$profileId]);
$projects = $projStmt->fetchAll();

$visibility = get_profile_visibility($pdo, $profileId);

$existingConnection = has_existing_connection($pdo, $userId, $studentUserId);
$canMessage = can_message($pdo, $userId, $studentUserId);

$pageTitle = 'Student Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($student['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
        .avatar-lg{width:72px;height:72px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:24px;font-weight:700;flex-shrink:0;object-fit:cover}
        .rc-tags{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}
        .rc-tags span{background:var(--uiu-light-blue);color:var(--uiu-dark-blue);font-size:12px;font-weight:600;padding:4px 10px;border-radius:14px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/research-connect.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Research Connect</a>

        <div class="detail-card d-flex align-items-center gap-3 flex-wrap">
            <?php if (!empty($student['profile_photo'])): ?>
                <img class="avatar-lg" src="<?= e(url('/uploads/avatars/' . $student['profile_photo'])) ?>" alt="<?= e($student['name']) ?>">
            <?php else: ?>
                <span class="avatar-lg"><?= e(initials($student['name'])) ?></span>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h1 style="font-size:22px;color:var(--uiu-blue);font-weight:700;margin:0;"><?= e($student['name']) ?></h1>
                <p class="text-muted mb-0"><?= e($student['department'] ?: 'Department not set') ?> &middot; <?= e($student['program'] ?: 'Program not set') ?> &middot; <?= e($student['semester'] ?: 'Semester not set') ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canMessage): ?>
                    <a href="<?= e(url('/faculty/conversation.php?user=' . $studentUserId)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-dots"></i> Message</a>
                <?php elseif ($existingConnection): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Connection Pending</button>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/faculty/faculty-connections.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="recipient_id" value="<?= $studentUserId ?>">
                        <button type="submit" class="btn btn-uiu btn-sm" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;"><i class="bi bi-link-45deg"></i> Connect</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($visibility['research_visibility'] ?? 1) || (int)$student['user_id'] === $userId): ?>
        <div class="detail-card">
            <h2>Research Profile</h2>
            <?php if ($student['research_statement']): ?><p><?= nl2br(e($student['research_statement'])) ?></p><?php endif; ?>
            <div class="rc-tags"><?php foreach ($domainNames as $n): ?><span><?= e($n) ?></span><?php endforeach; ?></div>
            <?php if (!$domainNames): ?><p class="text-muted small mb-0">No research domains listed.</p><?php endif; ?>
        </div>

        <div class="detail-card">
            <h2>Skills</h2>
            <div class="rc-tags"><?php foreach ($skillNames as $n): ?><span><?= e($n) ?></span><?php endforeach; ?></div>
            <?php if (!$skillNames): ?><p class="text-muted small mb-0">No skills listed.</p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (($visibility['project_visibility'] ?? 1) && $projects): ?>
        <div class="detail-card">
            <h2>Projects</h2>
            <?php foreach ($projects as $p): ?>
                <div class="mb-2 pb-2 border-bottom">
                    <strong><?= e($p['title']) ?></strong> <span class="text-muted small"><?= e($p['project_type'] ?: '') ?></span>
                    <?php if ($p['description']): ?><p class="mb-0 text-muted small"><?= e(mb_strimwidth($p['description'], 0, 150, '...')) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (($visibility['contact_visibility'] ?? 1)): ?>
        <div class="detail-card">
            <h2>Contact</h2>
            <p class="mb-1"><i class="bi bi-envelope-fill"></i> <?= e($student['email']) ?></p>
            <?php if ($student['linkedin_url']): ?><p class="mb-1"><a href="<?= e($student['linkedin_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i> LinkedIn</a></p><?php endif; ?>
            <?php if ($student['github_url']): ?><p class="mb-0"><a href="<?= e($student['github_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-github"></i> GitHub</a></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
