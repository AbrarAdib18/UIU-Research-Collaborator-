<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo         = db();
$userId      = (int)$currentUser['id'];
$facultyUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT fp.*, u.name, u.email FROM faculty_profiles fp JOIN users u ON u.id = fp.user_id WHERE u.id = ? AND u.role = 'faculty' LIMIT 1");
$stmt->execute([$facultyUserId]);
$faculty = $stmt->fetch();

if (!$faculty || !can_view_faculty_profile($pdo, $userId, $faculty)) {
    flash('error', 'That faculty profile is not available.');
    redirect('/student/research-connect.php?view=faculty');
}

$fpId = (int)$faculty['id'];
$domainStmt = $pdo->prepare('SELECT rd.name FROM faculty_research_domains frd JOIN research_domains rd ON rd.id = frd.domain_id WHERE frd.faculty_profile_id = ? ORDER BY rd.name');
$domainStmt->execute([$fpId]);
$domainNames = $domainStmt->fetchAll(PDO::FETCH_COLUMN);

$skillStmt = $pdo->prepare('SELECT sk.name FROM faculty_skills fs JOIN skills sk ON sk.id = fs.skill_id WHERE fs.faculty_profile_id = ? ORDER BY sk.name');
$skillStmt->execute([$fpId]);
$skillNames = $skillStmt->fetchAll(PDO::FETCH_COLUMN);

$pubStmt = $pdo->prepare('SELECT * FROM faculty_publications WHERE faculty_profile_id = ? ORDER BY publication_date DESC LIMIT 5');
$pubStmt->execute([$fpId]);
$publications = $pubStmt->fetchAll();

$projStmt = $pdo->prepare('SELECT * FROM faculty_projects WHERE faculty_profile_id = ? ORDER BY start_date DESC LIMIT 5');
$projStmt->execute([$fpId]);
$projects = $projStmt->fetchAll();

$visibility = get_faculty_visibility($pdo, $fpId);
$prefs      = get_faculty_preferences($pdo, $fpId);
$remaining  = faculty_capacity_remaining($pdo, $facultyUserId);

$existingConnection = has_existing_connection($pdo, $userId, $facultyUserId);
$canMessage = can_message($pdo, $userId, $facultyUserId);

$hasPendingAdvisorRequest = false;
$chk = $pdo->prepare("SELECT COUNT(*) FROM advisor_requests WHERE requested_by_user_id = ? AND faculty_user_id = ? AND status IN ('pending','clarification_requested')");
$chk->execute([$userId, $facultyUserId]);
$hasPendingAdvisorRequest = (bool)$chk->fetchColumn();

$acceptingAny = $prefs && ((int)$prefs['accepting_mentees'] || (int)$prefs['accepting_team_advisory'] || (int)$prefs['accepting_paper_advisory']);
$capacityOk = $remaining === null || $remaining > 0;

$pageTitle = 'Faculty Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($faculty['name']) ?> || UIU ResearchCollab</title>
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
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/student/research-connect.php?view=faculty')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Research Connect</a>

        <div class="detail-card d-flex align-items-center gap-3 flex-wrap">
            <?php if (!empty($faculty['profile_photo'])): ?>
                <img class="avatar-lg" src="<?= e(url('/uploads/avatars/' . $faculty['profile_photo'])) ?>" alt="<?= e($faculty['name']) ?>">
            <?php else: ?>
                <span class="avatar-lg"><?= e(initials($faculty['name'])) ?></span>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h1 style="font-size:22px;color:var(--uiu-blue);font-weight:700;margin:0;"><?= e($faculty['name']) ?></h1>
                <p class="text-muted mb-0"><?= e($faculty['designation'] ?: 'Faculty') ?> &middot; <?= e($faculty['department'] ?: 'Department not set') ?></p>
                <?php if ($prefs && $prefs['accepting_mentees']): ?><span class="badge bg-success-subtle text-success-emphasis mt-1">Accepting Mentees</span><?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canMessage): ?>
                    <a href="<?= e(url('/student/conversation.php?user=' . $facultyUserId)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-dots"></i> Message</a>
                <?php elseif ($existingConnection): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Connection Pending</button>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/student/faculty-connections.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="recipient_id" value="<?= $facultyUserId ?>">
                        <button type="submit" class="btn btn-sm" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;"><i class="bi bi-link-45deg"></i> Connect</button>
                    </form>
                <?php endif; ?>
                <?php if ($acceptingAny && $capacityOk && !$hasPendingAdvisorRequest): ?>
                    <a href="<?= e(url('/student/advisor-requests.php?faculty_id=' . $facultyUserId)) ?>" class="btn btn-sm btn-success"><i class="bi bi-person-check"></i> Request Advisor</a>
                <?php elseif ($hasPendingAdvisorRequest): ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>Request Pending</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($visibility['research_visibility'] ?? 1): ?>
        <div class="detail-card">
            <h2>Research Profile</h2>
            <?php if ($faculty['specialization']): ?><p class="mb-2"><strong>Specialization:</strong> <?= e($faculty['specialization']) ?></p><?php endif; ?>
            <?php if ($faculty['research_statement']): ?><p><?= nl2br(e($faculty['research_statement'])) ?></p><?php endif; ?>
            <div class="rc-tags"><?php foreach ($domainNames as $n): ?><span><?= e($n) ?></span><?php endforeach; ?></div>
            <?php if ($skillNames): ?><div class="rc-tags mt-1"><?php foreach ($skillNames as $n): ?><span><?= e($n) ?></span><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (($visibility['publication_visibility'] ?? 1) && $publications): ?>
        <div class="detail-card">
            <h2>Selected Publications</h2>
            <?php foreach ($publications as $p): ?>
                <div class="mb-2 pb-2 border-bottom"><strong><?= e($p['title']) ?></strong><br><span class="text-muted small"><?= e($p['venue'] ?: '') ?><?= $p['publication_date'] ? ' · ' . format_date($p['publication_date'], 'Y') : '' ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (($visibility['project_visibility'] ?? 1) && $projects): ?>
        <div class="detail-card">
            <h2>Research Projects</h2>
            <?php foreach ($projects as $p): ?>
                <div class="mb-2 pb-2 border-bottom"><strong><?= e($p['title']) ?></strong> <span class="text-muted small"><?= e($p['status']) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($visibility['contact_visibility'] ?? 1): ?>
        <div class="detail-card">
            <h2>Contact</h2>
            <p class="mb-1"><i class="bi bi-envelope-fill"></i> <?= e($faculty['email']) ?></p>
            <?php if ($faculty['linkedin_url']): ?><p class="mb-1"><a href="<?= e($faculty['linkedin_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i> LinkedIn</a></p><?php endif; ?>
            <?php if ($faculty['google_scholar_url']): ?><p class="mb-0"><a href="<?= e($faculty['google_scholar_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-mortarboard"></i> Google Scholar</a></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
