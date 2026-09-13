<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];
$perPage   = 9;
$page      = current_page();

$allowedTabs = ['recommended', 'top-matches', 'new', 'recently-active'];
$tab = (string)($_GET['tab'] ?? 'recommended');
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'recommended';
}

$q               = trim((string)($_GET['q'] ?? ''));
$selectedDomains = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['domains'] ?? [])))));
$selectedSkills  = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['skills'] ?? [])))));
$department      = trim((string)($_GET['department'] ?? ''));
$level           = trim((string)($_GET['level'] ?? ''));

// ---------------------------------------------------------------------
// Build the filtered candidate list (excludes self / inactive / private).
// ---------------------------------------------------------------------
$where  = [
    "u.role = 'student'",
    "u.status = 'active'",
    "sp.user_id != :uid",
    "(pv.profile_visibility IS NULL OR pv.profile_visibility <> 'Private')",
];
$params = [':uid' => $userId];

if ($q !== '') {
    $where[] = "(u.name LIKE :q1 OR sp.bio LIKE :q2 OR sp.research_statement LIKE :q3
        OR EXISTS (SELECT 1 FROM profile_skills psq JOIN skills skq ON skq.id = psq.skill_id WHERE psq.profile_id = sp.id AND skq.name LIKE :q4)
        OR EXISTS (SELECT 1 FROM profile_research_domains prdq JOIN research_domains rdq ON rdq.id = prdq.domain_id WHERE prdq.profile_id = sp.id AND rdq.name LIKE :q5))";
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
    $params[':q5'] = $like;
}

if ($selectedDomains) {
    $ph = [];
    foreach ($selectedDomains as $i => $id) {
        $key       = ":dom{$i}";
        $ph[]      = $key;
        $params[$key] = $id;
    }
    $where[] = 'EXISTS (SELECT 1 FROM profile_research_domains prdf WHERE prdf.profile_id = sp.id AND prdf.domain_id IN (' . implode(',', $ph) . '))';
}

if ($selectedSkills) {
    $ph = [];
    foreach ($selectedSkills as $i => $id) {
        $key       = ":skl{$i}";
        $ph[]      = $key;
        $params[$key] = $id;
    }
    $where[] = 'EXISTS (SELECT 1 FROM profile_skills psf WHERE psf.profile_id = sp.id AND psf.skill_id IN (' . implode(',', $ph) . '))';
}

// Department is free text in the schema (student_profiles.department), so we
// match it as a substring against the chosen abbreviation (e.g. "CSE" also
// matches "CSE Department", "Dept. of CSE", etc.).
if ($department !== '' && $department !== 'Any') {
    $where[] = 'sp.department LIKE :dept';
    $params[':dept'] = '%' . $department . '%';
}

$candidates = [];
try {
    $sql = 'SELECT sp.*, u.name AS name
            FROM student_profiles sp
            JOIN users u ON u.id = sp.user_id
            LEFT JOIN profile_visibility pv ON pv.profile_id = sp.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY sp.id DESC
            LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();
} catch (Throwable $ex) {
    error_log('research-connect.php listing query failed: ' . $ex->getMessage());
    flash('error', 'Something went wrong while loading researchers. Please try again.');
    $candidates = [];
}

// ---------------------------------------------------------------------
// Academic level filter.
// student_profiles.semester is free text (e.g. "7th Trimester"), so we
// reuse extract_semester_number() to pull the leading number out of it:
// semester <= 8 is treated as Undergraduate, > 8 as Graduate. Rows whose
// semester can't be parsed are excluded from either specific filter.
// ---------------------------------------------------------------------
if ($level === 'Undergraduate' || $level === 'Graduate') {
    $candidates = array_values(array_filter($candidates, function ($row) use ($level) {
        $sem = extract_semester_number($row['semester'] ?? null);
        if ($sem === null) {
            return false;
        }
        return $level === 'Undergraduate' ? $sem <= 8 : $sem > 8;
    }));
}

// ---------------------------------------------------------------------
// Match score (per candidate) + tab-specific sort/filter.
// ---------------------------------------------------------------------
foreach ($candidates as &$row) {
    $row['match_score'] = calculate_match_score($pdo, $studentProfile, $row);
}
unset($row);

switch ($tab) {
    case 'top-matches':
        $candidates = array_values(array_filter($candidates, fn($r) => $r['match_score'] >= 60));
        usort($candidates, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        break;
    case 'new':
        usort($candidates, fn($a, $b) => strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']));
        break;
    case 'recently-active':
        usort($candidates, fn($a, $b) => strtotime((string)$b['updated_at']) <=> strtotime((string)$a['updated_at']));
        break;
    case 'recommended':
    default:
        usort($candidates, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        break;
}

$total      = count($candidates);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$pageRows = array_slice($candidates, paginate_offset($page, $perPage), $perPage);

$domains = all_research_domains($pdo);
$skills  = all_skills($pdo);

// ---------------------------------------------------------------------
// Teams the current user can invite people into (Leader of a non-full,
// Active team). Reused for every "Invite" control on this page.
// ---------------------------------------------------------------------
$eligibleTeams = [];
try {
    $leaderTeamsStmt = $pdo->prepare(
        "SELECT rt.id, rt.name, rt.team_size_limit
         FROM research_teams rt
         JOIN team_members tm ON tm.team_id = rt.id AND tm.user_id = ? AND tm.role = 'Leader' AND tm.status = 'Active'
         ORDER BY rt.name"
    );
    $leaderTeamsStmt->execute([$userId]);
    foreach ($leaderTeamsStmt->fetchAll() as $t) {
        if (team_member_count($pdo, (int)$t['id']) < (int)$t['team_size_limit']) {
            $eligibleTeams[] = $t;
        }
    }
} catch (Throwable $ex) {
    error_log('research-connect.php eligible-teams query failed: ' . $ex->getMessage());
    $eligibleTeams = [];
}

function rc_skill_names_for(PDO $pdo, int $pid): array
{
    $stmt = $pdo->prepare('SELECT sk.name FROM profile_skills ps JOIN skills sk ON sk.id = ps.skill_id WHERE ps.profile_id = ? ORDER BY sk.name');
    $stmt->execute([$pid]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Build a query string that swaps `tab` (and resets `page`) while keeping every other current filter. */
function rc_tab_url(string $tab): string
{
    $params         = $_GET;
    $params['tab']  = $tab;
    unset($params['page']);
    return '?' . http_build_query($params);
}

$tabLabels = [
    'recommended'     => 'Recommended for You',
    'top-matches'     => 'Top Matches',
    'new'             => 'New Researchers',
    'recently-active' => 'Recently Active',
];

$completion = (int)($studentProfile['profile_completion'] ?? 0);
$circleDeg  = (int)round(min(100, max(0, $completion)) * 3.6);
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
    <link rel="stylesheet" href="../CSS/research-connect.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="research-connect-main">
        <?php render_flashes(); ?>

        <div class="research-connect-header">
            <div class="research-connect-title">
                <h2>Research Connect</h2>
                <p>Discover the most compatible research collaborators based on your interests, skills, and goals.</p>
            </div>
        </div>

        <div class="research-connect-grid">

            <section class="matches-content">

                <div class="matches-top-row">
                    <div class="match-tabs">
                        <?php foreach ($tabLabels as $key => $label): ?>
                            <a href="<?= e(rc_tab_url($key)) ?>" class="match-tab<?= $tab === $key ? ' active' : '' ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <a href="#" class="how-it-works" data-bs-toggle="modal" data-bs-target="#matchFormulaModal">How it works?</a>
                </div>

                <div class="recommended-section">
                    <h3 class="section-title"><?= e($tabLabels[$tab]) ?> (<?= (int)$total ?>)</h3>

                    <?php if (!$pageRows): ?>
                        <div class="app-empty-state">
                            <i class="bi bi-people"></i>
                            <p>No researchers match your filters yet. Try adjusting them or check back later.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($pageRows as $row): ?>
                        <?php
                        $candidateUserId = (int)$row['user_id'];
                        $skillNames      = rc_skill_names_for($pdo, (int)$row['id']);
                        $shownSkills     = array_slice($skillNames, 0, 3);
                        $moreCount       = max(0, count($skillNames) - count($shownSkills));
                        $photo           = $row['profile_photo'] ?? null;
                        ?>
                        <div class="researcher-match-card">

                            <div class="match-score-small">
                                <strong><?= (int)$row['match_score'] ?>%</strong>
                                <span>Match</span>
                            </div>

                            <div class="researcher-avatar-wrapper">
                                <?php if ($photo): ?>
                                    <img src="<?= e(url('/uploads/avatars/' . $photo)) ?>" alt="<?= e($row['name']) ?>" class="researcher-avatar">
                                <?php else: ?>
                                    <div class="researcher-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-weight:700;font-size:15px;">
                                        <?= e(initials($row['name'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="researcher-information">
                                <div class="researcher-name-row">
                                    <h4><?= e($row['name']) ?></h4>
                                    <span class="researcher-role">Student</span>
                                </div>

                                <div class="researcher-meta">
                                    <span><?= e($row['department'] ? $row['department'] . ' Department' : 'Department not set') ?></span>
                                    <span class="meta-divider">||</span>
                                    <span><?= e($row['semester'] ?: 'Semester not set') ?></span>
                                </div>

                                <div class="researcher-tags">
                                    <?php if (!$skillNames): ?>
                                        <span class="skill-tag">No skills listed</span>
                                    <?php else: ?>
                                        <?php foreach ($shownSkills as $sn): ?>
                                            <span class="skill-tag"><?= e($sn) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($moreCount > 0): ?>
                                            <span class="more-tag">+<?= (int)$moreCount ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="researcher-actions">
                                <a href="<?= e(url('/student/researcher-profile.php?id=' . $candidateUserId)) ?>" class="view-profile-button">View Profile</a>
                                <?php if ($eligibleTeams): ?>
                                    <button type="button" class="invite-button" data-bs-toggle="collapse" data-bs-target="#invite-<?= $candidateUserId ?>">
                                        Invite <i class="bi bi-plus-lg"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="<?= e(url('/student/team-create.php')) ?>" class="invite-button">Invite <i class="bi bi-plus-lg"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($eligibleTeams): ?>
                            <div class="collapse invite-panel" id="invite-<?= $candidateUserId ?>">
                                <form method="post" action="<?= e(url('/student/team-invitations.php')) ?>" class="d-flex flex-wrap align-items-center gap-2 p-2 mb-3 border rounded bg-light">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="invited_user_id" value="<?= $candidateUserId ?>">
                                    <select name="team_id" class="form-select form-select-sm" style="max-width:180px;" required>
                                        <?php foreach ($eligibleTeams as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="message" class="form-control form-control-sm" style="max-width:260px;" placeholder="Optional message">
                                    <button type="submit" class="btn btn-sm btn-primary">Send Invite</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="app-pagination" aria-label="Researcher results pages">
                        <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
                    </nav>
                <?php endif; ?>

                <div class="improve-match-card">
                    <div class="improve-match-content">
                        <h3>Improve Your Match Score</h3>
                        <p>Add more details to your profile to get better and more accurate matches.</p>
                    </div>
                    <div class="improve-progress-area">
                        <div class="improve-progress">
                            <div class="improve-progress-bar" style="width: <?= $completion ?>%;"></div>
                        </div>
                        <strong><?= $completion ?>%</strong>
                    </div>
                </div>
            </section>

            <aside class="research-connect-right">

                <div class="your-match-card">
                    <div class="your-match-content">
                        <h3>Your profile completion is</h3>
                        <strong><?= $completion ?>%</strong>
                        <p>Keep your profile updated to get better matches!</p>
                    </div>
                    <div class="score-circle" style="background: conic-gradient(var(--uiu-blue) 0deg <?= $circleDeg ?>deg, #d2d2d2 <?= $circleDeg ?>deg 360deg);">
                        <div class="score-circle-inner"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>

                <form method="get" action="<?= e(url('/student/research-connect.php')) ?>">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

                    <div class="filter-card">
                        <div class="filter-card-header">
                            <h3>Research Interests</h3>
                            <a href="<?= e(url('/student/research-connect.php')) ?>" class="clear-filter">Clear All</a>
                        </div>
                        <div class="filter-group">
                            <?php foreach ($domains as $i => $d): ?>
                                <label class="filter-checkbox<?= $i >= 8 ? ' d-none domain-extra' : '' ?>">
                                    <input type="checkbox" name="domains[]" value="<?= (int)$d['id'] ?>" <?= in_array((int)$d['id'], $selectedDomains, true) ? 'checked' : '' ?>>
                                    <span><?= e($d['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (count($domains) > 8): ?>
                                <a href="#" class="see-more" data-toggle-class="domain-extra">See More</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="filter-card skills-filter-card">
                        <div class="filter-card-header">
                            <h3>Skills</h3>
                            <a href="<?= e(url('/student/research-connect.php')) ?>" class="clear-filter">Clear All</a>
                        </div>
                        <div class="skills-search">
                            <input type="text" id="skillsSearchInput" placeholder="Search skills...">
                        </div>
                        <div class="filter-group skills-group">
                            <?php if (!$skills): ?>
                                <p style="font-size:9px;color:#666;">No skills catalogued yet.</p>
                            <?php endif; ?>
                            <?php foreach ($skills as $i => $s): ?>
                                <label class="filter-checkbox skill-option<?= $i >= 8 ? ' d-none skill-extra' : '' ?>" data-skill-name="<?= e(mb_strtolower($s['name'])) ?>">
                                    <input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $selectedSkills, true) ? 'checked' : '' ?>>
                                    <span><?= e($s['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (count($skills) > 8): ?>
                                <a href="#" class="see-more" data-toggle-class="skill-extra">See More</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="select-filter-group">
                        <label for="levelSelect">Academic Level</label>
                        <select name="level" id="levelSelect">
                            <option value="" <?= $level === '' ? 'selected' : '' ?>>Any Level</option>
                            <option value="Undergraduate" <?= $level === 'Undergraduate' ? 'selected' : '' ?>>Undergraduate</option>
                            <option value="Graduate" <?= $level === 'Graduate' ? 'selected' : '' ?>>Graduate</option>
                        </select>
                    </div>

                    <div class="select-filter-group">
                        <label for="deptSelect">Department</label>
                        <select name="department" id="deptSelect">
                            <option value="" <?= $department === '' ? 'selected' : '' ?>>Any Department</option>
                            <option value="CSE" <?= $department === 'CSE' ? 'selected' : '' ?>>CSE Department</option>
                            <option value="EEE" <?= $department === 'EEE' ? 'selected' : '' ?>>EEE Department</option>
                            <option value="BBA" <?= $department === 'BBA' ? 'selected' : '' ?>>BBA Department</option>
                        </select>
                    </div>

                    <button type="submit" class="apply-filter-button">Apply Filter</button>
                </form>
            </aside>

        </div>
    </main>

    <!-- MATCH FORMULA EXPLANATION -->
    <div class="modal fade" id="matchFormulaModal" tabindex="-1" aria-labelledby="matchFormulaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="matchFormulaModalLabel">How Your Match Score Is Calculated</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size:13px;color:#555;">Each candidate's match score (0-100%) is calculated from your profiles:</p>
                    <ul style="font-size:13px;color:#444;line-height:1.9;">
                        <li><strong>Shared research domains — 40%</strong>: how many of your research domains overlap.</li>
                        <li><strong>Shared skills — 30%</strong>: how many of your listed skills overlap.</li>
                        <li><strong>Department / program — 15%</strong>: +10 for the same department, +5 more for the same program.</li>
                        <li><strong>Academic-level closeness — 10%</strong>: +10 within 1 trimester, +5 within 3 trimesters.</li>
                        <li><strong>Mutual availability — 5%</strong>: both of you marked as looking for a team.</li>
                    </ul>
                    <p style="font-size:13px;color:#555;margin-bottom:0;">Complete more of your profile — domains, skills, and preferences — to get more accurate matches.</p>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.see-more').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var cls = btn.getAttribute('data-toggle-class');
            document.querySelectorAll('.' + cls).forEach(function (el) { el.classList.remove('d-none'); });
            btn.classList.add('d-none');
        });
    });

    var skillSearch = document.getElementById('skillsSearchInput');
    if (skillSearch) {
        skillSearch.addEventListener('input', function () {
            var term = skillSearch.value.trim().toLowerCase();
            document.querySelectorAll('.skill-option').forEach(function (label) {
                var name    = label.getAttribute('data-skill-name') || '';
                var matches = term === '' || name.indexOf(term) !== -1;
                label.classList.toggle('d-none', !matches);
            });
        });
    }
});
</script>
</body>
</html>
