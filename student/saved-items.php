<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

/**
 * Saved Items — two read-only sections: Saved Opportunities (writes owned
 * by the Opportunities module's save-opportunity.php) and Saved Resources
 * (writes owned by this module's save-resource.php). "Remove" on each row
 * simply re-POSTs to the owning toggle endpoint, which unsaves it.
 */

$pdo    = db();
$userId = (int)$currentUser['id'];

$oppStmt = $pdo->prepare(
    'SELECT o.*, so.saved_at
     FROM saved_opportunities so
     JOIN research_opportunities o ON o.id = so.opportunity_id
     WHERE so.user_id = ?
     ORDER BY so.saved_at DESC'
);
$oppStmt->execute([$userId]);
$savedOpportunities = $oppStmt->fetchAll();

$resStmt = $pdo->prepare(
    'SELECT r.*, d.name AS domain_name, sr.saved_at
     FROM saved_resources sr
     JOIN research_resources r ON r.id = sr.resource_id
     LEFT JOIN research_domains d ON d.id = r.domain_id
     WHERE sr.user_id = ?
     ORDER BY sr.saved_at DESC'
);
$resStmt->execute([$userId]);
$savedResources = $resStmt->fetchAll();

$typeIcons = [
    'Research Paper'   => 'bi-file-earmark-text',
    'Journal Article'  => 'bi-journal-text',
    'Conference Paper' => 'bi-file-earmark-richtext',
    'Dataset'          => 'bi-database',
    'Book'             => 'bi-journal-bookmark',
    'Thesis'           => 'bi-mortarboard',
    'Tutorial'         => 'bi-play-btn',
    'Documentation'    => 'bi-file-earmark-code',
    'Other'            => 'bi-file-earmark',
];

function saved_items_status_badge(?string $status): string
{
    switch ($status) {
        case 'Open':
            return 'bg-success';
        case 'Closed':
            return 'bg-secondary';
        case 'Completed':
            return 'bg-primary';
        case 'Draft':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Items || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="welcome-section">
            <h2>Saved Items</h2>
            <p>Opportunities and repository resources you've bookmarked for later.</p>
        </div>

        <section class="dashboard-section">
            <div class="section-title-row">
                <h2>Saved Opportunities</h2>
                <a href="<?= e(url('/student/opportunities.php')) ?>">Browse Opportunities</a>
            </div>
            <?php if ($savedOpportunities): ?>
                <div class="list-group">
                    <?php foreach ($savedOpportunities as $o): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <div class="fw-bold"><?= e($o['title']) ?></div>
                                <div class="small text-muted">
                                    Deadline: <?= format_date($o['deadline']) ?>
                                    &nbsp;<span class="badge <?= saved_items_status_badge($o['status']) ?>"><?= e($o['status'] ?: 'Open') ?></span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= e(url('/student/opportunity-details.php?id=' . $o['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <form method="post" action="<?= e(url('/student/save-opportunity.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="app-empty-state">
                    <i class="bi bi-bookmark"></i>
                    <p>No saved opportunities yet.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <div class="section-title-row">
                <h2>Saved Resources</h2>
                <a href="<?= e(url('/student/repository.php')) ?>">Browse Repository</a>
            </div>
            <?php if ($savedResources): ?>
                <div class="list-group">
                    <?php foreach ($savedResources as $r): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= e($typeIcons[$r['resource_type']] ?? 'bi-file-earmark') ?>" style="color:var(--uiu-blue); font-size:20px;"></i>
                                <div>
                                    <div class="fw-bold"><?= e($r['title']) ?></div>
                                    <div class="small text-muted">
                                        <?= e($r['resource_type']) ?><?php if (!empty($r['domain_name'])): ?> &middot; <?= e($r['domain_name']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= e(url('/student/repository-details.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <form method="post" action="<?= e(url('/student/save-resource.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="resource_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="app-empty-state">
                    <i class="bi bi-database"></i>
                    <p>No saved resources yet.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
