<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$fpId   = (int)$facultyProfile['id'];

$completion = calculate_faculty_profile_completion($pdo, $userId);

// --- Research domains ------------------------------------------------
$stmt = $pdo->prepare('SELECT frd.domain_id, rd.name FROM faculty_research_domains frd JOIN research_domains rd ON rd.id = frd.domain_id WHERE frd.faculty_profile_id = ? ORDER BY rd.name');
$stmt->execute([$fpId]);
$myDomains   = $stmt->fetchAll();
$myDomainIds = array_map(fn($r) => (int)$r['domain_id'], $myDomains);
$availableDomains = array_filter(all_research_domains($pdo), fn($d) => !in_array((int)$d['id'], $myDomainIds, true));

// --- Skills / expertise ------------------------------------------------
$stmt = $pdo->prepare('SELECT fs.skill_id, s.name FROM faculty_skills fs JOIN skills s ON s.id = fs.skill_id WHERE fs.faculty_profile_id = ? ORDER BY s.name');
$stmt->execute([$fpId]);
$mySkills   = $stmt->fetchAll();
$mySkillIds = array_map(fn($r) => (int)$r['skill_id'], $mySkills);
$availableSkills = array_filter(all_skills($pdo), fn($s) => !in_array((int)$s['id'], $mySkillIds, true));

// --- Education -----------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM faculty_education WHERE faculty_profile_id = ? ORDER BY start_date DESC, id DESC');
$stmt->execute([$fpId]);
$educationRows = $stmt->fetchAll();

// --- Publications --------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM faculty_publications WHERE faculty_profile_id = ? ORDER BY publication_date DESC, id DESC');
$stmt->execute([$fpId]);
$publicationRows = $stmt->fetchAll();
$publicationStatuses = ['Published', 'Accepted', 'Under Review', 'In Preparation'];

// --- Projects ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM faculty_projects WHERE faculty_profile_id = ? ORDER BY start_date DESC, id DESC');
$stmt->execute([$fpId]);
$projectRows = $stmt->fetchAll();
$projectStatuses = ['Ongoing', 'Completed', 'Planned'];

// --- Availability (indexed by day) ----------------------------------------
$stmt = $pdo->prepare('SELECT * FROM faculty_availability WHERE faculty_profile_id = ?');
$stmt->execute([$fpId]);
$availabilityByDay = [];
foreach ($stmt->fetchAll() as $row) {
    $availabilityByDay[$row['day_of_week']] = $row;
}
$daysOfWeek = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// --- Preferences & visibility -----------------------------------------------
$prefs      = get_faculty_preferences($pdo, $fpId);
$visibility = get_faculty_visibility($pdo, $fpId);
$meetingOptions = ['Online', 'In Person', 'Online + In Person'];

// --- Activity ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$userId]);
$activityRows = $stmt->fetchAll();

$editUrl = url('/faculty/profile-edit.php');
$pageTitle = 'My Faculty Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Faculty Profile || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/profile.css">
    <style>
        .avail-day-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light,#eee)}
        .avail-day-row:last-child{border-bottom:none}
        .avail-day-row label.day-label{width:110px;font-weight:600;margin:0}
        .avail-day-row input[type=time]{max-width:120px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>

    <main class="profile-main">
        <?php render_flashes(); ?>

        <!-- COVER + PROFILE HEADER -->
        <section class="profile-header-card">
            <div class="profile-cover"<?php if (!empty($facultyProfile['cover_photo'])): ?> style="background-image:url('<?= e(url('/uploads/avatars/' . $facultyProfile['cover_photo'])) ?>');background-size:cover;background-position:center;"<?php endif; ?>>
                <button class="cover-edit-button" type="button" onclick="toggleForm('coverUploadForm')">
                    <i class="bi bi-pencil"></i> Edit Cover
                </button>
            </div>
            <div class="profile-header-content">
                <div class="profile-picture-container">
                    <?php if (!empty($facultyProfile['profile_photo'])): ?>
                        <img src="<?= e(url('/uploads/avatars/' . $facultyProfile['profile_photo'])) ?>" alt="Profile Picture" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture avatar-initials" style="font-size:32px;"><?= e(initials($currentUser['name'])) ?></div>
                    <?php endif; ?>
                    <button class="profile-photo-edit" type="button" title="Change profile photo" onclick="toggleForm('photoUploadForm')">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </div>

                <div class="profile-basic-info">
                    <div class="profile-name-row">
                        <h1><?= e($currentUser['name']) ?></h1>
                        <span class="profile-role">Faculty</span>
                    </div>
                    <p class="profile-department">
                        <?= e($facultyProfile['designation'] ?: 'Designation not set') ?>
                        <span>||</span>
                        <?= e($facultyProfile['department'] ?: 'Department not set') ?>
                    </p>
                    <p class="profile-university">
                        <i class="bi bi-building"></i>
                        United International University
                        <span>||</span>
                        <?= e($facultyProfile['office_location'] ?: 'Office not set') ?>
                    </p>
                    <div class="profile-stat-row">
                        <span><i class="bi bi-person-badge-fill"></i> Faculty ID <strong><?= e($facultyProfile['faculty_id'] ?: '—') ?></strong></span>
                        <?php if ($prefs && !empty($prefs['accepting_mentees'])): ?>
                            <span class="available-status"><i class="bi bi-check-circle-fill"></i> Accepting Mentees</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-header-actions">
                    <a href="#identity-section" class="btn-profile-primary"><i class="bi bi-pencil-fill"></i> Edit Profile</a>
                </div>
            </div>

            <div id="coverUploadForm" hidden style="padding:10px 20px;border-top:1px solid var(--border-light);">
                <form method="post" action="<?= e($editUrl) ?>" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_cover">
                    <input type="file" name="cover" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm" style="max-width:260px;" required>
                    <button type="submit" class="save-section-button">Upload Cover</button>
                </form>
            </div>
            <div id="photoUploadForm" hidden style="padding:10px 20px;border-top:1px solid var(--border-light);">
                <form method="post" action="<?= e($editUrl) ?>" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm" style="max-width:260px;" required>
                    <button type="submit" class="save-section-button">Upload Photo</button>
                </form>
            </div>

            <div class="profile-tabs">
                <a href="#identity-section" class="profile-tab active">Overview</a>
                <a href="#research-section" class="profile-tab">Research & Expertise</a>
                <a href="#projects-section" class="profile-tab">Projects</a>
                <a href="#publications-section" class="profile-tab">Publications</a>
                <a href="#mentorship-section" class="profile-tab">Mentorship</a>
                <a href="#activity-section" class="profile-tab">Activity</a>
            </div>
        </section>

        <section class="completion-card">
            <div class="completion-left">
                <div class="completion-circle"><span><?= $completion ?>%</span></div>
                <div>
                    <h3>Profile Completion</h3>
                    <p>A complete profile helps students find and trust you as an advisor.</p>
                </div>
            </div>
            <div class="completion-right">
                <div class="completion-progress"><div class="completion-progress-bar" style="width:<?= $completion ?>%"></div></div>
                <span><?= $completion >= 95 ? 'Profile Complete!' : ($completion >= 80 ? 'Almost There!' : 'Keep Going!') ?></span>
            </div>
        </section>

        <div class="profile-content-grid">
        <div class="profile-left-column">

        <!-- IDENTITY -->
        <section class="profile-section" id="identity-section">
            <div class="section-header">
                <div><h2>Profile Overview</h2><p>Bio and specialization shown to students.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleSection('identityForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <form id="identityForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_identity">
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" class="form-control profile-input" rows="4" disabled><?= e($facultyProfile['bio'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" class="form-control profile-input" value="<?= e($facultyProfile['specialization'] ?? '') ?>" placeholder="e.g. Artificial Intelligence, NLP" disabled>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- CONTACT -->
        <section class="profile-section">
            <div class="section-header">
                <div><h2>Contact & Links</h2><p>How students and colleagues can reach you.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleSection('contactForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <form id="contactForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_contact">
                <div class="form-row">
                    <div class="form-group">
                        <label>University Email <small class="text-muted">(Fixed)</small></label>
                        <input type="email" class="form-control profile-input always-disabled" value="<?= e($currentUser['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control profile-input" value="<?= e($facultyProfile['phone'] ?? '') ?>" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label>Office Location</label>
                    <input type="text" name="office_location" class="form-control profile-input" value="<?= e($facultyProfile['office_location'] ?? '') ?>" disabled>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>LinkedIn</label><input type="url" name="linkedin_url" class="form-control profile-input" value="<?= e($facultyProfile['linkedin_url'] ?? '') ?>" disabled></div>
                    <div class="form-group"><label>Google Scholar</label><input type="url" name="google_scholar_url" class="form-control profile-input" value="<?= e($facultyProfile['google_scholar_url'] ?? '') ?>" disabled></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>ResearchGate</label><input type="url" name="researchgate_url" class="form-control profile-input" value="<?= e($facultyProfile['researchgate_url'] ?? '') ?>" disabled></div>
                    <div class="form-group"><label>Portfolio / Personal Site</label><input type="url" name="portfolio_url" class="form-control profile-input" value="<?= e($facultyProfile['portfolio_url'] ?? '') ?>" disabled></div>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- ACADEMIC -->
        <section class="profile-section">
            <div class="section-header">
                <div><h2>Academic Position</h2><p>Department and designation.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleSection('academicForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <form id="academicForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_academic">
                <div class="form-row">
                    <div class="form-group"><label>Department</label><input type="text" name="department" class="form-control profile-input" value="<?= e($facultyProfile['department'] ?? '') ?>" disabled></div>
                    <div class="form-group"><label>Designation</label><input type="text" name="designation" class="form-control profile-input" value="<?= e($facultyProfile['designation'] ?? '') ?>" disabled></div>
                </div>
                <div class="form-group">
                    <label>Faculty ID <small class="text-muted">(Fixed — assigned at provisioning)</small></label>
                    <input type="text" class="form-control profile-input always-disabled" value="<?= e($facultyProfile['faculty_id'] ?? '') ?>" disabled>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- RESEARCH -->
        <section class="profile-section" id="research-section">
            <div class="section-header">
                <div><h2>Research Domains & Expertise</h2><p>What students will find when searching for advisors.</p></div>
            </div>

            <div class="form-group">
                <label>Research Domains</label>
                <div class="research-tags">
                    <?php foreach ($myDomains as $d): ?>
                        <form method="post" action="<?= e($editUrl) ?>" class="research-tag" style="border:1px solid #b7d3f5;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_domain">
                            <input type="hidden" name="domain_id" value="<?= (int)$d['domain_id'] ?>">
                            <?= e($d['name']) ?>
                            <button type="submit" title="Remove domain">×</button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (!$myDomains): ?><span class="text-muted small">No research domains added yet.</span><?php endif; ?>
                </div>
                <?php if ($availableDomains): ?>
                <form method="post" action="<?= e($editUrl) ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_domain">
                    <select name="domain_id" class="form-select form-select-sm" style="max-width:280px;" required>
                        <option value="">Add a research domain...</option>
                        <?php foreach ($availableDomains as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="add-item-button"><i class="bi bi-plus-lg"></i> Add</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Areas of Expertise</label>
                <div class="research-tags">
                    <?php foreach ($mySkills as $sk): ?>
                        <form method="post" action="<?= e($editUrl) ?>" class="research-tag" style="border:1px solid #b7d3f5;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_skill">
                            <input type="hidden" name="skill_id" value="<?= (int)$sk['skill_id'] ?>">
                            <?= e($sk['name']) ?>
                            <button type="submit" title="Remove">×</button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (!$mySkills): ?><span class="text-muted small">No expertise added yet.</span><?php endif; ?>
                </div>
                <?php if ($availableSkills): ?>
                <form method="post" action="<?= e($editUrl) ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_skill">
                    <select name="skill_id" class="form-select form-select-sm" style="max-width:220px;" required>
                        <option value="">Add expertise...</option>
                        <?php foreach ($availableSkills as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="add-item-button"><i class="bi bi-plus-lg"></i> Add</button>
                </form>
                <?php endif; ?>
            </div>

            <form id="researchForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_research_statement">
                <div class="section-header">
                    <div><h2 style="font-size:15px;">Research Statement</h2></div>
                    <button class="section-edit-button" type="button" onclick="toggleSection('researchForm')"><i class="bi bi-pencil"></i></button>
                </div>
                <div class="form-group">
                    <textarea name="research_statement" class="form-control profile-input" rows="4" placeholder="Describe your research focus and what kind of students/teams you like to advise..." disabled><?= e($facultyProfile['research_statement'] ?? '') ?></textarea>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- EDUCATION -->
        <section class="profile-section" id="education-section">
            <div class="section-header">
                <div><h2>Education</h2><p>Your academic background.</p></div>
                <button class="add-item-button" type="button" onclick="toggleForm('addEducationForm')"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
            <div id="addEducationForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_education">
                    <div class="form-row">
                        <div class="form-group"><label>Institution</label><input type="text" name="institution" class="form-control" required></div>
                        <div class="form-group"><label>Degree</label><input type="text" name="degree" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Field of Study</label><input type="text" name="field_of_study" class="form-control"></div>
                        <div class="form-group"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <button class="save-section-button" type="submit">Add Education</button>
                </form>
            </div>
            <?php if (!$educationRows): ?>
                <div class="empty-state"><i class="bi bi-mortarboard"></i><p>No education added yet.</p></div>
            <?php else: foreach ($educationRows as $edu): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                            <div>
                                <h3><?= e($edu['degree'] ?: 'Degree') ?><?= $edu['field_of_study'] ? ' — ' . e($edu['field_of_study']) : '' ?></h3>
                                <span><?= e($edu['institution']) ?></span>
                                <small><?= format_date($edu['start_date']) ?> - <?= $edu['end_date'] ? format_date($edu['end_date']) : 'Present' ?></small>
                            </div>
                            <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_education">
                                <input type="hidden" name="id" value="<?= (int)$edu['id'] ?>">
                                <button type="submit" class="skill-delete" onclick="return confirm('Remove this education entry?');"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                        <?php if ($edu['description']): ?><p><?= nl2br(e($edu['description'])) ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </section>

        <!-- PROJECTS -->
        <section class="profile-section" id="projects-section">
            <div class="section-header">
                <div><h2>Research Projects</h2><p>Ongoing and completed research projects.</p></div>
                <button class="add-item-button" type="button" onclick="toggleForm('addProjectForm')"><i class="bi bi-plus-lg"></i> Add Project</button>
            </div>
            <div id="addProjectForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_project">
                    <div class="form-row">
                        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                        <div class="form-group"><label>Project Type</label><input type="text" name="project_type" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>Funding Source</label><input type="text" name="funding_source" class="form-control"></div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($projectStatuses as $st): ?><option value="<?= e($st) ?>"><?= e($st) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Repository URL</label><input type="url" name="repository_url" class="form-control"></div>
                    <button class="save-section-button" type="submit">Add Project</button>
                </form>
            </div>
            <?php if (!$projectRows): ?>
                <div class="empty-state"><i class="bi bi-kanban"></i><p>No projects added yet.</p></div>
            <?php else: foreach ($projectRows as $p): ?>
                <article class="project-card">
                    <div class="project-card-top">
                        <div>
                            <h3><?= e($p['title']) ?></h3>
                            <span class="project-type"><?= e($p['project_type'] ?: $p['status']) ?></span>
                        </div>
                        <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_project">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="skill-delete" onclick="return confirm('Delete this project?');"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <?php if ($p['description']): ?><p><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
                    <div class="project-links">
                        <?php if ($p['repository_url']): ?><a href="<?= e($p['repository_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Repository</a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; endif; ?>
        </section>

        <!-- PUBLICATIONS -->
        <section class="profile-section" id="publications-section">
            <div class="section-header">
                <div><h2>Publications</h2><p>Journal articles, conference papers and more.</p></div>
                <button class="add-item-button" type="button" onclick="toggleForm('addPublicationForm')"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
            <div id="addPublicationForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_publication">
                    <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Authors</label><input type="text" name="authors" class="form-control"></div>
                        <div class="form-group"><label>Venue</label><input type="text" name="venue" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Type</label><input type="text" name="publication_type" class="form-control" placeholder="e.g. Journal Article"></div>
                        <div class="form-group"><label>Publication Date</label><input type="date" name="publication_date" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>DOI</label><input type="text" name="doi" class="form-control"></div>
                        <div class="form-group"><label>URL</label><input type="url" name="url" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Abstract</label><textarea name="abstract" class="form-control" rows="3"></textarea></div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <?php foreach ($publicationStatuses as $st): ?><option value="<?= e($st) ?>" <?= $st === 'Published' ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="save-section-button" type="submit">Add Publication</button>
                </form>
            </div>
            <?php if (!$publicationRows): ?>
                <div class="empty-state"><i class="bi bi-file-earmark-text"></i><p>No publications added yet.</p></div>
            <?php else: foreach ($publicationRows as $pub): ?>
                <article class="publication-card">
                    <div class="publication-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="publication-content">
                        <h3><?= e($pub['title']) ?></h3>
                        <?php if ($pub['authors']): ?><p class="publication-authors"><?= e($pub['authors']) ?></p><?php endif; ?>
                        <p class="publication-venue"><?= e($pub['venue'] ?: 'Venue not set') ?><?= $pub['publication_date'] ? ' · ' . format_date($pub['publication_date'], 'Y') : '' ?></p>
                        <span class="publication-status"><?= e($pub['status']) ?></span>
                    </div>
                    <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_publication">
                        <input type="hidden" name="id" value="<?= (int)$pub['id'] ?>">
                        <button type="submit" class="section-edit-button" onclick="return confirm('Delete this publication?');"><i class="bi bi-trash"></i></button>
                    </form>
                </article>
            <?php endforeach; endif; ?>
        </section>
        </div><!-- /.profile-left-column -->

        <!-- RIGHT COLUMN -->
        <div class="profile-right-column">

        <section class="profile-section compact-section">
            <div class="section-header"><h2>Contact Info</h2></div>
            <div class="contact-list">
                <div><i class="bi bi-envelope-fill"></i><span><?= e($currentUser['email']) ?></span></div>
                <div><i class="bi bi-telephone-fill"></i><span><?= e($facultyProfile['phone'] ?: 'Not set') ?></span></div>
                <div><i class="bi bi-geo-alt-fill"></i><span><?= e($facultyProfile['office_location'] ?: 'Not set') ?></span></div>
                <?php if ($facultyProfile['linkedin_url']): ?><div><i class="bi bi-linkedin"></i><span><a href="<?= e($facultyProfile['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn</a></span></div><?php endif; ?>
                <?php if ($facultyProfile['google_scholar_url']): ?><div><i class="bi bi-mortarboard"></i><span><a href="<?= e($facultyProfile['google_scholar_url']) ?>" target="_blank" rel="noopener">Google Scholar</a></span></div><?php endif; ?>
            </div>
        </section>

        <!-- MENTORSHIP PREFERENCES -->
        <section class="profile-section" id="mentorship-section">
            <div class="section-header">
                <div><h2>Mentorship & Capacity</h2><p>Controls advisor-request eligibility.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleForm('prefEditForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <div class="preference-list">
                <div class="preference-item"><span>Accepting Mentees</span><strong class="<?= ($prefs && $prefs['accepting_mentees']) ? 'preference-green' : '' ?>"><?= ($prefs && $prefs['accepting_mentees']) ? 'Yes' : 'No' ?></strong></div>
                <div class="preference-item"><span>Accepting Team Advisory</span><strong class="<?= ($prefs && $prefs['accepting_team_advisory']) ? 'preference-green' : '' ?>"><?= ($prefs && $prefs['accepting_team_advisory']) ? 'Yes' : 'No' ?></strong></div>
                <div class="preference-item"><span>Accepting Paper Advisory</span><strong class="<?= ($prefs && $prefs['accepting_paper_advisory']) ? 'preference-green' : '' ?>"><?= ($prefs && $prefs['accepting_paper_advisory']) ? 'Yes' : 'No' ?></strong></div>
                <div class="preference-item"><span>Max Active Mentees</span><strong><?= $prefs ? (int)$prefs['max_active_mentees'] : 5 ?> (<?= faculty_active_assignment_count($pdo, $userId) ?> active now)</strong></div>
                <div class="preference-item"><span>Meeting Preference</span><strong><?= $prefs && $prefs['meeting_preference'] ? e($prefs['meeting_preference']) : 'Not set' ?></strong></div>
            </div>
            <div id="prefEditForm" hidden style="margin-top:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_preferences">
                    <div class="form-group"><label><input type="checkbox" name="accepting_mentees" value="1" <?= (!$prefs || $prefs['accepting_mentees']) ? 'checked' : '' ?>> Accepting individual mentees</label></div>
                    <div class="form-group"><label><input type="checkbox" name="accepting_team_advisory" value="1" <?= (!$prefs || $prefs['accepting_team_advisory']) ? 'checked' : '' ?>> Accepting team advisory requests</label></div>
                    <div class="form-group"><label><input type="checkbox" name="accepting_paper_advisory" value="1" <?= (!$prefs || $prefs['accepting_paper_advisory']) ? 'checked' : '' ?>> Accepting paper/publication advisory requests</label></div>
                    <div class="form-group"><label>Max Active Mentees</label><input type="number" min="0" name="max_active_mentees" class="form-control" value="<?= $prefs ? (int)$prefs['max_active_mentees'] : 5 ?>"></div>
                    <div class="form-group">
                        <label>Meeting Preference</label>
                        <select name="meeting_preference" class="form-select">
                            <option value="">Select...</option>
                            <?php foreach ($meetingOptions as $mo): ?><option value="<?= e($mo) ?>" <?= ($prefs && $prefs['meeting_preference'] === $mo) ? 'selected' : '' ?>><?= e($mo) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Preferred Project Types</label><input type="text" name="preferred_project_types" class="form-control" value="<?= e($prefs['preferred_project_types'] ?? '') ?>" placeholder="e.g. FYDP, Research Papers"></div>
                    <div class="form-group"><label>Availability Note</label><textarea name="availability_note" class="form-control" rows="2"><?= e($prefs['availability_note'] ?? '') ?></textarea></div>
                    <button class="save-section-button" type="submit">Save Preferences</button>
                </form>
            </div>
        </section>

        <!-- AVAILABILITY -->
        <section class="profile-section">
            <div class="section-header">
                <div><h2>Weekly Availability</h2><p>Consultation hours.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleForm('availForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <?php if (!$availabilityByDay): ?>
                <p class="text-muted small mb-0">No availability set yet.</p>
            <?php else: foreach ($availabilityByDay as $day => $slot): ?>
                <div class="preference-item"><span><?= e($day) ?></span><strong><?= e(substr($slot['start_time'], 0, 5)) ?> - <?= e(substr($slot['end_time'], 0, 5)) ?></strong></div>
            <?php endforeach; endif; ?>
            <div id="availForm" hidden style="margin-top:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_availability">
                    <?php foreach ($daysOfWeek as $day): $key = strtolower($day); $slot = $availabilityByDay[$day] ?? null; ?>
                        <div class="avail-day-row">
                            <label class="day-label"><input type="checkbox" name="avail_<?= $key ?>" value="1" <?= $slot ? 'checked' : '' ?>> <?= e($day) ?></label>
                            <input type="time" name="avail_<?= $key ?>_start" value="<?= $slot ? e(substr($slot['start_time'], 0, 5)) : '10:00' ?>">
                            <span>to</span>
                            <input type="time" name="avail_<?= $key ?>_end" value="<?= $slot ? e(substr($slot['end_time'], 0, 5)) : '12:00' ?>">
                        </div>
                    <?php endforeach; ?>
                    <button class="save-section-button mt-2" type="submit">Save Availability</button>
                </form>
            </div>
        </section>

        <!-- VISIBILITY -->
        <section class="profile-section">
            <div class="section-header">
                <div><h2>Privacy & Visibility</h2><p>Controls what students can see.</p></div>
                <button class="section-edit-button" type="button" onclick="toggleForm('visForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <div class="preference-list">
                <div class="preference-item"><span>Profile Visibility</span><strong><?= e($visibility['profile_visibility']) ?></strong></div>
                <div class="preference-item"><span>Contact Info Visible</span><strong><?= $visibility['contact_visibility'] ? 'Yes' : 'No' ?></strong></div>
                <div class="preference-item"><span>Research Visible</span><strong><?= $visibility['research_visibility'] ? 'Yes' : 'No' ?></strong></div>
                <div class="preference-item"><span>Publications Visible</span><strong><?= $visibility['publication_visibility'] ? 'Yes' : 'No' ?></strong></div>
            </div>
            <div id="visForm" hidden style="margin-top:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_visibility">
                    <div class="form-group">
                        <label>Profile Visibility</label>
                        <select name="profile_visibility" class="form-select">
                            <?php foreach (['Public', 'Students Only', 'Private'] as $v): ?><option value="<?= e($v) ?>" <?= $visibility['profile_visibility'] === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="contact_visibility" value="1" <?= $visibility['contact_visibility'] ? 'checked' : '' ?>> Show contact info</label></div>
                    <div class="form-group"><label><input type="checkbox" name="research_visibility" value="1" <?= $visibility['research_visibility'] ? 'checked' : '' ?>> Show research domains/projects</label></div>
                    <div class="form-group"><label><input type="checkbox" name="project_visibility" value="1" <?= $visibility['project_visibility'] ? 'checked' : '' ?>> Show projects</label></div>
                    <div class="form-group"><label><input type="checkbox" name="publication_visibility" value="1" <?= $visibility['publication_visibility'] ? 'checked' : '' ?>> Show publications</label></div>
                    <button class="save-section-button" type="submit">Save Visibility</button>
                </form>
            </div>
        </section>

        <!-- ACTIVITY -->
        <section class="profile-section" id="activity-section">
            <div class="section-header"><h2>Recent Activity</h2></div>
            <?php if (!$activityRows): ?>
                <p class="text-muted small mb-0">No recent activity.</p>
            <?php else: foreach ($activityRows as $a): ?>
                <div class="recent-message">
                    <span class="user-avatar avatar-initials" style="width:32px;height:32px;font-size:11px;border-radius:50%;"><i class="bi bi-activity"></i></span>
                    <div class="message-content">
                        <h3><?= e(ucwords(str_replace('_', ' ', $a['activity_type'] ?? ''))) ?></h3>
                        <p><?= e($a['description']) ?></p>
                    </div>
                    <div class="message-meta"><span><?= e(time_ago($a['created_at'])) ?></span></div>
                </div>
            <?php endforeach; endif; ?>
        </section>
        </div><!-- /.profile-right-column -->
        </div><!-- /.profile-content-grid -->
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
function toggleSection(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    const inputs = form.querySelectorAll('input:not(.always-disabled), textarea:not(.always-disabled), select:not(.always-disabled)');
    const currentlyDisabled = Array.from(inputs).some(input => input.disabled);
    inputs.forEach(input => { input.disabled = !currentlyDisabled; });
    if (currentlyDisabled) {
        const first = form.querySelector('input:not(.always-disabled):not([type=hidden]), textarea:not(.always-disabled)');
        if (first) first.focus();
    }
}
function toggleForm(id) {
    const el = document.getElementById(id);
    if (el) el.hidden = !el.hidden;
}
document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.profile-tab').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
</body>
</html>
