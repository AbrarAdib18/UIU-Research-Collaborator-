<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];

$completion = calculate_profile_completion($pdo, $userId);
$studentProfile['profile_completion'] = $completion;

$nameParts = preg_split('/\s+/', trim($currentUser['name']), 2);
$firstName = $nameParts[0] ?? '';
$lastName  = $nameParts[1] ?? '';

// --- Research domains ------------------------------------------------
$stmt = $pdo->prepare('SELECT prd.domain_id, rd.name FROM profile_research_domains prd JOIN research_domains rd ON rd.id = prd.domain_id WHERE prd.profile_id = ? ORDER BY rd.name');
$stmt->execute([$profileId]);
$myDomains   = $stmt->fetchAll();
$myDomainIds = array_map(fn($r) => (int)$r['domain_id'], $myDomains);
$availableDomains = array_filter(all_research_domains($pdo), fn($d) => !in_array((int)$d['id'], $myDomainIds, true));

// --- Skills ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT ps.skill_id, ps.level, s.name FROM profile_skills ps JOIN skills s ON s.id = ps.skill_id WHERE ps.profile_id = ? ORDER BY s.name');
$stmt->execute([$profileId]);
$mySkills   = $stmt->fetchAll();
$mySkillIds = array_map(fn($r) => (int)$r['skill_id'], $mySkills);
$availableSkills = array_filter(all_skills($pdo), fn($s) => !in_array((int)$s['id'], $mySkillIds, true));
$skillLevelPct = ['Beginner' => 25, 'Intermediate' => 50, 'Advanced' => 75, 'Expert' => 100];
$skillLevels   = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];

// --- Languages -----------------------------------------------------------
$stmt = $pdo->prepare('SELECT pl.language_id, pl.proficiency, l.name FROM profile_languages pl JOIN languages l ON l.id = pl.language_id WHERE pl.profile_id = ? ORDER BY l.name');
$stmt->execute([$profileId]);
$myLanguages   = $stmt->fetchAll();
$myLanguageIds = array_map(fn($r) => (int)$r['language_id'], $myLanguages);
$availableLanguages = array_filter(all_languages($pdo), fn($l) => !in_array((int)$l['id'], $myLanguageIds, true));
$proficiencyLevels  = ['Basic', 'Conversational', 'Intermediate', 'Advanced', 'Fluent', 'Native'];

// --- Education -----------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM education WHERE profile_id = ? ORDER BY start_date DESC, id DESC');
$stmt->execute([$profileId]);
$educationRows = $stmt->fetchAll();

// --- Work experience -------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM work_experience WHERE profile_id = ? ORDER BY is_current DESC, start_date DESC, id DESC');
$stmt->execute([$profileId]);
$workRows = $stmt->fetchAll();
$employmentTypes = ['Full-time', 'Part-time', 'Internship', 'Research Assistant', 'Volunteer', 'Freelance'];

// --- Projects ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM projects WHERE profile_id = ? ORDER BY is_featured DESC, created_at DESC');
$stmt->execute([$profileId]);
$projectRows = $stmt->fetchAll();

// --- Publications --------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM publications WHERE profile_id = ? ORDER BY publication_date DESC, id DESC');
$stmt->execute([$profileId]);
$publicationRows = $stmt->fetchAll();
$publicationStatuses = ['Published', 'Accepted', 'Under Review', 'In Preparation'];

// --- Certifications --------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM certifications WHERE profile_id = ? ORDER BY issue_date DESC, id DESC');
$stmt->execute([$profileId]);
$certificationRows = $stmt->fetchAll();

// --- Achievements ----------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM achievements WHERE profile_id = ? ORDER BY achievement_date DESC, id DESC');
$stmt->execute([$profileId]);
$achievementRows = $stmt->fetchAll();

// --- Research preferences & visibility --------------------------------------
$prefs      = get_research_preferences($pdo, $profileId);
$visibility = get_profile_visibility($pdo, $profileId);
$collabPreferences = ['Online', 'In Person', 'Online + In Person'];

// --- Activity ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
$stmt->execute([$userId]);
$activityRows = $stmt->fetchAll();

$activityIconMap = [
    'login'           => 'bi-box-arrow-in-right',
    'profile_update'  => 'bi-pencil-fill',
    'team_join'       => 'bi-people-fill',
    'team_leave'      => 'bi-person-dash-fill',
];
function activity_icon(array $map, ?string $type): string
{
    return $map[$type] ?? 'bi-activity';
}

$programOptions = [
    'B.Sc. in Computer Science & Engineering',
    'M.Sc. in Computer Science & Engineering',
    'B.Sc. in Data Science',
    'M.Sc. in Data Science',
];

$editUrl = url('/student/profile-edit.php');
$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/profile.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="profile-main">
        <?php render_flashes(); ?>

        <!-- COVER + PROFILE HEADER -->
        <section class="profile-header-card">
            <div class="profile-cover"<?php if (!empty($studentProfile['cover_photo'])): ?> style="background-image:url('<?= e(url('/uploads/avatars/' . $studentProfile['cover_photo'])) ?>');background-size:cover;background-position:center;"<?php endif; ?>>
                <button class="cover-edit-button" type="button" onclick="toggleForm('coverUploadForm')">
                    <i class="bi bi-pencil"></i>
                    Edit Cover
                </button>
            </div>
            <div class="profile-header-content">
                <!-- Profile Image -->
                <div class="profile-picture-container">
                    <?php if (!empty($studentProfile['profile_photo'])): ?>
                        <img src="<?= e(url('/uploads/avatars/' . $studentProfile['profile_photo'])) ?>" alt="Profile Picture" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture avatar-initials" style="font-size:32px;"><?= e(initials($currentUser['name'])) ?></div>
                    <?php endif; ?>
                    <button class="profile-photo-edit" type="button" title="Change profile photo" onclick="toggleForm('photoUploadForm')">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </div>

                <!-- Basic Profile Information -->
                <div class="profile-basic-info">
                    <div class="profile-name-row">
                        <h1><?= e($currentUser['name']) ?></h1>
                        <span class="profile-role">Student</span>
                    </div>
                    <p class="profile-department">
                        <?= e($studentProfile['department'] ?: 'Department not set') ?>
                        <span>||</span>
                        <?= e($studentProfile['semester'] ?: 'Semester not set') ?>
                    </p>
                    <p class="profile-university">
                        <i class="bi bi-building"></i>
                        United International University
                        <span>||</span>
                        <?= e($studentProfile['location'] ?: 'Location not set') ?>
                    </p>
                    <div class="profile-stat-row">
                        <span>
                            <i class="bi bi-mortarboard-fill"></i>
                            CGPA
                            <strong><?= $studentProfile['cgpa'] !== null ? e(number_format((float)$studentProfile['cgpa'], 2)) : '—' ?></strong>
                        </span>
                        <span>
                            <i class="bi bi-calendar3"></i>
                            Student ID
                            <strong><?= e($studentProfile['student_id'] ?: '—') ?></strong>
                        </span>
                        <?php if ($prefs && !empty($prefs['looking_for_team'])): ?>
                            <span class="available-status">
                                <i class="bi bi-check-circle-fill"></i>
                                Available for Collaboration
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="profile-header-actions">
                    <a href="#personal-section" class="btn-profile-primary">
                        <i class="bi bi-pencil-fill"></i>
                        Edit Profile
                    </a>
                </div>
            </div>

            <!-- COVER / PHOTO UPLOAD FORMS -->
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

            <!-- PROFILE NAVIGATION -->
            <div class="profile-tabs">
                <a href="#personal-section" class="profile-tab active">Overview</a>
                <a href="#research-section" class="profile-tab">Research & Skills</a>
                <a href="#projects-section" class="profile-tab">Projects</a>
                <a href="#publications-section" class="profile-tab">Publications</a>
                <a href="#experience-section" class="profile-tab">Experience</a>
                <a href="#achievements-section" class="profile-tab">Achievements</a>
                <a href="#activity-section" class="profile-tab">Activity</a>
            </div>
        </section>

        <!-- PROFILE COMPLETION -->
        <section class="completion-card">
            <div class="completion-left">
                <div class="completion-circle"><span><?= $completion ?>%</span></div>
                <div>
                    <h3>Profile Completion</h3>
                    <p>Complete your profile to get better research team matches.</p>
                </div>
            </div>
            <div class="completion-right">
                <div class="completion-progress">
                    <div class="completion-progress-bar" style="width:<?= $completion ?>%"></div>
                </div>
                <span><?= $completion >= 95 ? 'Profile Complete!' : ($completion >= 80 ? 'Almost There!' : 'Keep Going!') ?></span>
            </div>
        </section>

        <!-- TWO COLUMN PROFILE CONTENT -->
        <div class="profile-content-grid">
        <div class="profile-left-column">

        <!-- PERSONAL INFORMATION -->
        <section class="profile-section" id="personal-section">
            <div class="section-header">
                <div>
                    <h2>Personal Information</h2>
                    <p>Basic information about yourself.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleSection('personalForm')">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <form id="personalForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_personal">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control profile-input" value="<?= e($firstName) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control profile-input" value="<?= e($lastName) ?>" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth <span class="coming-soon-badge">Coming Soon</span></label>
                        <input type="date" class="form-control profile-input always-disabled" disabled>
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="coming-soon-badge">Coming Soon</span></label>
                        <select class="form-select profile-input always-disabled" disabled>
                            <option selected>Select Gender</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Other</option>
                            <option>Prefer not to say</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>About Me</label>
                    <textarea name="bio" class="form-control profile-input" rows="4" disabled><?= e($studentProfile['bio'] ?? '') ?></textarea>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- CONTACT INFORMATION -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Contact Information</h2>
                    <p>Contact details for collaboration.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleSection('contactForm')">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <form id="contactForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_contact">
                <div class="form-row">
                    <div class="form-group">
                        <label>University Email</label>
                        <input type="email" class="form-control profile-input always-disabled" value="<?= e($currentUser['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control profile-input" value="<?= e($studentProfile['phone'] ?? '') ?>" placeholder="+880 1XXXXXXXXX" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control profile-input" value="<?= e($studentProfile['location'] ?? '') ?>" placeholder="Dhaka, Bangladesh" disabled>
                    </div>
                    <div class="form-group">
                        <label>Preferred Contact <span class="coming-soon-badge">Coming Soon</span></label>
                        <select class="form-select profile-input always-disabled" disabled>
                            <option>University Email</option>
                            <option>Phone</option>
                            <option>Platform Messages</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>LinkedIn Profile</label>
                    <input type="url" name="linkedin_url" class="form-control profile-input" value="<?= e($studentProfile['linkedin_url'] ?? '') ?>" placeholder="LinkedIn profile link" disabled>
                </div>
                <div class="form-group">
                    <label>GitHub Profile</label>
                    <input type="url" name="github_url" class="form-control profile-input" value="<?= e($studentProfile['github_url'] ?? '') ?>" placeholder="GitHub profile link" disabled>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- ACADEMIC INFORMATION -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Academic Information</h2>
                    <p>Your educational background.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleSection('academicForm')">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <form id="academicForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_academic">
                <div class="form-row">
                    <div class="form-group">
                        <label>University <span class="coming-soon-badge">Coming Soon</span></label>
                        <input type="text" class="form-control profile-input always-disabled" value="United International University" disabled>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control profile-input" value="<?= e($studentProfile['department'] ?? '') ?>" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Program</label>
                        <select name="program" class="form-select profile-input" disabled>
                            <option value="">Select Program</option>
                            <?php foreach ($programOptions as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= ($studentProfile['program'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Current Trimester</label>
                        <input type="text" name="semester" class="form-control profile-input" value="<?= e($studentProfile['semester'] ?? '') ?>" placeholder="e.g. 7th Trimester" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" class="form-control profile-input always-disabled" value="<?= e($studentProfile['student_id'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Current CGPA</label>
                        <input type="number" step="0.01" min="0" max="4" name="cgpa" class="form-control profile-input" value="<?= $studentProfile['cgpa'] !== null ? e((string)$studentProfile['cgpa']) : '' ?>" placeholder="0.00 - 4.00" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Expected Graduation <span class="coming-soon-badge">Coming Soon</span></label>
                        <input type="month" class="form-control profile-input always-disabled" disabled>
                    </div>
                    <div class="form-group">
                        <label>Academic Status <span class="coming-soon-badge">Coming Soon</span></label>
                        <select class="form-select profile-input always-disabled" disabled>
                            <option>Currently Studying</option>
                            <option>Graduated</option>
                            <option>On Leave</option>
                        </select>
                    </div>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- EDUCATION -->
        <section class="profile-section" id="education-section">
            <div class="section-header">
                <div>
                    <h2>Education</h2>
                    <p>Your academic history.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addEducationForm')">
                    <i class="bi bi-plus-lg"></i>
                    Add Education
                </button>
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
            <div id="educationContainer">
                <?php if (!$educationRows): ?>
                    <div class="empty-state">
                        <i class="bi bi-mortarboard"></i>
                        <p>No education added yet.</p>
                    </div>
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
                                <div style="display:flex;gap:4px;flex-shrink:0;">
                                    <button class="section-edit-button" type="button" onclick="toggleForm('editEdu<?= (int)$edu['id'] ?>')"><i class="bi bi-pencil"></i></button>
                                    <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_education">
                                        <input type="hidden" name="id" value="<?= (int)$edu['id'] ?>">
                                        <button type="submit" class="skill-delete" onclick="return confirm('Remove this education entry?');"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php if ($edu['description']): ?><p><?= nl2br(e($edu['description'])) ?></p><?php endif; ?>
                            <div id="editEdu<?= (int)$edu['id'] ?>" hidden style="margin-top:8px;">
                                <form method="post" action="<?= e($editUrl) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_education">
                                    <input type="hidden" name="id" value="<?= (int)$edu['id'] ?>">
                                    <div class="form-row">
                                        <div class="form-group"><label>Institution</label><input type="text" name="institution" class="form-control" value="<?= e($edu['institution']) ?>" required></div>
                                        <div class="form-group"><label>Degree</label><input type="text" name="degree" class="form-control" value="<?= e($edu['degree'] ?? '') ?>"></div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group"><label>Field of Study</label><input type="text" name="field_of_study" class="form-control" value="<?= e($edu['field_of_study'] ?? '') ?>"></div>
                                        <div class="form-group"></div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= e($edu['start_date'] ?? '') ?>"></div>
                                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= e($edu['end_date'] ?? '') ?>"></div>
                                    </div>
                                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($edu['description'] ?? '') ?></textarea></div>
                                    <button class="save-section-button" type="submit">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- RESEARCH PROFILE -->
        <section class="profile-section" id="research-section">
            <div class="section-header">
                <div>
                    <h2>Research Profile</h2>
                    <p>Tell other researchers what you want to work on.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleSection('researchForm')">
                    <i class="bi bi-pencil"></i>
                </button>
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
                        <?php foreach ($availableDomains as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="add-item-button"><i class="bi bi-plus-lg"></i> Add</button>
                </form>
                <?php endif; ?>
            </div>

            <form id="researchForm" class="section-form" method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_research_statement">
                <div class="form-group">
                    <label>Research Description</label>
                    <textarea name="research_statement" class="form-control profile-input" rows="4" placeholder="Describe your research interests, goals and areas you want to explore..." disabled><?= e($studentProfile['research_statement'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Research Methodologies <span class="coming-soon-badge">Coming Soon</span></label>
                    <div class="checkbox-grid">
                        <label><input type="checkbox" class="always-disabled" disabled> Experimental Research</label>
                        <label><input type="checkbox" class="always-disabled" disabled> Data Analysis</label>
                        <label><input type="checkbox" class="always-disabled" disabled> Machine Learning</label>
                        <label><input type="checkbox" class="always-disabled" disabled> System Development</label>
                        <label><input type="checkbox" class="always-disabled" disabled> Survey Research</label>
                        <label><input type="checkbox" class="always-disabled" disabled> Literature Review</label>
                    </div>
                </div>
                <button class="save-section-button" type="submit">Save Changes</button>
            </form>
        </section>

        <!-- SKILLS -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Skills</h2>
                    <p>Technical and research skills.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleForm('addSkillForm')">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div id="addSkillForm" hidden style="margin-bottom:10px;">
                <?php if ($availableSkills): ?>
                <form method="post" action="<?= e($editUrl) ?>" class="d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_skill">
                    <select name="skill_id" class="form-select form-select-sm" style="max-width:220px;" required>
                        <option value="">Select a skill...</option>
                        <?php foreach ($availableSkills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="level" class="form-select form-select-sm" style="max-width:150px;">
                        <?php foreach ($skillLevels as $lvl): ?>
                            <option value="<?= e($lvl) ?>" <?= $lvl === 'Beginner' ? 'selected' : '' ?>><?= e($lvl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="add-item-button"><i class="bi bi-plus-lg"></i> Add Skill</button>
                </form>
                <?php else: ?>
                    <p class="text-muted small mb-0">All available skills have been added.</p>
                <?php endif; ?>
            </div>
            <div id="skillsContainer">
                <?php if (!$mySkills): ?>
                    <div class="empty-state">
                        <i class="bi bi-stars"></i>
                        <p>No skills added yet.</p>
                    </div>
                <?php else: foreach ($mySkills as $sk): ?>
                    <div class="skill-row">
                        <div class="skill-name"><?= e($sk['name']) ?></div>
                        <div class="skill-progress">
                            <div class="skill-progress-bar" style="width:<?= (int)($skillLevelPct[$sk['level']] ?? 25) ?>%"></div>
                        </div>
                        <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_skill">
                            <input type="hidden" name="skill_id" value="<?= (int)$sk['skill_id'] ?>">
                            <select name="level" class="skill-level" onchange="this.form.submit()">
                                <?php foreach ($skillLevels as $lvl): ?>
                                    <option value="<?= e($lvl) ?>" <?= $sk['level'] === $lvl ? 'selected' : '' ?>><?= e($lvl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_skill">
                            <input type="hidden" name="skill_id" value="<?= (int)$sk['skill_id'] ?>">
                            <button class="skill-delete" type="submit"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- PROJECTS -->
        <section class="profile-section" id="projects-section">
            <div class="section-header">
                <div>
                    <h2>Projects</h2>
                    <p>Academic, research and personal projects.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addProjectForm')">
                    <i class="bi bi-plus-lg"></i>
                    Add Project
                </button>
            </div>
            <div id="addProjectForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_project">
                    <div class="form-row">
                        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                        <div class="form-group"><label>Project Type</label><input type="text" name="project_type" class="form-control" placeholder="e.g. Academic Project"></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Repository URL</label><input type="url" name="repository_url" class="form-control"></div>
                        <div class="form-group"><label>Demo URL</label><input type="url" name="demo_url" class="form-control"></div>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_featured" value="1"> Featured project</label>
                    </div>
                    <button class="save-section-button" type="submit">Add Project</button>
                </form>
            </div>
            <div id="projectsContainer">
                <?php if (!$projectRows): ?>
                    <div class="empty-state">
                        <i class="bi bi-kanban"></i>
                        <p>No projects added yet.</p>
                    </div>
                <?php else: foreach ($projectRows as $p): ?>
                    <article class="project-card">
                        <div class="project-card-top">
                            <div>
                                <h3><?= e($p['title']) ?></h3>
                                <span class="project-type"><?= e($p['project_type'] ?: 'Project') ?></span>
                            </div>
                            <div style="display:flex;gap:4px;flex-shrink:0;">
                                <button class="section-edit-button" type="button" onclick="toggleForm('editProj<?= (int)$p['id'] ?>')"><i class="bi bi-pencil"></i></button>
                                <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_project">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="skill-delete" onclick="return confirm('Delete this project?');"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php if ($p['description']): ?><p><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
                        <div class="project-links">
                            <?php if ($p['repository_url']): ?><a href="<?= e($p['repository_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-github"></i> Source Code</a><?php endif; ?>
                            <?php if ($p['demo_url']): ?><a href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Live Project</a><?php endif; ?>
                        </div>
                        <div id="editProj<?= (int)$p['id'] ?>" hidden style="margin-top:10px;">
                            <form method="post" action="<?= e($editUrl) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_project">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <div class="form-row">
                                    <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" value="<?= e($p['title']) ?>" required></div>
                                    <div class="form-group"><label>Project Type</label><input type="text" name="project_type" class="form-control" value="<?= e($p['project_type'] ?? '') ?>"></div>
                                </div>
                                <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($p['description'] ?? '') ?></textarea></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= e($p['start_date'] ?? '') ?>"></div>
                                    <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= e($p['end_date'] ?? '') ?>"></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Repository URL</label><input type="url" name="repository_url" class="form-control" value="<?= e($p['repository_url'] ?? '') ?>"></div>
                                    <div class="form-group"><label>Demo URL</label><input type="url" name="demo_url" class="form-control" value="<?= e($p['demo_url'] ?? '') ?>"></div>
                                </div>
                                <div class="form-group">
                                    <label><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?>> Featured project</label>
                                </div>
                                <button class="save-section-button" type="submit">Save Changes</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- PUBLICATIONS -->
        <section class="profile-section" id="publications-section">
            <div class="section-header">
                <div>
                    <h2>Publications & Papers</h2>
                    <p>Published and ongoing research work.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addPublicationForm')">
                    <i class="bi bi-plus-lg"></i>
                    Add Publication
                </button>
            </div>
            <div id="addPublicationForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_publication">
                    <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Authors</label><input type="text" name="authors" class="form-control" placeholder="Author Name, Co-author Name"></div>
                        <div class="form-group"><label>Venue</label><input type="text" name="venue" class="form-control" placeholder="Conference / Journal Name"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Type</label><input type="text" name="publication_type" class="form-control" placeholder="e.g. Conference Paper"></div>
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
                            <?php foreach ($publicationStatuses as $st): ?>
                                <option value="<?= e($st) ?>" <?= $st === 'In Preparation' ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="save-section-button" type="submit">Add Publication</button>
                </form>
            </div>
            <div id="publicationContainer">
                <?php if (!$publicationRows): ?>
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <p>No publications added yet.</p>
                    </div>
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
            </div>
        </section>
        </div><!-- /.profile-left-column -->

        <!-- RIGHT COLUMN -->
        <div class="profile-right-column">

        <!-- CONTACT SUMMARY -->
        <section class="profile-section compact-section">
            <div class="section-header">
                <h2>Contact Info</h2>
                <a href="#contactForm" class="section-edit-button"><i class="bi bi-pencil"></i></a>
            </div>
            <div class="contact-list">
                <div><i class="bi bi-envelope-fill"></i><span><?= e($currentUser['email']) ?></span></div>
                <div><i class="bi bi-telephone-fill"></i><span><?= e($studentProfile['phone'] ?: 'Not set') ?></span></div>
                <div><i class="bi bi-geo-alt-fill"></i><span><?= e($studentProfile['location'] ?: 'Not set') ?></span></div>
                <?php if ($studentProfile['linkedin_url']): ?><div><i class="bi bi-linkedin"></i><span><a href="<?= e($studentProfile['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn Profile</a></span></div><?php endif; ?>
                <?php if ($studentProfile['github_url']): ?><div><i class="bi bi-github"></i><span><a href="<?= e($studentProfile['github_url']) ?>" target="_blank" rel="noopener">GitHub Profile</a></span></div><?php endif; ?>
            </div>
        </section>

        <!-- RESEARCH PREFERENCES -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Research Preferences</h2>
                    <p>Help us find suitable research teammates.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleForm('prefEditForm')"><i class="bi bi-pencil"></i></button>
            </div>
            <div class="preference-list">
                <div class="preference-item">
                    <span>Looking for Team</span>
                    <strong class="<?= ($prefs && !empty($prefs['looking_for_team'])) ? 'preference-green' : '' ?>"><?= ($prefs && !empty($prefs['looking_for_team'])) ? 'Yes' : 'No' ?></strong>
                </div>
                <div class="preference-item">
                    <span>Preferred Team Size</span>
                    <strong><?= $prefs ? (int)$prefs['preferred_team_size_min'] . ' - ' . (int)$prefs['preferred_team_size_max'] . ' Members' : 'Not set' ?></strong>
                </div>
                <div class="preference-item">
                    <span>Availability</span>
                    <strong><?= $prefs && $prefs['availability_hours_per_week'] !== null ? (int)$prefs['availability_hours_per_week'] . ' hrs/week' : 'Not set' ?></strong>
                </div>
                <div class="preference-item">
                    <span>Preferred Collaboration</span>
                    <strong><?= $prefs && $prefs['collaboration_preference'] ? e($prefs['collaboration_preference']) : 'Not set' ?></strong>
                </div>
                <div class="preference-item">
                    <span>Preferred Project Type</span>
                    <strong><?= $prefs && $prefs['project_type_preference'] ? e($prefs['project_type_preference']) : 'Not set' ?></strong>
                </div>
            </div>
            <div id="prefEditForm" hidden style="margin-top:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_preferences">
                    <div class="form-group">
                        <label><input type="checkbox" name="looking_for_team" value="1" <?= ($prefs && !empty($prefs['looking_for_team'])) ? 'checked' : '' ?>> Looking for a team</label>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Min Team Size</label><input type="number" min="1" name="preferred_team_size_min" class="form-control" value="<?= $prefs ? (int)$prefs['preferred_team_size_min'] : 2 ?>"></div>
                        <div class="form-group"><label>Max Team Size</label><input type="number" min="1" name="preferred_team_size_max" class="form-control" value="<?= $prefs ? (int)$prefs['preferred_team_size_max'] : 4 ?>"></div>
                    </div>
                    <div class="form-group"><label>Availability (hrs/week)</label><input type="number" min="0" max="168" name="availability_hours_per_week" class="form-control" value="<?= $prefs && $prefs['availability_hours_per_week'] !== null ? (int)$prefs['availability_hours_per_week'] : '' ?>"></div>
                    <div class="form-group">
                        <label>Collaboration Preference</label>
                        <select name="collaboration_preference" class="form-select">
                            <option value="">Select...</option>
                            <?php foreach ($collabPreferences as $cp): ?>
                                <option value="<?= e($cp) ?>" <?= ($prefs && $prefs['collaboration_preference'] === $cp) ? 'selected' : '' ?>><?= e($cp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Preferred Project Type</label><input type="text" name="project_type_preference" class="form-control" value="<?= e($prefs['project_type_preference'] ?? '') ?>" placeholder="e.g. Research Project"></div>
                    <button class="save-section-button" type="submit">Save Preferences</button>
                </form>
            </div>
        </section>

        <!-- LANGUAGES -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Languages</h2>
                    <p>Languages you can communicate in.</p>
                </div>
                <button class="section-edit-button" type="button" onclick="toggleForm('addLanguageForm')"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div id="addLanguageForm" hidden style="margin-bottom:10px;">
                <?php if ($availableLanguages): ?>
                <form method="post" action="<?= e($editUrl) ?>" class="d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_language">
                    <select name="language_id" class="form-select form-select-sm" style="max-width:150px;" required>
                        <option value="">Language...</option>
                        <?php foreach ($availableLanguages as $l): ?>
                            <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="proficiency" class="form-select form-select-sm" style="max-width:150px;">
                        <?php foreach ($proficiencyLevels as $pl): ?>
                            <option value="<?= e($pl) ?>" <?= $pl === 'Intermediate' ? 'selected' : '' ?>><?= e($pl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="add-item-button"><i class="bi bi-plus-lg"></i></button>
                </form>
                <?php else: ?>
                    <p class="text-muted small mb-0">All languages have been added.</p>
                <?php endif; ?>
            </div>
            <div id="languageContainer">
                <?php if (!$myLanguages): ?>
                    <div class="empty-state"><i class="bi bi-translate"></i><p>No languages added yet.</p></div>
                <?php else: foreach ($myLanguages as $lg): ?>
                    <div class="language-row">
                        <div>
                            <strong><?= e($lg['name']) ?></strong>
                            <small><?= e($lg['proficiency']) ?></small>
                        </div>
                        <div class="language-level"><?= e($lg['proficiency']) ?></div>
                        <form method="post" action="<?= e($editUrl) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_language">
                            <input type="hidden" name="language_id" value="<?= (int)$lg['language_id'] ?>">
                            <button type="submit" class="skill-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- CERTIFICATIONS -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Certifications</h2>
                    <p>Professional and academic certifications.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addCertificationForm')"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
            <div id="addCertificationForm" hidden style="margin-bottom:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_certification">
                    <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label>Issuing Organization</label><input type="text" name="issuing_organization" class="form-control"></div>
                    <div class="form-row">
                        <div class="form-group"><label>Issue Date</label><input type="date" name="issue_date" class="form-control"></div>
                        <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Credential ID</label><input type="text" name="credential_id" class="form-control"></div>
                        <div class="form-group"><label>Credential URL</label><input type="url" name="credential_url" class="form-control"></div>
                    </div>
                    <button class="save-section-button" type="submit">Add Certification</button>
                </form>
            </div>
            <div id="certificationContainer">
                <?php if (!$certificationRows): ?>
                    <div class="empty-state"><i class="bi bi-award"></i><p>No certifications added yet.</p></div>
                <?php else: foreach ($certificationRows as $c): ?>
                    <div class="certificate-item">
                        <div class="certificate-icon"><i class="bi bi-award-fill"></i></div>
                        <div style="flex:1;min-width:0;">
                            <strong><?= e($c['name']) ?></strong>
                            <small><?= e($c['issuing_organization'] ?: 'Organization not set') ?><?= $c['issue_date'] ? ' · ' . format_date($c['issue_date'], 'Y') : '' ?></small>
                        </div>
                        <form method="post" action="<?= e($editUrl) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_certification">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="skill-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- AWARDS & ACHIEVEMENTS -->
        <section class="profile-section" id="achievements-section">
            <div class="section-header">
                <div>
                    <h2>Awards & Achievements</h2>
                    <p>Competitions, awards and recognitions.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addAchievementForm')"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
            <div id="addAchievementForm" hidden style="margin-bottom:10px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_achievement">
                    <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Organization</label><input type="text" name="organization" class="form-control"></div>
                        <div class="form-group"><label>Date</label><input type="date" name="achievement_date" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <button class="save-section-button" type="submit">Add Achievement</button>
                </form>
            </div>
            <div id="achievementContainer">
                <?php if (!$achievementRows): ?>
                    <div class="empty-state"><i class="bi bi-trophy"></i><p>No achievements added yet.</p></div>
                <?php else: foreach ($achievementRows as $a): ?>
                    <div class="achievement-item">
                        <div class="achievement-icon"><i class="bi bi-trophy-fill"></i></div>
                        <div style="flex:1;min-width:0;">
                            <strong><?= e($a['title']) ?></strong>
                            <small><?= e($a['organization'] ?: 'Organization not set') ?><?= $a['achievement_date'] ? ' · ' . format_date($a['achievement_date'], 'Y') : '' ?></small>
                        </div>
                        <form method="post" action="<?= e($editUrl) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_achievement">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="skill-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- WORK EXPERIENCE (also serves as the Experience tab anchor; see README note on the
             removed "Research Experience" section, which duplicated this with no DB backing) -->
        <section class="profile-section" id="experience-section">
            <div class="section-header">
                <div>
                    <h2>Work Experience</h2>
                    <p>Internship, job and professional experience.</p>
                </div>
                <button class="add-item-button" type="button" onclick="toggleForm('addWorkForm')">
                    <i class="bi bi-plus-lg"></i>
                    Add Experience
                </button>
            </div>
            <div id="addWorkForm" hidden style="margin-bottom:12px;">
                <form method="post" action="<?= e($editUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_work">
                    <div class="form-row">
                        <div class="form-group"><label>Organization</label><input type="text" name="organization" class="form-control" required></div>
                        <div class="form-group"><label>Position</label><input type="text" name="position" class="form-control"></div>
                    </div>
                    <div class="form-group">
                        <label>Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <option value="">Select...</option>
                            <?php foreach ($employmentTypes as $et): ?><option value="<?= e($et) ?>"><?= e($et) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="is_current" value="1"> I currently work here</label></div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <button class="save-section-button" type="submit">Add Experience</button>
                </form>
            </div>
            <div id="workContainer">
                <?php if (!$workRows): ?>
                    <div class="empty-state">
                        <i class="bi bi-briefcase"></i>
                        <p>No work experience added yet.</p>
                        <button type="button" onclick="toggleForm('addWorkForm')">Add Work Experience</button>
                    </div>
                <?php else: foreach ($workRows as $w): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                                <div>
                                    <h3><?= e($w['position'] ?: 'Position') ?></h3>
                                    <span><?= e($w['organization']) ?><?= $w['employment_type'] ? ' · ' . e($w['employment_type']) : '' ?></span>
                                    <small><?= format_date($w['start_date']) ?> - <?= !empty($w['is_current']) ? 'Present' : ($w['end_date'] ? format_date($w['end_date']) : 'N/A') ?></small>
                                </div>
                                <div style="display:flex;gap:4px;flex-shrink:0;">
                                    <button class="section-edit-button" type="button" onclick="toggleForm('editWork<?= (int)$w['id'] ?>')"><i class="bi bi-pencil"></i></button>
                                    <form method="post" action="<?= e($editUrl) ?>" style="display:contents">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_work">
                                        <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                        <button type="submit" class="skill-delete" onclick="return confirm('Remove this work experience?');"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php if ($w['description']): ?><p><?= nl2br(e($w['description'])) ?></p><?php endif; ?>
                            <div id="editWork<?= (int)$w['id'] ?>" hidden style="margin-top:8px;">
                                <form method="post" action="<?= e($editUrl) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_work">
                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                    <div class="form-row">
                                        <div class="form-group"><label>Organization</label><input type="text" name="organization" class="form-control" value="<?= e($w['organization']) ?>" required></div>
                                        <div class="form-group"><label>Position</label><input type="text" name="position" class="form-control" value="<?= e($w['position'] ?? '') ?>"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>Employment Type</label>
                                        <select name="employment_type" class="form-select">
                                            <option value="">Select...</option>
                                            <?php foreach ($employmentTypes as $et): ?><option value="<?= e($et) ?>" <?= $w['employment_type'] === $et ? 'selected' : '' ?>><?= e($et) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= e($w['start_date'] ?? '') ?>"></div>
                                        <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= e($w['end_date'] ?? '') ?>"></div>
                                    </div>
                                    <div class="form-group"><label><input type="checkbox" name="is_current" value="1" <?= !empty($w['is_current']) ? 'checked' : '' ?>> I currently work here</label></div>
                                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($w['description'] ?? '') ?></textarea></div>
                                    <button class="save-section-button" type="submit">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- EXTRACURRICULAR ACTIVITIES -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Extracurricular Activities <span class="coming-soon-badge">Coming Soon</span></h2>
                    <p>Clubs, volunteering and student activities.</p>
                </div>
                <button class="add-item-button" type="button" disabled title="Coming soon">
                    <i class="bi bi-plus-lg"></i>
                    Add
                </button>
            </div>
            <textarea class="form-control" rows="4" placeholder="Describe your extracurricular activities..." disabled></textarea>
        </section>

        <!-- CV / RESUME -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>CV / Resume</h2>
                    <p>Upload your latest CV or resume.</p>
                </div>
            </div>
            <div class="cv-upload-area">
                <i class="bi bi-file-earmark-pdf"></i>
                <?php if (!empty($studentProfile['cv_path'])): ?>
                    <h3>CV Uploaded</h3>
                    <p><a href="<?= e(url('/uploads/cv/' . $studentProfile['cv_path'])) ?>" target="_blank" rel="noopener">Download current CV</a></p>
                <?php else: ?>
                    <h3>Upload CV</h3>
                    <p>PDF, DOC or DOCX — max 5MB</p>
                <?php endif; ?>
                <form method="post" action="<?= e($editUrl) ?>" enctype="multipart/form-data" style="display:contents">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_cv">
                    <label class="cv-upload-button">
                        <i class="bi bi-upload"></i>
                        Choose File
                        <input type="file" name="cv" accept=".pdf,.doc,.docx" hidden onchange="this.form.requestSubmit()">
                    </label>
                </form>
            </div>
        </section>

        <!-- AVAILABILITY -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Availability <span class="coming-soon-badge">Coming Soon</span></h2>
                    <p>When are you available for research collaboration?</p>
                </div>
            </div>
            <p class="text-muted small">Set your weekly availability in Research Preferences above — detailed day/time scheduling is coming soon.</p>
            <div class="availability-grid">
                <label><input type="checkbox" class="always-disabled" disabled> Saturday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Sunday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Monday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Tuesday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Wednesday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Thursday</label>
                <label><input type="checkbox" class="always-disabled" disabled> Friday</label>
            </div>
        </section>

        <!-- PROFILE VISIBILITY -->
        <section class="profile-section">
            <div class="section-header">
                <div>
                    <h2>Profile Visibility</h2>
                    <p>Control who can view your profile.</p>
                </div>
            </div>
            <form method="post" action="<?= e($editUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_visibility">
                <div class="visibility-options">
                    <label class="visibility-option">
                        <input type="radio" name="profile_visibility" value="Public" onchange="this.form.submit()" <?= $visibility['profile_visibility'] === 'Public' ? 'checked' : '' ?>>
                        <div><strong>Public</strong><small>Visible to all registered researchers.</small></div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="profile_visibility" value="Students Only" onchange="this.form.submit()" <?= $visibility['profile_visibility'] === 'Students Only' ? 'checked' : '' ?>>
                        <div><strong>University Only</strong><small>Visible only to UIU users.</small></div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="profile_visibility" value="Private" onchange="this.form.submit()" <?= $visibility['profile_visibility'] === 'Private' ? 'checked' : '' ?>>
                        <div><strong>Private</strong><small>Only you can view your profile.</small></div>
                    </label>
                </div>
            </form>
            <p class="text-muted small mt-2 mb-0">Manage contact / research / project / publication visibility in <a href="<?= e(url('/student/settings.php')) ?>">Settings</a>.</p>
        </section>

        <!-- PROFILE ACTIONS -->
        <section class="profile-actions-card">
            <a href="<?= e(url('/student/researcher-profile.php?id=' . $userId)) ?>" class="preview-profile-button">
                <i class="bi bi-eye"></i>
                Preview Profile
            </a>
            <a href="<?= e(url('/student/dashboard.php')) ?>" class="cancel-profile-button">
                Cancel
            </a>
        </section>
        </div><!-- /.profile-right-column -->
        </div><!-- /.profile-content-grid -->

        <!-- ACTIVITY SECTION -->
        <section class="profile-section full-width-section" id="activity-section">
            <div class="section-header">
                <div>
                    <h2>Recent Activity</h2>
                    <p>Your recent research collaboration activity.</p>
                </div>
            </div>
            <div class="activity-list">
                <?php if (!$activityRows): ?>
                    <div class="empty-state"><i class="bi bi-clock-history"></i><p>No recent activity yet.</p></div>
                <?php else: foreach ($activityRows as $act): ?>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="bi <?= e(activity_icon($activityIconMap, $act['activity_type'])) ?>"></i></div>
                        <div>
                            <strong><?= e($act['description'] ?: ucwords(str_replace('_', ' ', (string)$act['activity_type']))) ?></strong>
                            <small><?= e(time_ago($act['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
// Toggle disabled state for the always-visible section-forms (Personal / Contact / Academic / Research).
// Inputs marked .always-disabled never get re-enabled (no DB column backs them).
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

// Generic show/hide for add/edit inline forms (skills, education, projects, etc.).
function toggleForm(id) {
    const el = document.getElementById(id);
    if (el) el.hidden = !el.hidden;
}

// PROFILE TAB ACTIVE STATE
document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.profile-tab').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
</body>
</html>
