<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$opportunityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($opportunityId <= 0) {
    flash('error', 'Opportunity not found.');
    redirect('/student/opportunities.php');
}

$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS creator_name
     FROM research_opportunities o
     LEFT JOIN users u ON u.id = o.created_by
     WHERE o.id = ? AND o.visibility = 'Public' AND o.status <> 'Draft'
     LIMIT 1"
);
$stmt->execute([$opportunityId]);
$opportunity = $stmt->fetch();

if (!$opportunity) {
    flash('error', 'That opportunity does not exist or is no longer available.');
    redirect('/student/opportunities.php');
}

$domainStmt = $pdo->prepare(
    "SELECT d.name FROM opportunity_domains od
     JOIN research_domains d ON d.id = od.domain_id
     WHERE od.opportunity_id = ?
     ORDER BY d.name"
);
$domainStmt->execute([$opportunityId]);
$domainNames = $domainStmt->fetchAll(PDO::FETCH_COLUMN);

$appStmt = $pdo->prepare('SELECT * FROM opportunity_applications WHERE opportunity_id = ? AND user_id = ? LIMIT 1');
$appStmt->execute([$opportunityId, $userId]);
$application = $appStmt->fetch();

$saveStmt = $pdo->prepare('SELECT 1 FROM saved_opportunities WHERE user_id = ? AND opportunity_id = ? LIMIT 1');
$saveStmt->execute([$userId, $opportunityId]);
$isSaved = (bool)$saveStmt->fetchColumn();

$deadlinePassed = $opportunity['deadline'] !== null && $opportunity['deadline'] < date('Y-m-d');
$activeApplication = $application && in_array($application['status'], ['Pending', 'Accepted'], true);
$canApply = !$activeApplication && $opportunity['status'] === 'Open' && !$deadlinePassed;

function application_status_badge(string $status): string
{
    switch ($status) {
        case 'Accepted':  return 'bg-success-subtle text-success-emphasis';
        case 'Rejected':  return 'bg-danger-subtle text-danger-emphasis';
        case 'Withdrawn': return 'bg-secondary-subtle text-secondary-emphasis';
        default:          return 'bg-warning-subtle text-warning-emphasis'; // Pending
    }
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
        .detail-card {
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .detail-card h2 {
            color: var(--uiu-blue);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .detail-card p { color: var(--text-medium); white-space: pre-line; }
        .detail-header-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .detail-title { color: var(--uiu-blue); font-size: 24px; font-weight: 700; margin-bottom: 6px; }
        .detail-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            color: var(--text-light);
            font-size: 13px;
            margin: 10px 0;
        }
        .detail-meta-row span i { margin-right: 5px; color: var(--uiu-blue); }
        .detail-domain-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
        .detail-domain-tags span {
            background-color: var(--uiu-light-blue);
            color: var(--uiu-dark-blue);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .opp-save-btn-lg {
            border: 1px solid var(--border-color);
            background: var(--card-background);
            color: var(--uiu-blue);
            font-size: 16px;
            border-radius: 8px;
            padding: 8px 14px;
            white-space: nowrap;
        }
        .opp-save-btn-lg:hover { border-color: var(--uiu-blue); }
        .application-status-box {
            border-radius: 8px;
            padding: 14px;
            background-color: var(--uiu-light-blue);
        }
        .back-link { color: var(--uiu-blue); font-size: 14px; display: inline-block; margin-bottom: 14px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <a href="<?= e(url('/student/opportunities.php')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to Opportunities</a>

        <div class="detail-card">
            <div class="detail-header-row">
                <div>
                    <span class="opportunity-badge fydp"><?= e($opportunity['project_type'] ?: 'Research') ?></span>
                    <h1 class="detail-title mt-2"><?= e($opportunity['title']) ?></h1>
                    <div>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($opportunity['status']) ?></span>
                    </div>
                </div>
                <form method="post" action="<?= e(url('/student/save-opportunity.php')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="opportunity_id" value="<?= (int)$opportunity['id'] ?>">
                    <button type="submit" class="opp-save-btn-lg">
                        <i class="bi <?= $isSaved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                        <?= $isSaved ? 'Saved' : 'Save' ?>
                    </button>
                </form>
            </div>

            <div class="detail-meta-row">
                <span><i class="bi bi-person-fill"></i>Posted by <?= e($opportunity['creator_name'] ?: 'Unknown') ?></span>
                <span><i class="bi bi-people-fill"></i>Team size: <?= (int)$opportunity['team_size_min'] ?> - <?= (int)$opportunity['team_size_max'] ?></span>
                <span><i class="bi bi-calendar3"></i>Deadline: <?= format_date($opportunity['deadline']) ?><?= $deadlinePassed ? ' (passed)' : '' ?></span>
            </div>

            <?php if ($domainNames): ?>
                <div class="detail-domain-tags">
                    <?php foreach ($domainNames as $name): ?><span><?= e($name) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <h2>Description</h2>
            <p><?= e($opportunity['description'] ?: 'No description provided.') ?></p>
        </div>

        <?php if (!empty($opportunity['problem_statement'])): ?>
            <div class="detail-card">
                <h2>Problem Statement</h2>
                <p><?= e($opportunity['problem_statement']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($opportunity['requirements'])): ?>
            <div class="detail-card">
                <h2>Requirements</h2>
                <p><?= e($opportunity['requirements']) ?></p>
            </div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Your Application</h2>

            <?php if ($application): ?>
                <div class="application-status-box mb-3">
                    <p class="mb-1">
                        Status:
                        <span class="badge rounded-pill <?= application_status_badge($application['status']) ?>"><?= e($application['status']) ?></span>
                    </p>
                    <?php if (!empty($application['message'])): ?>
                        <p class="mb-1"><strong>Your message:</strong> <?= e($application['message']) ?></p>
                    <?php endif; ?>
                    <p class="mb-0 text-muted small">Applied <?= e(time_ago($application['applied_at'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($application && $application['status'] === 'Pending'): ?>
                <form method="post" action="<?= e(url('/student/apply-opportunity.php')) ?>" onsubmit="return confirm('Withdraw your application for this opportunity?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="opportunity_id" value="<?= (int)$opportunity['id'] ?>">
                    <input type="hidden" name="action" value="withdraw">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle"></i> Withdraw Application
                    </button>
                </form>
            <?php elseif ($application && $application['status'] === 'Accepted'): ?>
                <p class="text-muted mb-0">You already applied — status: <strong>Accepted</strong>. No further action is available for this opportunity.</p>
            <?php elseif ($canApply): ?>
                <?php if ($application): ?>
                    <p class="text-muted small mb-2">Your previous application was <?= e(strtolower($application['status'])) ?>. You're welcome to apply again.</p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/student/apply-opportunity.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="opportunity_id" value="<?= (int)$opportunity['id'] ?>">
                    <input type="hidden" name="action" value="apply">
                    <div class="mb-3">
                        <label for="apply-message" class="form-label">Message to the opportunity owner (optional)</label>
                        <textarea id="apply-message" name="message" class="form-control" rows="4" maxlength="2000" placeholder="Tell them why you're a good fit..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send-fill"></i> Apply Now
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted mb-0">
                    <?php if ($opportunity['status'] !== 'Open'): ?>
                        This opportunity is currently <strong><?= e($opportunity['status']) ?></strong> and is not accepting applications.
                    <?php elseif ($deadlinePassed): ?>
                        The application deadline for this opportunity has passed.
                    <?php else: ?>
                        Applications are not open for this opportunity right now.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
