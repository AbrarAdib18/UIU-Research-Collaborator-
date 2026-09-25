<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$oppId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM research_opportunities WHERE id = ? AND created_by = ?');
$stmt->execute([$oppId, $userId]);
$opportunity = $stmt->fetch();

if (!$opportunity) {
    flash('error', 'That opportunity does not exist or is not yours to manage.');
    redirect('/faculty/opportunities.php');
}

$domainStmt = $pdo->prepare('SELECT d.name FROM opportunity_domains od JOIN research_domains d ON d.id = od.domain_id WHERE od.opportunity_id = ? ORDER BY d.name');
$domainStmt->execute([$oppId]);
$domainNames = $domainStmt->fetchAll(PDO::FETCH_COLUMN);

$appStmt = $pdo->prepare(
    "SELECT oa.*, u.name AS applicant_name, sp.department, sp.profile_photo
     FROM opportunity_applications oa
     JOIN users u ON u.id = oa.user_id
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE oa.opportunity_id = ?
     ORDER BY FIELD(oa.status,'Pending','Shortlisted','Accepted','Rejected','Withdrawn'), oa.applied_at DESC"
);
$appStmt->execute([$oppId]);
$applications = $appStmt->fetchAll();

function fac_app_status_badge(string $status): string
{
    return match ($status) {
        'Accepted'    => 'bg-success-subtle text-success-emphasis',
        'Shortlisted' => 'bg-info-subtle text-info-emphasis',
        'Rejected'    => 'bg-danger-subtle text-danger-emphasis',
        'Withdrawn'   => 'bg-secondary-subtle text-secondary-emphasis',
        default       => 'bg-warning-subtle text-warning-emphasis',
    };
}

$pageTitle = 'Opportunity Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($opportunity['title']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
        .detail-card p{color:var(--text-medium);white-space:pre-line}
        .detail-title{color:var(--uiu-blue);font-size:24px;font-weight:700;margin-bottom:6px}
        .detail-meta-row{display:flex;flex-wrap:wrap;gap:16px;color:var(--text-light);font-size:13px;margin:10px 0}
        .detail-domain-tags{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0}
        .detail-domain-tags span{background-color:var(--uiu-light-blue);color:var(--uiu-dark-blue);font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px}
        .avatar-sm{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:13px;font-weight:700;flex-shrink:0;object-fit:cover}
        .applicant-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light,#eee)}
        .applicant-row:last-child{border-bottom:none}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/opportunities.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Opportunities</a>

        <div class="detail-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <span class="opportunity-badge fydp"><?= e($opportunity['project_type'] ?: 'Research') ?></span>
                    <h1 class="detail-title mt-2"><?= e($opportunity['title']) ?></h1>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($opportunity['status']) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($opportunity['visibility']) ?></span>
                </div>
                <a href="<?= e(url('/faculty/opportunity-edit.php?id=' . $oppId)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            </div>
            <div class="detail-meta-row">
                <span><i class="bi bi-people-fill"></i> Team size: <?= (int)$opportunity['team_size_min'] ?> - <?= (int)$opportunity['team_size_max'] ?></span>
                <span><i class="bi bi-calendar3"></i> Deadline: <?= format_date($opportunity['deadline']) ?></span>
                <span><i class="bi bi-inbox"></i> <?= count($applications) ?> applicant(s)</span>
            </div>
            <?php if ($domainNames): ?>
                <div class="detail-domain-tags"><?php foreach ($domainNames as $n): ?><span><?= e($n) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <div class="detail-card"><h2>Description</h2><p><?= e($opportunity['description'] ?: 'No description provided.') ?></p></div>
        <?php if ($opportunity['problem_statement']): ?><div class="detail-card"><h2>Problem Statement</h2><p><?= e($opportunity['problem_statement']) ?></p></div><?php endif; ?>
        <?php if ($opportunity['requirements']): ?><div class="detail-card"><h2>Requirements</h2><p><?= e($opportunity['requirements']) ?></p></div><?php endif; ?>

        <div class="detail-card">
            <h2>Applicants</h2>
            <?php if (!$applications): ?>
                <p class="text-muted mb-0">No one has applied yet.</p>
            <?php else: foreach ($applications as $a): ?>
                <div class="applicant-row">
                    <?php if (!empty($a['profile_photo'])): ?>
                        <img class="avatar-sm" src="<?= e(url('/uploads/avatars/' . $a['profile_photo'])) ?>" alt="<?= e($a['applicant_name']) ?>">
                    <?php else: ?>
                        <span class="avatar-sm"><?= e(initials($a['applicant_name'])) ?></span>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($a['applicant_name']) ?></div>
                        <div class="text-muted small"><?= e($a['department'] ?: 'Student') ?> &middot; Applied <?= e(time_ago($a['applied_at'])) ?></div>
                    </div>
                    <span class="badge rounded-pill <?= fac_app_status_badge($a['status']) ?>"><?= e($a['status']) ?></span>
                    <a href="<?= e(url('/faculty/application-details.php?id=' . $a['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
