<?php
/**
 * Shared helper functions used across the whole app.
 * Loaded once via includes/bootstrap.php.
 */

// ---------------------------------------------------------------------
// Output / URL helpers
// ---------------------------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** URL path prefix the app is installed under (handles htdocs subfolders). */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $root    = str_replace('\\', '/', rtrim(dirname(__DIR__), '/\\'));
        $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
        $base    = ($docRoot !== '' && strpos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
    }
    return $base;
}

/** Build an app-root-relative URL, e.g. url('/student/dashboard.php'). */
function url(string $path): string
{
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return base_url() . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Redirect back to the referring page, falling back to $default. */
function redirect_back(string $default = '/student/dashboard.php'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && strpos($ref, base_url()) !== false) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($default);
}

// ---------------------------------------------------------------------
// Old-input helper (repopulate forms after a validation failure)
// ---------------------------------------------------------------------

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function old(string $key, $default = '')
{
    static $snapshot = null;
    if ($snapshot === null) {
        $snapshot = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
    }
    return $snapshot[$key] ?? $default;
}

function nullable_trim($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

/** Returns a positive integer id from a raw GET/POST value, or null if it isn't one. */
function validate_id($raw): ?int
{
    if (!is_numeric($raw)) {
        return null;
    }
    $id = (int)$raw;
    return $id > 0 ? $id : null;
}

/**
 * Returns a http(s) URL only if it is well-formed, else null. Rejects
 * javascript:/data:/vbscript: and other dangerous schemes that would
 * otherwise be stored and later echoed into an href="" attribute.
 */
function is_valid_http_url($raw): ?string
{
    $v = trim((string)$raw);
    if ($v === '') {
        return null;
    }
    if (!preg_match('~^https?://~i', $v) || !filter_var($v, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $v;
}

// ---------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('M j, Y', $ts);
}

function format_date(?string $date, string $fmt = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

function initials(string $name): string
{
    $parts = array_filter(preg_split('/\s+/', trim($name)));
    if (!$parts) {
        return '?';
    }
    $parts = array_values($parts);
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------

function current_page(): int
{
    $p = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    return $p > 0 ? $p : 1;
}

function paginate_offset(int $page, int $perPage): int
{
    return ($page - 1) * $perPage;
}

/** Build a pagination link preserving current query params except page. */
function page_url(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// ---------------------------------------------------------------------
// Lookup lists (cached per request)
// ---------------------------------------------------------------------

function all_research_domains(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM research_domains ORDER BY name')->fetchAll();
    }
    return $cache;
}

function all_skills(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM skills ORDER BY name')->fetchAll();
    }
    return $cache;
}

function all_languages(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM languages ORDER BY name')->fetchAll();
    }
    return $cache;
}

// ---------------------------------------------------------------------
// Student profile helpers
// ---------------------------------------------------------------------

function get_student_profile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_profile_domain_ids(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare('SELECT domain_id FROM profile_research_domains WHERE profile_id = ?');
    $stmt->execute([$profileId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_profile_skill_ids(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare('SELECT skill_id FROM profile_skills WHERE profile_id = ?');
    $stmt->execute([$profileId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_research_preferences(PDO $pdo, int $profileId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM research_preferences WHERE profile_id = ? LIMIT 1');
    $stmt->execute([$profileId]);
    return $stmt->fetch() ?: null;
}

function get_profile_visibility(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare('SELECT * FROM profile_visibility WHERE profile_id = ? LIMIT 1');
    $stmt->execute([$profileId]);
    $row = $stmt->fetch();
    return $row ?: [
        'profile_visibility'     => 'Students Only',
        'contact_visibility'     => 1,
        'research_visibility'    => 1,
        'project_visibility'     => 1,
        'publication_visibility' => 1,
    ];
}

/**
 * Recomputes and persists profile_completion for a student.
 * Weighted sections: bio 10, contact 10, academics 15, domains 15,
 * skills 15, education 10, projects 10, preferences 10, photo 5 = 100.
 */
function calculate_profile_completion(PDO $pdo, int $userId): int
{
    $profile = get_student_profile($pdo, $userId);
    if (!$profile) {
        return 0;
    }
    $profileId = (int)$profile['id'];
    $score = 0;

    if (nullable_trim($profile['bio'] ?? '') !== null) {
        $score += 10;
    }
    if (nullable_trim($profile['phone'] ?? '') !== null && nullable_trim($profile['location'] ?? '') !== null) {
        $score += 10;
    }
    if (!empty($profile['department']) && !empty($profile['program']) && !empty($profile['semester'])) {
        $score += 15;
    }
    if (count(get_profile_domain_ids($pdo, $profileId)) > 0) {
        $score += 15;
    }
    if (count(get_profile_skill_ids($pdo, $profileId)) > 0) {
        $score += 15;
    }

    $eduStmt = $pdo->prepare('SELECT COUNT(*) FROM education WHERE profile_id = ?');
    $eduStmt->execute([$profileId]);
    if ((int)$eduStmt->fetchColumn() > 0) {
        $score += 10;
    }

    $projStmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE profile_id = ?');
    $projStmt->execute([$profileId]);
    if ((int)$projStmt->fetchColumn() > 0) {
        $score += 10;
    }

    if (get_research_preferences($pdo, $profileId) !== null) {
        $score += 10;
    }
    if (!empty($profile['profile_photo'])) {
        $score += 5;
    }

    $score = (int)min(100, $score);

    $upd = $pdo->prepare('UPDATE student_profiles SET profile_completion = ? WHERE id = ?');
    $upd->execute([$score, $profileId]);

    return $score;
}

function extract_semester_number($semester): ?int
{
    if (!$semester) {
        return null;
    }
    if (preg_match('/(\d+)/', (string)$semester, $m)) {
        return (int)$m[1];
    }
    return null;
}

/**
 * Research Connect match score (0-100). See README "Matching Formula".
 * Weights: shared domains 40, shared skills 30, dept/program 15,
 * academic-level closeness 10, mutual availability 5.
 */
function calculate_match_score(PDO $pdo, array $viewer, array $candidate): int
{
    $viewerDomains    = get_profile_domain_ids($pdo, (int)$viewer['id']);
    $candidateDomains = get_profile_domain_ids($pdo, (int)$candidate['id']);
    $sharedDomains    = count(array_intersect($viewerDomains, $candidateDomains));
    $domainScore      = $viewerDomains ? min(40, round(($sharedDomains / count($viewerDomains)) * 40)) : 0;

    $viewerSkills    = get_profile_skill_ids($pdo, (int)$viewer['id']);
    $candidateSkills = get_profile_skill_ids($pdo, (int)$candidate['id']);
    $sharedSkills    = count(array_intersect($viewerSkills, $candidateSkills));
    $skillScore      = $viewerSkills ? min(30, round(($sharedSkills / count($viewerSkills)) * 30)) : 0;

    $deptProgramScore = 0;
    if (!empty($viewer['department']) && !empty($candidate['department'])
        && strcasecmp((string)$viewer['department'], (string)$candidate['department']) === 0) {
        $deptProgramScore += 10;
        if (!empty($viewer['program']) && !empty($candidate['program'])
            && strcasecmp((string)$viewer['program'], (string)$candidate['program']) === 0) {
            $deptProgramScore += 5;
        }
    }

    $viewerSem    = extract_semester_number($viewer['semester'] ?? null);
    $candidateSem = extract_semester_number($candidate['semester'] ?? null);
    $levelScore   = 0;
    if ($viewerSem !== null && $candidateSem !== null) {
        $diff       = abs($viewerSem - $candidateSem);
        $levelScore = $diff <= 1 ? 10 : ($diff <= 3 ? 5 : 0);
    }

    $availScore   = 0;
    $viewerPref   = get_research_preferences($pdo, (int)$viewer['id']);
    $candidatePref = get_research_preferences($pdo, (int)$candidate['id']);
    if (!empty($viewerPref['looking_for_team']) && !empty($candidatePref['looking_for_team'])) {
        $availScore = 5;
    }

    return (int)min(100, $domainScore + $skillScore + $deptProgramScore + $levelScore + $availScore);
}

// ---------------------------------------------------------------------
// Notifications & activity log
// ---------------------------------------------------------------------

function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $relatedType = null, ?int $relatedId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_type, related_id) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$userId, $type, $title, $message, $relatedType, $relatedId]);
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function notification_link(?string $relatedType, ?int $relatedId, string $role = 'student'): string
{
    if ($role === 'faculty') {
        switch ($relatedType) {
            case 'opportunity_application':
                return $relatedId ? url('/faculty/application-details.php?id=' . $relatedId) : url('/faculty/applications.php');
            case 'opportunity':
                return $relatedId ? url('/faculty/opportunity-details.php?id=' . $relatedId) : url('/faculty/opportunities.php');
            case 'advisor_request':
                return $relatedId ? url('/faculty/mentorship-request-details.php?id=' . $relatedId) : url('/faculty/mentorship-requests.php');
            case 'advisor_assignment':
                return url('/faculty/advised-students.php');
            case 'connection_request':
                return url('/faculty/faculty-connections.php');
            case 'direct_message':
                return $relatedId ? url('/faculty/conversation.php?user=' . $relatedId) : url('/faculty/messages.php');
            case 'team':
            case 'team_message':
            case 'team_milestone':
            case 'team_task':
                return $relatedId ? url('/faculty/advised-teams.php?team_id=' . $relatedId) : url('/faculty/advised-teams.php');
            case 'community':
            case 'community_post':
                return $relatedId ? url('/faculty/community-details.php?id=' . $relatedId) : url('/faculty/communities.php');
            default:
                return url('/faculty/notifications.php');
        }
    }

    switch ($relatedType) {
        case 'opportunity_application':
            return url('/student/saved-items.php');
        case 'opportunity':
            return $relatedId ? url('/student/opportunity-details.php?id=' . $relatedId) : url('/student/opportunities.php');
        case 'team_invitation':
            return url('/student/team-invitations.php');
        case 'team_request':
            return url('/student/team-requests.php');
        case 'team':
        case 'team_message':
        case 'team_milestone':
        case 'team_task':
            return $relatedId ? url('/student/team-details.php?id=' . $relatedId) : url('/student/teams.php');
        case 'community':
        case 'community_post':
            return $relatedId ? url('/student/community-details.php?id=' . $relatedId) : url('/student/communities.php');
        case 'advisor_request':
            return url('/student/advisor-requests.php');
        case 'advisor_assignment':
            return url('/student/advisor-requests.php');
        case 'connection_request':
            return url('/student/faculty-connections.php');
        case 'direct_message':
            return $relatedId ? url('/student/conversation.php?user=' . $relatedId) : url('/student/messages.php');
        default:
            return url('/student/notifications.php');
    }
}

function log_activity(PDO $pdo, int $userId, string $type, string $description, ?string $relatedType = null, ?int $relatedId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, activity_type, description, related_type, related_id) VALUES (?,?,?,?,?)');
    $stmt->execute([$userId, $type, $description, $relatedType, $relatedId]);
}

// ---------------------------------------------------------------------
// Team helpers
// ---------------------------------------------------------------------

function is_team_member(PDO $pdo, int $teamId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'Active'");
    $stmt->execute([$teamId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function is_team_leader(PDO $pdo, int $teamId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'Active' AND role = 'Leader'");
    $stmt->execute([$teamId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function team_member_count(PDO $pdo, int $teamId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND status = 'Active'");
    $stmt->execute([$teamId]);
    return (int)$stmt->fetchColumn();
}

// ---------------------------------------------------------------------
// File upload validation
// ---------------------------------------------------------------------

const DANGEROUS_UPLOAD_EXTS = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'htaccess', 'dll', 'msi', 'vbs'];

/**
 * @param array $file      One entry from $_FILES
 * @param array $allowedExts Lower-case extensions without the dot
 * @param int   $maxBytes
 * @return array{ok:bool,error?:string,ext?:string}
 */
function validate_upload(array $file, array $allowedExts, int $maxBytes): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['ok' => false, 'error' => 'No file selected.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['ok' => false, 'error' => 'File is too large.'];
        default:
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File exceeds the maximum allowed size.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || in_array($ext, DANGEROUS_UPLOAD_EXTS, true) || !in_array($ext, $allowedExts, true)) {
        return ['ok' => false, 'error' => 'File type not allowed.'];
    }
    return ['ok' => true, 'ext' => $ext];
}

function safe_filename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
    return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
}

// ---------------------------------------------------------------------
// Faculty profile helpers (mirror the student_profiles helpers above)
// ---------------------------------------------------------------------

function get_faculty_profile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM faculty_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_faculty_profile_by_id(PDO $pdo, int $facultyProfileId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM faculty_profiles WHERE id = ? LIMIT 1');
    $stmt->execute([$facultyProfileId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_faculty_domain_ids(PDO $pdo, int $facultyProfileId): array
{
    $stmt = $pdo->prepare('SELECT domain_id FROM faculty_research_domains WHERE faculty_profile_id = ?');
    $stmt->execute([$facultyProfileId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_faculty_skill_ids(PDO $pdo, int $facultyProfileId): array
{
    $stmt = $pdo->prepare('SELECT skill_id FROM faculty_skills WHERE faculty_profile_id = ?');
    $stmt->execute([$facultyProfileId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_faculty_preferences(PDO $pdo, int $facultyProfileId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM faculty_preferences WHERE faculty_profile_id = ? LIMIT 1');
    $stmt->execute([$facultyProfileId]);
    return $stmt->fetch() ?: null;
}

function get_faculty_visibility(PDO $pdo, int $facultyProfileId): array
{
    $stmt = $pdo->prepare('SELECT * FROM faculty_visibility WHERE faculty_profile_id = ? LIMIT 1');
    $stmt->execute([$facultyProfileId]);
    $row = $stmt->fetch();
    return $row ?: [
        'profile_visibility'     => 'Public',
        'contact_visibility'     => 1,
        'research_visibility'    => 1,
        'project_visibility'     => 1,
        'publication_visibility' => 1,
    ];
}

/**
 * Recomputes and persists nothing (faculty_profiles has no completion
 * column) — returns a 0-100 completion score for display only.
 * Weighted: bio 10, contact 10, department+designation 15, research
 * statement 10, domains 15, skills 10, publications/projects 15, photo 5,
 * preferences 10 = 100.
 */
function calculate_faculty_profile_completion(PDO $pdo, int $userId): int
{
    $profile = get_faculty_profile($pdo, $userId);
    if (!$profile) {
        return 0;
    }
    $fpId  = (int)$profile['id'];
    $score = 0;

    if (nullable_trim($profile['bio'] ?? '') !== null) {
        $score += 10;
    }
    if (nullable_trim($profile['phone'] ?? '') !== null && nullable_trim($profile['office_location'] ?? '') !== null) {
        $score += 10;
    }
    if (!empty($profile['department']) && !empty($profile['designation'])) {
        $score += 15;
    }
    if (nullable_trim($profile['research_statement'] ?? '') !== null) {
        $score += 10;
    }
    if (count(get_faculty_domain_ids($pdo, $fpId)) > 0) {
        $score += 15;
    }
    if (count(get_faculty_skill_ids($pdo, $fpId)) > 0) {
        $score += 10;
    }

    $pubStmt = $pdo->prepare('SELECT COUNT(*) FROM faculty_publications WHERE faculty_profile_id = ?');
    $pubStmt->execute([$fpId]);
    $projStmt = $pdo->prepare('SELECT COUNT(*) FROM faculty_projects WHERE faculty_profile_id = ?');
    $projStmt->execute([$fpId]);
    if ((int)$pubStmt->fetchColumn() > 0 || (int)$projStmt->fetchColumn() > 0) {
        $score += 15;
    }

    if (!empty($profile['profile_photo'])) {
        $score += 5;
    }
    if (get_faculty_preferences($pdo, $fpId) !== null) {
        $score += 10;
    }

    return (int)min(100, $score);
}

// ---------------------------------------------------------------------
// Cross-role visibility checks
// ---------------------------------------------------------------------

/** True if $viewerUserId may view $studentProfile (owner always can). */
function can_view_student_profile(PDO $pdo, int $viewerUserId, array $studentProfile): bool
{
    if ((int)$studentProfile['user_id'] === $viewerUserId) {
        return true;
    }
    $vis = get_profile_visibility($pdo, (int)$studentProfile['id']);
    return ($vis['profile_visibility'] ?? 'Students Only') !== 'Private';
}

/** True if $viewerUserId may view $facultyProfile (owner always can). */
function can_view_faculty_profile(PDO $pdo, int $viewerUserId, array $facultyProfile): bool
{
    if ((int)$facultyProfile['user_id'] === $viewerUserId) {
        return true;
    }
    $vis = get_faculty_visibility($pdo, (int)$facultyProfile['id']);
    return ($vis['profile_visibility'] ?? 'Public') !== 'Private';
}

// ---------------------------------------------------------------------
// Advisor / mentorship helpers
// ---------------------------------------------------------------------

/** True if this faculty member has an *active* advisor assignment on this team. */
function is_team_advisor(PDO $pdo, int $teamId, int $facultyUserId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE team_id = ? AND faculty_user_id = ? AND status = 'active'");
    $stmt->execute([$teamId, $facultyUserId]);
    return (bool)$stmt->fetchColumn();
}

/** The faculty member currently advising this team, if any. */
function get_team_advisor(PDO $pdo, int $teamId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT aa.*, u.name AS faculty_name, fp.designation, fp.department, fp.profile_photo
         FROM advisor_assignments aa
         JOIN users u ON u.id = aa.faculty_user_id
         LEFT JOIN faculty_profiles fp ON fp.user_id = aa.faculty_user_id
         WHERE aa.team_id = ? AND aa.status = 'active'
         ORDER BY aa.assigned_at DESC LIMIT 1"
    );
    $stmt->execute([$teamId]);
    return $stmt->fetch() ?: null;
}

/** How many mentee "slots" (individual + team assignments) this faculty member currently has active. */
function faculty_active_assignment_count(PDO $pdo, int $facultyUserId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE faculty_user_id = ? AND status = 'active'");
    $stmt->execute([$facultyUserId]);
    return (int)$stmt->fetchColumn();
}

/** Remaining mentee capacity, or null if this faculty member has no preferences row (treated as unlimited). */
function faculty_capacity_remaining(PDO $pdo, int $facultyUserId): ?int
{
    $profile = get_faculty_profile($pdo, $facultyUserId);
    if (!$profile) {
        return null;
    }
    $prefs = get_faculty_preferences($pdo, (int)$profile['id']);
    if (!$prefs) {
        return null;
    }
    $max = (int)$prefs['max_active_mentees'];
    return max(0, $max - faculty_active_assignment_count($pdo, $facultyUserId));
}

/** True if there's already a pending advisor_requests row for this exact faculty+target+type. */
function has_pending_advisor_request(PDO $pdo, int $facultyUserId, ?int $studentUserId, ?int $teamId, string $requestType): bool
{
    $sql = "SELECT COUNT(*) FROM advisor_requests WHERE faculty_user_id = ? AND request_type = ? AND status = 'pending'";
    $params = [$facultyUserId, $requestType];
    if ($teamId !== null) {
        $sql .= ' AND team_id = ?';
        $params[] = $teamId;
    } else {
        $sql .= ' AND team_id IS NULL AND requested_by_user_id = ?';
        $params[] = $studentUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/** True if there's already an active advisor_assignments row for this exact faculty+target+type. */
function has_active_advisor_assignment(PDO $pdo, int $facultyUserId, ?int $studentUserId, ?int $teamId, string $assignmentType): bool
{
    $sql = "SELECT COUNT(*) FROM advisor_assignments WHERE faculty_user_id = ? AND assignment_type = ? AND status = 'active'";
    $params = [$facultyUserId, $assignmentType];
    if ($teamId !== null) {
        $sql .= ' AND team_id = ?';
        $params[] = $teamId;
    } else {
        $sql .= ' AND team_id IS NULL AND student_user_id = ?';
        $params[] = $studentUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

// ---------------------------------------------------------------------
// Research connections + direct chat
// ---------------------------------------------------------------------

/** True if there's a pending or accepted connection already between these two users. */
function has_existing_connection(PDO $pdo, int $userA, int $userB): bool
{
    $pairKey = min($userA, $userB) . '-' . max($userA, $userB);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM research_connections WHERE pair_key = ? AND status IN ('pending','accepted')");
    $stmt->execute([$pairKey]);
    return (bool)$stmt->fetchColumn();
}

/** True if $userA and $userB are allowed to exchange direct messages. */
function can_message(PDO $pdo, int $userA, int $userB): bool
{
    if ($userA === $userB) {
        return false;
    }
    $pairKey = min($userA, $userB) . '-' . max($userA, $userB);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM research_connections WHERE pair_key = ? AND status = 'accepted'");
    $stmt->execute([$pairKey]);
    if ((int)$stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM advisor_assignments
         WHERE status = 'active'
           AND ((faculty_user_id = ? AND student_user_id = ?) OR (faculty_user_id = ? AND student_user_id = ?))"
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Finds (or transactionally creates) the direct_conversations row for this
 * user pair. Canonicalizes ordering (user_one_id < user_two_id) so the
 * unique key on (user_one_id,user_two_id) can never be duplicated.
 */
function get_or_create_direct_conversation(PDO $pdo, int $userA, int $userB, ?int $connectionId = null, ?int $assignmentId = null): int
{
    $one = min($userA, $userB);
    $two = max($userA, $userB);

    $find = $pdo->prepare('SELECT id FROM direct_conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
    $find->execute([$one, $two]);
    $existing = $find->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO direct_conversations (user_one_id, user_two_id, connection_id, advisor_assignment_id) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$one, $two, $connectionId, $assignmentId]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Unique-key race: another request created it between our SELECT and INSERT.
        $find->execute([$one, $two]);
        $existing = $find->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }
        throw $e;
    }
}

// ---------------------------------------------------------------------
// Platform settings (admin-configurable, real backend effect only)
// ---------------------------------------------------------------------

/** All platform_settings rows as [key => value], cached per request. */
function get_all_platform_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM platform_settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function get_platform_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $all = get_all_platform_settings($pdo);
    return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== ''
        ? $all[$key]
        : $default;
}
