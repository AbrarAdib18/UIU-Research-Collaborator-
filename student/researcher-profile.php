<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo          = db();
$viewerUserId = (int)$currentUser['id'];
$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Viewing your own profile through this page? Send them to the real editable one.
if ($targetUserId > 0 && $targetUserId === $viewerUserId) {
    redirect('/student/profile.php');
}

if ($targetUserId <= 0) {
    flash('error', 'That researcher profile could not be found.');
    redirect('/student/research-connect.php');
}

$stmt = $pdo->prepare(
    "SELECT sp.*, u.name AS name, u.status AS user_status, u.role AS user_role
     FROM student_profiles sp
     JOIN users u ON u.id = sp.user_id
     WHERE sp.user_id = ?
     LIMIT 1"
);
$stmt->execute([$targetUserId]);
$profile = $stmt->fetch();

if (!$profile || $profile['user_role'] !== 'student' || $profile['user_status'] !== 'active') {
    flash('error', 'That researcher profile could not be found.');
    redirect('/student/research-connect.php');
}

$profileId  = (int)$profile['id'];
$visibility = get_profile_visibility($pdo, $profileId);

if ($visibility['profile_visibility'] === 'Private') {
    flash('error', 'This profile is private.');
    redirect('/student/research-connect.php');
}
// 'Students Only' is visible here — the whole /student/ area sits behind
// student_guard.php, so the viewer is always a logged-in student.

$matchScore = calculate_match_score($pdo, $studentProfile, $profile);

// ---- Research domains (gated by research_visibility) ---------------------
$domainNames = [];
if ($visibility['research_visibility']) {
    $domainIds = get_profile_domain_ids($pdo, $profileId);
    if ($domainIds) {
        foreach (all_research_domains($pdo) as $d) {
            if (in_array((int)$d['id'], $domainIds, true)) {
                $domainNames[] = $d['name'];
            }
        }
    }
}

// ---- Skills (not visibility-gated) ---------------------------------------
$skillStmt = $pdo->prepare('SELECT sk.name, ps.level FROM profile_skills ps JOIN skills sk ON sk.id = ps.skill_id WHERE ps.profile_id = ? ORDER BY sk.name');
$skillStmt->execute([$profileId]);
$skillRows = $skillStmt->fetchAll();
$levelPct  = ['Beginner' => 25, 'Intermediate' => 50, 'Advanced' => 75, 'Expert' => 100];

// ---- Research preferences (gated by research_visibility) -----------------
$preferences = $visibility['research_visibility'] ? get_research_preferences($pdo, $profileId) : null;

// ---- Education / work experience (not gated) ------------------------------
$eduStmt = $pdo->prepare('SELECT * FROM education WHERE profile_id = ? ORDER BY start_date DESC, id DESC');
$eduStmt->execute([$profileId]);
$educationRows = $eduStmt->fetchAll();

$workStmt = $pdo->prepare('SELECT * FROM work_experience WHERE profile_id = ? ORDER BY start_date DESC, id DESC');
$workStmt->execute([$profileId]);
$workRows = $workStmt->fetchAll();

// ---- Projects (gated by project_visibility) --------------------------------
$projectRows = [];
if ($visibility['project_visibility']) {
    $projStmt = $pdo->prepare('SELECT * FROM projects WHERE profile_id = ? ORDER BY is_featured DESC, start_date DESC, id DESC');
    $projStmt->execute([$profileId]);
    $projectRows = $projStmt->fetchAll();
}

// ---- Publications (gated by publication_visibility) ------------------------
$publicationRows = [];
if ($visibility['publication_visibility']) {
    $pubStmt = $pdo->prepare('SELECT * FROM publications WHERE profile_id = ? ORDER BY publication_date DESC, id DESC');
    $pubStmt->execute([$profileId]);
    $publicationRows = $pubStmt->fetchAll();
}

// ---- Certifications / achievements / languages (not gated) -----------------
$certStmt = $pdo->prepare('SELECT * FROM certifications WHERE profile_id = ? ORDER BY issue_date DESC, id DESC');
$certStmt->execute([$profileId]);
$certRows = $certStmt->fetchAll();

$achieveStmt = $pdo->prepare('SELECT * FROM achievements WHERE profile_id = ? ORDER BY achievement_date DESC, id DESC');
$achieveStmt->execute([$profileId]);
$achievementRows = $achieveStmt->fetchAll();

$langStmt = $pdo->prepare('SELECT l.name, pl.proficiency FROM profile_languages pl JOIN languages l ON l.id = pl.language_id WHERE pl.profile_id = ? ORDER BY l.name');
$langStmt->execute([$profileId]);
$languageRows = $langStmt->fetchAll();

// ---------------------------------------------------------------------
// Teams the viewer can invite this person into (Leader of a non-full,
// Active team of their own).
// ---------------------------------------------------------------------
$eligibleTeams = [];
try {
    $leaderTeamsStmt = $pdo->prepare(
        "SELECT rt.id, rt.name, rt.team_size_limit
         FROM research_teams rt
         JOIN team_members tm ON tm.team_id = rt.id AND tm.user_id = ? AND tm.role = 'Leader' AND tm.status = 'Active'
         ORDER BY rt.name"
    );
    $leaderTeamsStmt->execute([$viewerUserId]);
    foreach ($leaderTeamsStmt->fetchAll() as $t) {
        if (team_member_count($pdo, (int)$t['id']) < (int)$t['team_size_limit']) {
            $eligibleTeams[] = $t;
        }
    }
} catch (Throwable $ex) {
    error_log('researcher-profile.php eligible-teams query failed: ' . $ex->getMessage());
    $eligibleTeams = [];
}

function rp_date_range(?string $start, ?string $end, $isCurrent = false): string
{
    $s = $start ? format_date($start, 'M Y') : null;
    if ($isCurrent) {
        return ($s ?: '—') . ' - Present';
    }
    $e = $end ? format_date($end, 'M Y') : 'Present';
    return ($s ?: '—') . ' - ' . $e;
}

/**
 * Only allow http(s) links to be rendered as clickable hrefs. e() escapes
 * HTML but does not stop a stored `javascript:` (or other dangerous scheme)
 * URL from executing when a viewer clicks the link, so external profile /
 * project links are whitelisted to http/https before being echoed as href.
 */
function rp_safe_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') {
        return null;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || !in_array(strtolower($scheme), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($profile['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/profile.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="profile-main">
        <?php render_flashes(); ?>

        <!-- COVER + PROFILE HEADER -->
        <section class="profile-header-card">
            <div class="profile-cover"></div>
            <div class="profile-header-content">

                <div class="profile-picture-container">
                    <?php if (!empty($profile['profile_photo'])): ?>
                        <img src="<?= e(url('/uploads/avatars/' . $profile['profile_photo'])) ?>" alt="<?= e($profile['name']) ?>" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture" style="display:flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:28px;font-weight:700;">
                            <?= e(initials($profile['name'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-basic-info">
                    <div class="profile-name-row">
                        <h1><?= e($profile['name']) ?></h1>
                        <span class="profile-role">Student</span>
                        <span class="profile-role"><?= (int)$matchScore ?>% Match</span>
                    </div>
                    <p class="profile-department">
                        <?= e($profile['department'] ?: 'Department not set') ?>
                        <span>||</span>
                        <?= e($profile['semester'] ?: 'Semester not set') ?>
                    </p>
                    <?php if (!empty($profile['program'])): ?>
                        <p><i class="bi bi-mortarboard-fill"></i> <?= e($profile['program']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-header-actions">
                    <a href="<?= e(url('/student/research-connect.php')) ?>" class="btn-profile-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <?php if ($eligibleTeams): ?>
                        <button type="button" class="btn-profile-primary" data-bs-toggle="collapse" data-bs-target="#inviteToTeamPanel">
                            <i class="bi bi-person-plus"></i> Invite to Team
                        </button>
                    <?php else: ?>
                        <a href="<?= e(url('/student/team-create.php')) ?>" class="btn-profile-primary">
                            <i class="bi bi-person-plus"></i> Create a Team First
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($eligibleTeams): ?>
            <div class="collapse mb-3" id="inviteToTeamPanel">
                <div class="p-3 border rounded bg-light">
                    <form method="post" action="<?= e(url('/student/team-invitations.php')) ?>" class="d-flex flex-wrap align-items-end gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="invited_user_id" value="<?= (int)$targetUserId ?>">
                        <div>
                            <label class="form-label small mb-1">Team</label>
                            <select name="team_id" class="form-select form-select-sm" required>
                                <?php foreach ($eligibleTeams as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label small mb-1">Message (optional)</label>
                            <input type="text" name="message" class="form-control form-control-sm" placeholder="Say hello and explain why you'd be a great fit together">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Send Invite</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- TWO COLUMN PROFILE CONTENT -->
        <div class="profile-content-grid">

            <!-- LEFT COLUMN -->
            <div class="profile-left-column">

                <!-- BIO -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>About</h2>
                            <p>Bio</p>
                        </div>
                    </div>
                    <p style="font-size:13px;color:#4b6073;"><?= $profile['bio'] ? nl2br(e($profile['bio'])) : '<span class="text-muted">No bio added yet.</span>' ?></p>
                </section>

                <!-- RESEARCH PROFILE -->
                <?php if ($visibility['research_visibility']): ?>
                    <section class="profile-section">
                        <div class="section-header">
                            <div>
                                <h2>Research Profile</h2>
                                <p>Research interests and statement.</p>
                            </div>
                        </div>
                        <?php if ($domainNames): ?>
                            <div class="research-tags">
                                <?php foreach ($domainNames as $dn): ?>
                                    <span class="research-tag"><?= e($dn) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small">No research interests added yet.</p>
                        <?php endif; ?>
                        <?php if (!empty($profile['research_statement'])): ?>
                            <p style="font-size:13px;color:#4b6073;margin-top:10px;"><?= nl2br(e($profile['research_statement'])) ?></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- SKILLS -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Skills</h2>
                            <p>Technical and research skills.</p>
                        </div>
                    </div>
                    <?php if (!$skillRows): ?>
                        <p class="text-muted small">No skills added yet.</p>
                    <?php else: ?>
                        <?php foreach ($skillRows as $sr): ?>
                            <div class="skill-row">
                                <div class="skill-name"><?= e($sr['name']) ?></div>
                                <div class="skill-progress">
                                    <div class="skill-progress-bar" style="width: <?= (int)($levelPct[$sr['level']] ?? 50) ?>%"></div>
                                </div>
                                <span class="skill-level" style="display:flex;align-items:center;justify-content:center;"><?= e($sr['level']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- EDUCATION -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Education</h2>
                            <p>Academic background.</p>
                        </div>
                    </div>
                    <?php if (!$educationRows): ?>
                        <div class="empty-state"><i class="bi bi-mortarboard"></i><p>No education added yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($educationRows as $ed): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <h3><?= e($ed['degree'] ?: 'Degree not specified') ?></h3>
                                    <span><?= e($ed['institution']) ?></span>
                                    <small><?= e(rp_date_range($ed['start_date'], $ed['end_date'])) ?></small>
                                    <?php if ($ed['field_of_study'] || $ed['description']): ?>
                                        <p><?= e($ed['field_of_study'] ? $ed['field_of_study'] . '. ' : '') . e($ed['description'] ?? '') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- WORK EXPERIENCE -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Work Experience</h2>
                            <p>Internship, job and professional experience.</p>
                        </div>
                    </div>
                    <?php if (!$workRows): ?>
                        <div class="empty-state"><i class="bi bi-briefcase"></i><p>No work experience added yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($workRows as $w): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <h3><?= e($w['position'] ?: 'Position not specified') ?></h3>
                                    <span><?= e($w['organization']) ?></span>
                                    <small><?= e(rp_date_range($w['start_date'], $w['end_date'], (bool)$w['is_current'])) ?></small>
                                    <?php if ($w['description']): ?><p><?= nl2br(e($w['description'])) ?></p><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- PROJECTS -->
                <?php if ($visibility['project_visibility']): ?>
                    <section class="profile-section">
                        <div class="section-header">
                            <div>
                                <h2>Projects</h2>
                                <p>Academic, research and personal projects.</p>
                            </div>
                        </div>
                        <?php if (!$projectRows): ?>
                            <p class="text-muted small">No projects added yet.</p>
                        <?php else: ?>
                            <?php foreach ($projectRows as $proj): ?>
                                <article class="project-card">
                                    <div class="project-card-top">
                                        <div>
                                            <h3><?= e($proj['title']) ?></h3>
                                            <span class="project-type"><?= e($proj['project_type'] ?: 'Project') ?></span>
                                        </div>
                                    </div>
                                    <?php if ($proj['description']): ?><p><?= nl2br(e($proj['description'])) ?></p><?php endif; ?>
                                    <div class="project-links">
                                        <?php $repoUrl = rp_safe_url($proj['repository_url'] ?? null); ?>
                                        <?php if ($repoUrl): ?>
                                            <a href="<?= e($repoUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i> Source Code</a>
                                        <?php endif; ?>
                                        <?php $demoUrl = rp_safe_url($proj['demo_url'] ?? null); ?>
                                        <?php if ($demoUrl): ?>
                                            <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> Live Project</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- PUBLICATIONS -->
                <?php if ($visibility['publication_visibility']): ?>
                    <section class="profile-section">
                        <div class="section-header">
                            <div>
                                <h2>Publications &amp; Papers</h2>
                                <p>Published and ongoing research work.</p>
                            </div>
                        </div>
                        <?php if (!$publicationRows): ?>
                            <p class="text-muted small">No publications added yet.</p>
                        <?php else: ?>
                            <?php foreach ($publicationRows as $pub): ?>
                                <article class="publication-card">
                                    <div class="publication-icon"><i class="bi bi-file-earmark-text"></i></div>
                                    <div class="publication-content">
                                        <h3><?= e($pub['title']) ?></h3>
                                        <?php if ($pub['authors']): ?><p class="publication-authors"><?= e($pub['authors']) ?></p><?php endif; ?>
                                        <p class="publication-venue"><?= e($pub['venue'] ?: 'Venue not specified') ?><?php if ($pub['publication_date']): ?> · <?= e(format_date($pub['publication_date'], 'Y')) ?><?php endif; ?></p>
                                        <span class="publication-status"><?= e($pub['status'] ?: 'In Preparation') ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="profile-right-column">

                <!-- CONTACT INFO -->
                <?php if ($visibility['contact_visibility']): ?>
                    <section class="profile-section compact-section">
                        <div class="section-header">
                            <h2>Contact Info</h2>
                        </div>
                        <div class="contact-list">
                            <?php if (!empty($profile['phone'])): ?>
                                <div><i class="bi bi-telephone-fill"></i><span><?= e($profile['phone']) ?></span></div>
                            <?php endif; ?>
                            <?php $linkedinUrl = rp_safe_url($profile['linkedin_url'] ?? null); ?>
                            <?php if ($linkedinUrl): ?>
                                <div><i class="bi bi-linkedin"></i><span><a href="<?= e($linkedinUrl) ?>" target="_blank" rel="noopener noreferrer">LinkedIn Profile</a></span></div>
                            <?php endif; ?>
                            <?php $githubUrl = rp_safe_url($profile['github_url'] ?? null); ?>
                            <?php if ($githubUrl): ?>
                                <div><i class="bi bi-github"></i><span><a href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer">GitHub Profile</a></span></div>
                            <?php endif; ?>
                            <?php $portfolioUrl = rp_safe_url($profile['portfolio_url'] ?? null); ?>
                            <?php if ($portfolioUrl): ?>
                                <div><i class="bi bi-globe2"></i><span><a href="<?= e($portfolioUrl) ?>" target="_blank" rel="noopener noreferrer">Portfolio</a></span></div>
                            <?php endif; ?>
                            <?php if (empty($profile['phone']) && empty($profile['linkedin_url']) && empty($profile['github_url']) && empty($profile['portfolio_url'])): ?>
                                <p class="text-muted small mb-0">No contact details shared yet.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- RESEARCH PREFERENCES -->
                <?php if ($visibility['research_visibility']): ?>
                    <section class="profile-section">
                        <div class="section-header">
                            <div>
                                <h2>Research Preferences</h2>
                                <p>Collaboration preferences.</p>
                            </div>
                        </div>
                        <?php if (!$preferences): ?>
                            <p class="text-muted small">No research preferences set yet.</p>
                        <?php else: ?>
                            <div class="preference-list">
                                <div class="preference-item">
                                    <span>Looking for Team</span>
                                    <strong class="<?= $preferences['looking_for_team'] ? 'preference-green' : '' ?>"><?= $preferences['looking_for_team'] ? 'Yes' : 'No' ?></strong>
                                </div>
                                <div class="preference-item">
                                    <span>Preferred Team Size</span>
                                    <strong><?= (int)$preferences['preferred_team_size_min'] ?> - <?= (int)$preferences['preferred_team_size_max'] ?> Members</strong>
                                </div>
                                <div class="preference-item">
                                    <span>Availability</span>
                                    <strong><?= $preferences['availability_hours_per_week'] ? (int)$preferences['availability_hours_per_week'] . ' hrs/week' : 'Not set' ?></strong>
                                </div>
                                <div class="preference-item">
                                    <span>Preferred Collaboration</span>
                                    <strong><?= e($preferences['collaboration_preference'] ?: 'Not set') ?></strong>
                                </div>
                                <div class="preference-item">
                                    <span>Preferred Project Type</span>
                                    <strong><?= e($preferences['project_type_preference'] ?: 'Not set') ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- LANGUAGES -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Languages</h2>
                            <p>Languages spoken.</p>
                        </div>
                    </div>
                    <?php if (!$languageRows): ?>
                        <p class="text-muted small">No languages added yet.</p>
                    <?php else: ?>
                        <?php foreach ($languageRows as $lg): ?>
                            <div class="language-row">
                                <div><strong><?= e($lg['name']) ?></strong></div>
                                <div class="language-level"><?= e($lg['proficiency']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- CERTIFICATIONS -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Certifications</h2>
                            <p>Professional and academic certifications.</p>
                        </div>
                    </div>
                    <?php if (!$certRows): ?>
                        <p class="text-muted small">No certifications added yet.</p>
                    <?php else: ?>
                        <?php foreach ($certRows as $c): ?>
                            <div class="certificate-item">
                                <div class="certificate-icon"><i class="bi bi-award-fill"></i></div>
                                <div>
                                    <strong><?= e($c['name']) ?></strong>
                                    <small><?= e($c['issuing_organization'] ?: 'Organization not specified') ?><?php if ($c['issue_date']): ?> · <?= e(format_date($c['issue_date'], 'Y')) ?><?php endif; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- ACHIEVEMENTS -->
                <section class="profile-section">
                    <div class="section-header">
                        <div>
                            <h2>Awards &amp; Achievements</h2>
                            <p>Competitions, awards and recognitions.</p>
                        </div>
                    </div>
                    <?php if (!$achievementRows): ?>
                        <p class="text-muted small">No achievements added yet.</p>
                    <?php else: ?>
                        <?php foreach ($achievementRows as $a): ?>
                            <div class="achievement-item">
                                <div class="achievement-icon"><i class="bi bi-trophy-fill"></i></div>
                                <div>
                                    <strong><?= e($a['title']) ?></strong>
                                    <small><?= e($a['organization'] ?: 'Organization not specified') ?><?php if ($a['achievement_date']): ?> · <?= e(format_date($a['achievement_date'], 'Y')) ?><?php endif; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
