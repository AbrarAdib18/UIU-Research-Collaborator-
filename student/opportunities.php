<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

/** Deadline indicator for a still-"Open"-status opportunity. */
function opportunity_deadline_flag(?string $deadline): ?array
{
    if (!$deadline) {
        return null;
    }
    $days = (strtotime($deadline) - strtotime(date('Y-m-d'))) / 86400;
    if ($days < 0) {
        return ['label' => 'Expired', 'badge' => 'bg-danger-subtle text-danger-emphasis'];
    }
    if ($days <= 7) {
        return ['label' => 'Closing Soon', 'badge' => 'bg-warning-subtle text-warning-emphasis'];
    }
    return null;
}

function application_status_badge(string $status): string
{
    switch ($status) {
        case 'Accepted':  return 'bg-success-subtle text-success-emphasis';
        case 'Rejected':  return 'bg-danger-subtle text-danger-emphasis';
        case 'Withdrawn': return 'bg-secondary-subtle text-secondary-emphasis';
        default:          return 'bg-warning-subtle text-warning-emphasis'; // Pending
    }
}

// -------------------------------------------------------------------
// Filters
// -------------------------------------------------------------------
$q           = trim((string)($_GET['q'] ?? ''));
$domainId    = isset($_GET['domain']) && $_GET['domain'] !== '' ? (int)$_GET['domain'] : 0;
$projectType = trim((string)($_GET['project_type'] ?? ''));
$deadlineFilter = (string)($_GET['deadline'] ?? '');
if (!in_array($deadlineFilter, ['', 'closing_soon', 'expired'], true)) {
    $deadlineFilter = '';
}

$conditions = ["o.status = 'Open'", "o.visibility = 'Public'"];
$params     = [];

if ($q !== '') {
    $conditions[] = '(o.title LIKE ? OR o.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($domainId > 0) {
    $conditions[] = 'EXISTS (SELECT 1 FROM opportunity_domains odx WHERE odx.opportunity_id = o.id AND odx.domain_id = ?)';
    $params[] = $domainId;
}
if ($projectType !== '') {
    $conditions[] = 'o.project_type = ?';
    $params[] = $projectType;
}
if ($deadlineFilter === 'closing_soon') {
    $conditions[] = 'o.deadline IS NOT NULL AND o.deadline >= CURDATE() AND o.deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
} elseif ($deadlineFilter === 'expired') {
    $conditions[] = 'o.deadline IS NOT NULL AND o.deadline < CURDATE()';
}

$whereSql = implode(' AND ', $conditions);

// -------------------------------------------------------------------
// Pagination
// -------------------------------------------------------------------
$perPage = 9;
$page    = current_page();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM research_opportunities o WHERE {$whereSql}");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = paginate_offset($page, $perPage);

$listSql = "SELECT o.*, GROUP_CONCAT(DISTINCT d.name SEPARATOR '||') AS domain_names,
                   app.status AS my_application_status,
                   (sv.user_id IS NOT NULL) AS is_saved
            FROM research_opportunities o
            LEFT JOIN opportunity_domains od ON od.opportunity_id = o.id
            LEFT JOIN research_domains d ON d.id = od.domain_id
            LEFT JOIN opportunity_applications app ON app.opportunity_id = o.id AND app.user_id = ?
            LEFT JOIN saved_opportunities sv ON sv.opportunity_id = o.id AND sv.user_id = ?
            WHERE {$whereSql}
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute(array_merge([$userId, $userId], $params));
$opportunities = $listStmt->fetchAll();

$domains = all_research_domains($pdo);

$projectTypes = $pdo->query(
    "SELECT DISTINCT project_type FROM research_opportunities
     WHERE status = 'Open' AND visibility = 'Public' AND project_type IS NOT NULL AND project_type <> ''
     ORDER BY project_type"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Research Opportunities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Opportunities || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-filter-bar {
            background-color: var(--uiu-light-blue);
            border-radius: 10px;
            padding: 16px;
        }
        .app-filter-bar .form-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--uiu-dark-blue);
            margin-bottom: 4px;
        }
        .opportunities-grid {
            width: 95%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        @media (max-width: 1200px) {
            .opportunities-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .opportunities-grid { grid-template-columns: 1fr; width: 100%; }
        }
        .opportunities-grid .dashboard-opportunity-card {
            min-height: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
        }
        .opp-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 6px;
        }
        .opp-save-btn {
            border: none;
            background: transparent;
            color: var(--uiu-blue);
            font-size: 18px;
            line-height: 1;
            padding: 2px 4px;
            flex-shrink: 0;
        }
        .opp-save-btn:hover { color: var(--uiu-dark-blue); }
        .opp-badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 6px 0;
        }
        .opp-badges-row .badge { font-weight: 600; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="welcome-section">
            <h2>Research Opportunities</h2>
            <p>Browse open research opportunities and apply to the ones that match your interests.</p>
        </div>

        <form method="get" class="app-filter-bar mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 col-sm-6">
                    <label class="form-label" for="filter-q">Keyword</label>
                    <input type="text" id="filter-q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search title or description">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label" for="filter-domain">Research Domain</label>
                    <select id="filter-domain" name="domain" class="form-select">
                        <option value="">All Domains</option>
                        <?php foreach ($domains as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $domainId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label" for="filter-type">Project Type</label>
                    <select id="filter-type" name="project_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($projectTypes as $pt): ?>
                            <option value="<?= e($pt) ?>" <?= $projectType === $pt ? 'selected' : '' ?>><?= e($pt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label" for="filter-deadline">Deadline</label>
                    <select id="filter-deadline" name="deadline" class="form-select">
                        <option value="">Any</option>
                        <option value="closing_soon" <?= $deadlineFilter === 'closing_soon' ? 'selected' : '' ?>>Closing Soon</option>
                        <option value="expired" <?= $deadlineFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-1 col-sm-6 d-grid">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>

        <?php if ($opportunities): ?>
            <div class="opportunities-grid">
                <?php foreach ($opportunities as $opp): ?>
                    <?php
                        $tags = $opp['domain_names'] ? explode('||', $opp['domain_names']) : [];
                        $flag = opportunity_deadline_flag($opp['deadline']);
                        $saved = (bool)$opp['is_saved'];
                    ?>
                    <div class="dashboard-opportunity-card">
                        <div class="opp-card-top">
                            <span class="opportunity-badge fydp"><?= e($opp['project_type'] ?: 'Research') ?></span>
                            <form method="post" action="<?= e(url('/student/save-opportunity.php')) ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="opportunity_id" value="<?= (int)$opp['id'] ?>">
                                <button type="submit" class="opp-save-btn" title="<?= $saved ? 'Remove from saved' : 'Save this opportunity' ?>">
                                    <i class="bi <?= $saved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                </button>
                            </form>
                        </div>
                        <h3><?= e($opp['title']) ?></h3>
                        <div class="opportunity-members"><i class="bi bi-people-fill"></i> Need <?= (int)$opp['team_size_min'] ?> - <?= (int)$opp['team_size_max'] ?> Members</div>
                        <div class="opportunity-tags">
                            <?php foreach (array_slice($tags, 0, 3) as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?>
                        </div>
                        <div class="opp-badges-row">
                            <?php if ($flag): ?>
                                <span class="badge rounded-pill <?= $flag['badge'] ?>"><?= e($flag['label']) ?></span>
                            <?php endif; ?>
                            <?php if ($opp['my_application_status']): ?>
                                <span class="badge rounded-pill <?= application_status_badge($opp['my_application_status']) ?>">
                                    Applied: <?= e($opp['my_application_status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="opportunity-card-footer">
                            <span>Deadline: <?= format_date($opp['deadline']) ?></span>
                            <a href="<?= e(url('/student/opportunity-details.php?id=' . $opp['id'])) ?>">View Details<i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="app-pagination" aria-label="Opportunities pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="app-empty-state">
                <i class="bi bi-briefcase"></i>
                <p>No open opportunities match your filters right now — try adjusting your search or check back soon.</p>
            </div>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
