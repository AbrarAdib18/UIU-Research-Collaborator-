<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$type = (string)($_GET['type'] ?? 'students');
if (!in_array($type, ['students', 'teams'], true)) {
    $type = 'students';
}
$q               = trim((string)($_GET['q'] ?? ''));
$selectedDomains = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['domains'] ?? [])))));
$department      = trim((string)($_GET['department'] ?? ''));
$lookingForAdvisorOnly = isset($_GET['seeking_advisor']);

$domains = all_research_domains($pdo);

$students = [];
$teams    = [];

if ($type === 'students') {
    $where  = ["u.role = 'student'", "u.status = 'active'", "(pv.profile_visibility IS NULL OR pv.profile_visibility <> 'Private')"];
    $params = [];
    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR sp.bio LIKE ? OR sp.research_statement LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($selectedDomains) {
        $ph = implode(',', array_fill(0, count($selectedDomains), '?'));
        $where[] = "EXISTS (SELECT 1 FROM profile_research_domains prdf WHERE prdf.profile_id = sp.id AND prdf.domain_id IN ($ph))";
        foreach ($selectedDomains as $d) { $params[] = $d; }
    }
    if ($department !== '') {
        $where[] = 'sp.department LIKE ?';
        $params[] = '%' . $department . '%';
    }
    if ($lookingForAdvisorOnly) {
        $where[] = "EXISTS (SELECT 1 FROM research_preferences rp WHERE rp.profile_id = sp.id AND rp.looking_for_team = 1)";
    }

    $sql = "SELECT sp.*, u.name, u.id AS user_id
            FROM student_profiles sp JOIN users u ON u.id = sp.user_id
            LEFT JOIN profile_visibility pv ON pv.profile_id = sp.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sp.updated_at DESC LIMIT 60";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} else {
    $where  = ["1=1"];
    $params = [];
    if ($q !== '') {
        $where[] = '(rt.name LIKE ? OR rt.description LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like;
    }
    if ($selectedDomains) {
        $ph = implode(',', array_fill(0, count($selectedDomains), '?'));
        $where[] = "rt.research_domain_id IN ($ph)";
        foreach ($selectedDomains as $d) { $params[] = $d; }
    }
    $sql = "SELECT rt.*, u.name AS creator_name, d.name AS domain_name,
                   (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = rt.id AND tm.status='Active') AS member_count,
                   (SELECT COUNT(*) FROM advisor_assignments aa WHERE aa.team_id = rt.id AND aa.status='active') AS has_advisor
            FROM research_teams rt
            JOIN users u ON u.id = rt.created_by
            LEFT JOIN research_domains d ON d.id = rt.research_domain_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY rt.updated_at DESC LIMIT 60";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $teams = $stmt->fetchAll();
    if ($lookingForAdvisorOnly) {
        $teams = array_values(array_filter($teams, fn($t) => (int)$t['has_advisor'] === 0));
    }
}

function frc_skill_names_for(PDO $pdo, int $pid): array
{
    $stmt = $pdo->prepare('SELECT sk.name FROM profile_skills ps JOIN skills sk ON sk.id = ps.skill_id WHERE ps.profile_id = ? ORDER BY sk.name');
    $stmt->execute([$pid]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = 'Research Connect';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Connect || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .type-tabs{display:flex;gap:8px;margin-bottom:16px}
        .type-tabs a{padding:8px 18px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none}
        .type-tabs a.active{background:var(--uiu-blue);color:#fff}
        .rc-grid{display:grid;grid-template-columns:2.4fr 1fr;gap:18px}
        @media (max-width:900px){.rc-grid{grid-template-columns:1fr}}
        .rc-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px;margin-bottom:12px;display:flex;gap:14px;align-items:flex-start}
        .avatar-md{width:52px;height:52px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:16px;font-weight:700;flex-shrink:0;object-fit:cover}
        .rc-tags{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}
        .rc-tags span{background:var(--uiu-light-blue);color:var(--uiu-dark-blue);font-size:11px;font-weight:600;padding:3px 9px;border-radius:14px}
        .filter-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px;margin-bottom:14px}
        .filter-card h3{font-size:14px;color:var(--uiu-blue);font-weight:700;margin-bottom:10px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <div class="app-page-header">
            <div><h2>Research Connect</h2><p class="text-muted mb-0">Discover students and teams to advise or mentor.</p></div>
        </div>

        <div class="type-tabs">
            <a href="<?= e(url('/faculty/research-connect.php?type=students')) ?>" class="<?= $type === 'students' ? 'active' : '' ?>">Students</a>
            <a href="<?= e(url('/faculty/research-connect.php?type=teams')) ?>" class="<?= $type === 'teams' ? 'active' : '' ?>">Research Teams</a>
        </div>

        <div class="rc-grid">
            <section>
                <?php if ($type === 'students'): ?>
                    <?php if (!$students): ?>
                        <div class="app-empty-state"><i class="bi bi-people"></i><p>No students match your filters.</p></div>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <?php $skillNames = frc_skill_names_for($pdo, (int)$s['id']); ?>
                        <div class="rc-card">
                            <?php if (!empty($s['profile_photo'])): ?>
                                <img class="avatar-md" src="<?= e(url('/uploads/avatars/' . $s['profile_photo'])) ?>" alt="<?= e($s['name']) ?>">
                            <?php else: ?>
                                <span class="avatar-md"><?= e(initials($s['name'])) ?></span>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <h4 style="margin:0;font-size:15px;"><?= e($s['name']) ?></h4>
                                <div class="text-muted small"><?= e($s['department'] ?: 'Department not set') ?> &middot; <?= e($s['semester'] ?: 'Semester not set') ?></div>
                                <div class="rc-tags">
                                    <?php foreach (array_slice($skillNames, 0, 4) as $sn): ?><span><?= e($sn) ?></span><?php endforeach; ?>
                                    <?php if (!$skillNames): ?><span class="text-muted small">No skills listed</span><?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= e(url('/faculty/student-profile.php?id=' . $s['user_id'])) ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php if (!$teams): ?>
                        <div class="app-empty-state"><i class="bi bi-people-fill"></i><p>No teams match your filters.</p></div>
                    <?php endif; ?>
                    <?php foreach ($teams as $t): ?>
                        <div class="rc-card">
                            <span class="avatar-md"><i class="bi bi-people-fill"></i></span>
                            <div class="flex-grow-1">
                                <h4 style="margin:0;font-size:15px;"><?= e($t['name']) ?></h4>
                                <div class="text-muted small"><?= (int)$t['member_count'] ?>/<?= (int)$t['team_size_limit'] ?> members &middot; Led by <?= e($t['creator_name']) ?><?php if ($t['domain_name']): ?> &middot; <?= e($t['domain_name']) ?><?php endif; ?></div>
                                <?php if ((int)$t['has_advisor'] === 0): ?><span class="badge bg-warning-subtle text-warning-emphasis mt-1">Seeking Advisor</span><?php else: ?><span class="badge bg-success-subtle text-success-emphasis mt-1">Has Advisor</span><?php endif; ?>
                            </div>
                            <a href="<?= e(url('/faculty/advised-teams.php?team_id=' . $t['id'])) ?>" class="btn btn-sm btn-outline-primary">View Team</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <aside>
                <form method="get">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <div class="filter-card">
                        <h3>Search</h3>
                        <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Keyword...">
                    </div>
                    <div class="filter-card">
                        <h3>Research Domains</h3>
                        <?php foreach ($domains as $d): ?>
                            <label class="d-block small"><input type="checkbox" name="domains[]" value="<?= (int)$d['id'] ?>" <?= in_array((int)$d['id'], $selectedDomains, true) ? 'checked' : '' ?>> <?= e($d['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($type === 'students'): ?>
                    <div class="filter-card">
                        <h3>Department</h3>
                        <input type="text" name="department" value="<?= e($department) ?>" class="form-control form-control-sm" placeholder="e.g. CSE">
                    </div>
                    <?php endif; ?>
                    <div class="filter-card">
                        <label class="d-block small"><input type="checkbox" name="seeking_advisor" value="1" <?= $lookingForAdvisorOnly ? 'checked' : '' ?>> <?= $type === 'students' ? 'Looking for a team/advisor' : 'Seeking an advisor' ?></label>
                    </div>
                    <button type="submit" class="btn btn-uiu w-100">Apply Filters</button>
                </form>
            </aside>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
