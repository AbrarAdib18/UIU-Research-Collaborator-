<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/profile.php');
}

require_csrf('/student/profile.php');

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];
$action    = $_POST['action'] ?? '';

/** Returns a Y-m-d date string only if it looks like a valid HTML date input value, else null. */
function local_date(?string $v): ?string
{
    $v = trim((string)$v);
    if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    return $v;
}

/**
 * Returns a http(s) URL only if it is well-formed, else null.
 * Rejects javascript:/data:/vbscript: and other dangerous schemes that
 * would otherwise be stored and later echoed into an href="" attribute.
 */
function local_url(?string $v): ?string
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    if (!preg_match('~^https?://~i', $v) || !filter_var($v, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $v;
}

switch ($action) {

    // -----------------------------------------------------------------
    // PERSONAL / CONTACT / ACADEMIC
    // -----------------------------------------------------------------
    case 'update_personal': {
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name'] ?? '');
        $bio      = nullable_trim($_POST['bio'] ?? '');
        $fullName = trim($first . ' ' . $last);
        if ($fullName === '') {
            flash('error', 'Name cannot be empty.');
            break;
        }

        $dob = local_date($_POST['date_of_birth'] ?? '');
        if ($dob !== null && $dob > date('Y-m-d')) {
            flash('error', 'Date of birth cannot be in the future.');
            break;
        }

        $validGenders = ['Male', 'Female', 'Other', 'Prefer not to say'];
        $gender       = $_POST['gender'] ?? '';
        $gender       = in_array($gender, $validGenders, true) ? $gender : null;

        try {
            $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$fullName, $userId]);
            $pdo->prepare('UPDATE student_profiles SET bio = ?, date_of_birth = ?, gender = ? WHERE id = ?')
                ->execute([$bio, $dob, $gender, $profileId]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Updated personal information');
            flash('success', 'Personal information updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_personal: ' . $ex->getMessage());
            flash('error', 'Could not update personal information. Please try again.');
        }
        break;
    }

    case 'update_contact': {
        $phone    = nullable_trim($_POST['phone'] ?? '');
        $location = nullable_trim($_POST['location'] ?? '');
        $linkedin = local_url($_POST['linkedin_url'] ?? '');
        $github   = local_url($_POST['github_url'] ?? '');

        $validPreferredContact = ['University Email', 'Phone', 'Platform Messages'];
        $preferredContact      = $_POST['preferred_contact'] ?? '';
        $preferredContact      = in_array($preferredContact, $validPreferredContact, true) ? $preferredContact : null;

        try {
            $pdo->prepare('UPDATE student_profiles SET phone = ?, location = ?, linkedin_url = ?, github_url = ?, preferred_contact = ? WHERE id = ?')
                ->execute([$phone, $location, $linkedin, $github, $preferredContact, $profileId]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Updated contact information');
            flash('success', 'Contact information updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_contact: ' . $ex->getMessage());
            flash('error', 'Could not update contact information. Please try again.');
        }
        break;
    }

    case 'update_academic': {
        $department = nullable_trim($_POST['department'] ?? '');
        $program    = nullable_trim($_POST['program'] ?? '');
        $semester   = nullable_trim($_POST['semester'] ?? '');
        $cgpaRaw    = trim($_POST['cgpa'] ?? '');
        $cgpa       = null;
        if ($cgpaRaw !== '') {
            if (!is_numeric($cgpaRaw) || (float)$cgpaRaw < 0 || (float)$cgpaRaw > 4) {
                flash('error', 'CGPA must be between 0.00 and 4.00.');
                break;
            }
            $cgpa = number_format((float)$cgpaRaw, 2, '.', '');
        }

        // <input type="month"> posts "YYYY-MM" — store as the first of that month.
        $gradRaw       = trim($_POST['expected_graduation_date'] ?? '');
        $expectedGrad  = null;
        if ($gradRaw !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $gradRaw)) {
                flash('error', 'Invalid expected graduation date.');
                break;
            }
            $expectedGrad = $gradRaw . '-01';
        }

        $validStatuses  = ['Currently Studying', 'Graduated', 'On Leave'];
        $academicStatus = $_POST['academic_status'] ?? '';
        $academicStatus = in_array($academicStatus, $validStatuses, true) ? $academicStatus : null;

        try {
            $pdo->prepare('UPDATE student_profiles SET department = ?, program = ?, semester = ?, cgpa = ?, expected_graduation_date = ?, academic_status = ? WHERE id = ?')
                ->execute([$department, $program, $semester, $cgpa, $expectedGrad, $academicStatus, $profileId]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Updated academic information');
            flash('success', 'Academic information updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_academic: ' . $ex->getMessage());
            flash('error', 'Could not update academic information. Please try again.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // RESEARCH DOMAINS + RESEARCH STATEMENT
    // -----------------------------------------------------------------
    case 'add_domain': {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        if ($domainId <= 0) {
            flash('error', 'Please choose a research domain.');
            break;
        }
        try {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM research_domains WHERE id = ?');
            $chk->execute([$domainId]);
            if (!$chk->fetchColumn()) {
                flash('error', 'Invalid research domain.');
                break;
            }
            $pdo->prepare('INSERT IGNORE INTO profile_research_domains (profile_id, domain_id) VALUES (?, ?)')
                ->execute([$profileId, $domainId]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Added a research domain');
            flash('success', 'Research domain added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_domain: ' . $ex->getMessage());
            flash('error', 'Could not add research domain.');
        }
        break;
    }

    case 'remove_domain': {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM profile_research_domains WHERE profile_id = ? AND domain_id = ?')
                ->execute([$profileId, $domainId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Research domain removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_domain: ' . $ex->getMessage());
            flash('error', 'Could not remove research domain.');
        }
        break;
    }

    case 'update_research_statement': {
        $statement = nullable_trim($_POST['research_statement'] ?? '');

        $validMethodologies = ['Experimental Research', 'Data Analysis', 'Machine Learning', 'System Development', 'Survey Research', 'Literature Review'];
        $submittedMethods    = (array)($_POST['research_methodologies'] ?? []);
        $methods             = array_values(array_intersect($validMethodologies, $submittedMethods));
        $methodologies       = $methods ? implode(',', $methods) : null;

        try {
            $pdo->prepare('UPDATE student_profiles SET research_statement = ?, research_methodologies = ? WHERE id = ?')
                ->execute([$statement, $methodologies, $profileId]);
            log_activity($pdo, $userId, 'profile_update', 'Updated research description');
            flash('success', 'Research description updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_research_statement: ' . $ex->getMessage());
            flash('error', 'Could not update research description.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // SKILLS
    // -----------------------------------------------------------------
    case 'add_skill': {
        $skillId     = (int)($_POST['skill_id'] ?? 0);
        $level       = $_POST['level'] ?? 'Beginner';
        $validLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
        if ($skillId <= 0) {
            flash('error', 'Please choose a skill.');
            break;
        }
        if (!in_array($level, $validLevels, true)) {
            $level = 'Beginner';
        }
        try {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE id = ?');
            $chk->execute([$skillId]);
            if (!$chk->fetchColumn()) {
                flash('error', 'Invalid skill.');
                break;
            }
            $pdo->prepare('INSERT INTO profile_skills (profile_id, skill_id, level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE level = VALUES(level)')
                ->execute([$profileId, $skillId, $level]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Added a skill');
            flash('success', 'Skill added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_skill: ' . $ex->getMessage());
            flash('error', 'Could not add skill.');
        }
        break;
    }

    case 'update_skill': {
        $skillId     = (int)($_POST['skill_id'] ?? 0);
        $level       = $_POST['level'] ?? '';
        $validLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
        if (!in_array($level, $validLevels, true)) {
            flash('error', 'Invalid skill level.');
            break;
        }
        try {
            $pdo->prepare('UPDATE profile_skills SET level = ? WHERE profile_id = ? AND skill_id = ?')
                ->execute([$level, $profileId, $skillId]);
            flash('success', 'Skill level updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_skill: ' . $ex->getMessage());
            flash('error', 'Could not update skill level.');
        }
        break;
    }

    case 'remove_skill': {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM profile_skills WHERE profile_id = ? AND skill_id = ?')
                ->execute([$profileId, $skillId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Skill removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_skill: ' . $ex->getMessage());
            flash('error', 'Could not remove skill.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // EDUCATION
    // -----------------------------------------------------------------
    case 'add_education': {
        $institution = nullable_trim($_POST['institution'] ?? '');
        if (!$institution) {
            flash('error', 'Institution is required.');
            break;
        }
        $degree = nullable_trim($_POST['degree'] ?? '');
        $field  = nullable_trim($_POST['field_of_study'] ?? '');
        $start  = local_date($_POST['start_date'] ?? '');
        $end    = local_date($_POST['end_date'] ?? '');
        $desc   = nullable_trim($_POST['description'] ?? '');
        try {
            $pdo->prepare('INSERT INTO education (profile_id, institution, degree, field_of_study, start_date, end_date, description) VALUES (?,?,?,?,?,?,?)')
                ->execute([$profileId, $institution, $degree, $field, $start, $end, $desc]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Added an education entry');
            flash('success', 'Education added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_education: ' . $ex->getMessage());
            flash('error', 'Could not add education.');
        }
        break;
    }

    case 'update_education': {
        $id          = (int)($_POST['id'] ?? 0);
        $institution = nullable_trim($_POST['institution'] ?? '');
        if (!$institution) {
            flash('error', 'Institution is required.');
            break;
        }
        $degree = nullable_trim($_POST['degree'] ?? '');
        $field  = nullable_trim($_POST['field_of_study'] ?? '');
        $start  = local_date($_POST['start_date'] ?? '');
        $end    = local_date($_POST['end_date'] ?? '');
        $desc   = nullable_trim($_POST['description'] ?? '');
        try {
            $stmt = $pdo->prepare('UPDATE education SET institution=?, degree=?, field_of_study=?, start_date=?, end_date=?, description=? WHERE id=? AND profile_id=?');
            $stmt->execute([$institution, $degree, $field, $start, $end, $desc, $id, $profileId]);
            flash('success', 'Education updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_education: ' . $ex->getMessage());
            flash('error', 'Could not update education.');
        }
        break;
    }

    case 'remove_education': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM education WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Education removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_education: ' . $ex->getMessage());
            flash('error', 'Could not remove education.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // WORK EXPERIENCE
    // -----------------------------------------------------------------
    case 'add_work':
    case 'update_work': {
        $org = nullable_trim($_POST['organization'] ?? '');
        if (!$org) {
            flash('error', 'Organization is required.');
            break;
        }
        $position   = nullable_trim($_POST['position'] ?? '');
        $type       = $_POST['employment_type'] ?? '';
        $validTypes = ['Full-time', 'Part-time', 'Internship', 'Research Assistant', 'Volunteer', 'Freelance'];
        $type       = in_array($type, $validTypes, true) ? $type : null;
        $start      = local_date($_POST['start_date'] ?? '');
        $end        = local_date($_POST['end_date'] ?? '');
        $isCurrent  = isset($_POST['is_current']) ? 1 : 0;
        if ($isCurrent) {
            $end = null;
        }
        $desc = nullable_trim($_POST['description'] ?? '');

        try {
            if ($action === 'add_work') {
                $pdo->prepare('INSERT INTO work_experience (profile_id, organization, position, employment_type, start_date, end_date, is_current, description) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$profileId, $org, $position, $type, $start, $end, $isCurrent, $desc]);
                calculate_profile_completion($pdo, $userId);
                log_activity($pdo, $userId, 'profile_update', 'Added a work experience entry');
                flash('success', 'Work experience added.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE work_experience SET organization=?, position=?, employment_type=?, start_date=?, end_date=?, is_current=?, description=? WHERE id=? AND profile_id=?');
                $stmt->execute([$org, $position, $type, $start, $end, $isCurrent, $desc, $id, $profileId]);
                flash('success', 'Work experience updated.');
            }
        } catch (Throwable $ex) {
            error_log('profile-edit ' . $action . ': ' . $ex->getMessage());
            flash('error', 'Could not save work experience.');
        }
        break;
    }

    case 'remove_work': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM work_experience WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Work experience removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_work: ' . $ex->getMessage());
            flash('error', 'Could not remove work experience.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // EXTRACURRICULAR ACTIVITIES
    // -----------------------------------------------------------------
    case 'add_extracurricular':
    case 'update_extracurricular': {
        $title = nullable_trim($_POST['title'] ?? '');
        if (!$title) {
            flash('error', 'Activity title is required.');
            break;
        }
        $org       = nullable_trim($_POST['organization'] ?? '');
        $role      = nullable_trim($_POST['role'] ?? '');
        $start     = local_date($_POST['start_date'] ?? '');
        $end       = local_date($_POST['end_date'] ?? '');
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        if ($isCurrent) {
            $end = null;
        }
        if ($start !== null && $end !== null && $start > $end) {
            flash('error', 'Start date cannot be after end date.');
            break;
        }
        $desc = nullable_trim($_POST['description'] ?? '');

        try {
            if ($action === 'add_extracurricular') {
                $pdo->prepare('INSERT INTO extracurricular_activities (profile_id, title, organization, role, start_date, end_date, is_current, description) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$profileId, $title, $org, $role, $start, $end, $isCurrent, $desc]);
                log_activity($pdo, $userId, 'profile_update', 'Added an extracurricular activity: ' . $title);
                flash('success', 'Activity added.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE extracurricular_activities SET title=?, organization=?, role=?, start_date=?, end_date=?, is_current=?, description=? WHERE id=? AND profile_id=?');
                $stmt->execute([$title, $org, $role, $start, $end, $isCurrent, $desc, $id, $profileId]);
                flash('success', 'Activity updated.');
            }
        } catch (Throwable $ex) {
            error_log('profile-edit ' . $action . ': ' . $ex->getMessage());
            flash('error', 'Could not save activity.');
        }
        break;
    }

    case 'remove_extracurricular': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM extracurricular_activities WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            flash('success', 'Activity removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_extracurricular: ' . $ex->getMessage());
            flash('error', 'Could not remove activity.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // AVAILABILITY SCHEDULE (one row per selected weekday)
    // -----------------------------------------------------------------
    case 'update_availability': {
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $enabledDays = (array)($_POST['day_enabled'] ?? []);
        $startTimes  = (array)($_POST['day_start'] ?? []);
        $endTimes    = (array)($_POST['day_end'] ?? []);

        $rowsToSave = [];
        foreach ($days as $day) {
            if (empty($enabledDays[$day])) {
                continue;
            }
            $start = trim((string)($startTimes[$day] ?? ''));
            $end   = trim((string)($endTimes[$day] ?? ''));
            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                flash('error', "Please provide a valid start and end time for $day.");
                $rowsToSave = null;
                break;
            }
            if ($start >= $end) {
                flash('error', "$day's start time must be before its end time.");
                $rowsToSave = null;
                break;
            }
            $rowsToSave[$day] = [$start, $end];
        }

        if ($rowsToSave === null) {
            break;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM profile_availability WHERE profile_id = ?')->execute([$profileId]);
            if ($rowsToSave) {
                $ins = $pdo->prepare('INSERT INTO profile_availability (profile_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
                foreach ($rowsToSave as $day => [$start, $end]) {
                    $ins->execute([$profileId, $day, $start, $end]);
                }
            }
            $pdo->commit();
            log_activity($pdo, $userId, 'profile_update', 'Updated availability schedule');
            flash('success', 'Availability schedule updated.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('profile-edit update_availability: ' . $ex->getMessage());
            flash('error', 'Could not update availability schedule.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PROJECTS
    // -----------------------------------------------------------------
    case 'add_project':
    case 'update_project': {
        $title = nullable_trim($_POST['title'] ?? '');
        if (!$title) {
            flash('error', 'Project title is required.');
            break;
        }
        $desc     = nullable_trim($_POST['description'] ?? '');
        $type     = nullable_trim($_POST['project_type'] ?? '');
        $start    = local_date($_POST['start_date'] ?? '');
        $end      = local_date($_POST['end_date'] ?? '');
        $repo     = local_url($_POST['repository_url'] ?? '');
        $demo     = local_url($_POST['demo_url'] ?? '');
        $featured = isset($_POST['is_featured']) ? 1 : 0;

        try {
            if ($action === 'add_project') {
                $pdo->prepare('INSERT INTO projects (profile_id, title, description, project_type, start_date, end_date, repository_url, demo_url, is_featured) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$profileId, $title, $desc, $type, $start, $end, $repo, $demo, $featured]);
                calculate_profile_completion($pdo, $userId);
                log_activity($pdo, $userId, 'profile_update', 'Added a project: ' . $title);
                flash('success', 'Project added.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE projects SET title=?, description=?, project_type=?, start_date=?, end_date=?, repository_url=?, demo_url=?, is_featured=? WHERE id=? AND profile_id=?');
                $stmt->execute([$title, $desc, $type, $start, $end, $repo, $demo, $featured, $id, $profileId]);
                flash('success', 'Project updated.');
            }
        } catch (Throwable $ex) {
            error_log('profile-edit ' . $action . ': ' . $ex->getMessage());
            flash('error', 'Could not save project.');
        }
        break;
    }

    case 'remove_project': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM projects WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Project removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_project: ' . $ex->getMessage());
            flash('error', 'Could not remove project.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PUBLICATIONS
    // -----------------------------------------------------------------
    case 'add_publication': {
        $title = nullable_trim($_POST['title'] ?? '');
        if (!$title) {
            flash('error', 'Publication title is required.');
            break;
        }
        $authors        = nullable_trim($_POST['authors'] ?? '');
        $venue          = nullable_trim($_POST['venue'] ?? '');
        $type           = nullable_trim($_POST['publication_type'] ?? '');
        $date           = local_date($_POST['publication_date'] ?? '');
        $doi            = nullable_trim($_POST['doi'] ?? '');
        $urlField       = local_url($_POST['url'] ?? '');
        $abstract       = nullable_trim($_POST['abstract'] ?? '');
        $status         = $_POST['status'] ?? 'In Preparation';
        $validStatuses  = ['Published', 'Accepted', 'Under Review', 'In Preparation'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'In Preparation';
        }
        try {
            $pdo->prepare('INSERT INTO publications (profile_id, title, authors, venue, publication_type, publication_date, doi, url, abstract, status) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$profileId, $title, $authors, $venue, $type, $date, $doi, $urlField, $abstract, $status]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Added a publication: ' . $title);
            flash('success', 'Publication added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_publication: ' . $ex->getMessage());
            flash('error', 'Could not add publication.');
        }
        break;
    }

    case 'remove_publication': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM publications WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            calculate_profile_completion($pdo, $userId);
            flash('success', 'Publication removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_publication: ' . $ex->getMessage());
            flash('error', 'Could not remove publication.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // CERTIFICATIONS
    // -----------------------------------------------------------------
    case 'add_certification': {
        $name = nullable_trim($_POST['name'] ?? '');
        if (!$name) {
            flash('error', 'Certification name is required.');
            break;
        }
        $org     = nullable_trim($_POST['issuing_organization'] ?? '');
        $issue   = local_date($_POST['issue_date'] ?? '');
        $expiry  = local_date($_POST['expiry_date'] ?? '');
        $credId  = nullable_trim($_POST['credential_id'] ?? '');
        $credUrl = local_url($_POST['credential_url'] ?? '');
        try {
            $pdo->prepare('INSERT INTO certifications (profile_id, name, issuing_organization, issue_date, expiry_date, credential_id, credential_url) VALUES (?,?,?,?,?,?,?)')
                ->execute([$profileId, $name, $org, $issue, $expiry, $credId, $credUrl]);
            log_activity($pdo, $userId, 'profile_update', 'Added a certification: ' . $name);
            flash('success', 'Certification added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_certification: ' . $ex->getMessage());
            flash('error', 'Could not add certification.');
        }
        break;
    }

    case 'remove_certification': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM certifications WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            flash('success', 'Certification removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_certification: ' . $ex->getMessage());
            flash('error', 'Could not remove certification.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // AWARDS & ACHIEVEMENTS
    // -----------------------------------------------------------------
    case 'add_achievement': {
        $title = nullable_trim($_POST['title'] ?? '');
        if (!$title) {
            flash('error', 'Achievement title is required.');
            break;
        }
        $org  = nullable_trim($_POST['organization'] ?? '');
        $date = local_date($_POST['achievement_date'] ?? '');
        $desc = nullable_trim($_POST['description'] ?? '');
        try {
            $pdo->prepare('INSERT INTO achievements (profile_id, title, description, organization, achievement_date) VALUES (?,?,?,?,?)')
                ->execute([$profileId, $title, $desc, $org, $date]);
            log_activity($pdo, $userId, 'profile_update', 'Added an achievement: ' . $title);
            flash('success', 'Achievement added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_achievement: ' . $ex->getMessage());
            flash('error', 'Could not add achievement.');
        }
        break;
    }

    case 'remove_achievement': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM achievements WHERE id = ? AND profile_id = ?')->execute([$id, $profileId]);
            flash('success', 'Achievement removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_achievement: ' . $ex->getMessage());
            flash('error', 'Could not remove achievement.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // LANGUAGES
    // -----------------------------------------------------------------
    case 'add_language': {
        $languageId = (int)($_POST['language_id'] ?? 0);
        $prof       = $_POST['proficiency'] ?? 'Intermediate';
        $validProf  = ['Basic', 'Conversational', 'Intermediate', 'Advanced', 'Fluent', 'Native'];
        if ($languageId <= 0) {
            flash('error', 'Please choose a language.');
            break;
        }
        if (!in_array($prof, $validProf, true)) {
            $prof = 'Intermediate';
        }
        try {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM languages WHERE id = ?');
            $chk->execute([$languageId]);
            if (!$chk->fetchColumn()) {
                flash('error', 'Invalid language.');
                break;
            }
            $pdo->prepare('INSERT INTO profile_languages (profile_id, language_id, proficiency) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE proficiency = VALUES(proficiency)')
                ->execute([$profileId, $languageId, $prof]);
            log_activity($pdo, $userId, 'profile_update', 'Added a language');
            flash('success', 'Language added.');
        } catch (Throwable $ex) {
            error_log('profile-edit add_language: ' . $ex->getMessage());
            flash('error', 'Could not add language.');
        }
        break;
    }

    case 'remove_language': {
        $languageId = (int)($_POST['language_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM profile_languages WHERE profile_id = ? AND language_id = ?')
                ->execute([$profileId, $languageId]);
            flash('success', 'Language removed.');
        } catch (Throwable $ex) {
            error_log('profile-edit remove_language: ' . $ex->getMessage());
            flash('error', 'Could not remove language.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // RESEARCH PREFERENCES (one row per profile)
    // -----------------------------------------------------------------
    case 'update_preferences': {
        $lookingForTeam = isset($_POST['looking_for_team']) ? 1 : 0;
        $minSize        = max(1, (int)($_POST['preferred_team_size_min'] ?? 2));
        $maxSize        = max($minSize, (int)($_POST['preferred_team_size_max'] ?? 4));
        $availRaw       = trim($_POST['availability_hours_per_week'] ?? '');
        $avail          = ($availRaw !== '' && is_numeric($availRaw)) ? max(0, (int)$availRaw) : null;
        $collab         = $_POST['collaboration_preference'] ?? '';
        $validCollab    = ['Online', 'In Person', 'Online + In Person'];
        $collab         = in_array($collab, $validCollab, true) ? $collab : null;
        $projType       = nullable_trim($_POST['project_type_preference'] ?? '');

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO research_preferences
                    (profile_id, looking_for_team, preferred_team_size_min, preferred_team_size_max, availability_hours_per_week, collaboration_preference, project_type_preference)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    looking_for_team = VALUES(looking_for_team),
                    preferred_team_size_min = VALUES(preferred_team_size_min),
                    preferred_team_size_max = VALUES(preferred_team_size_max),
                    availability_hours_per_week = VALUES(availability_hours_per_week),
                    collaboration_preference = VALUES(collaboration_preference),
                    project_type_preference = VALUES(project_type_preference)'
            );
            $stmt->execute([$profileId, $lookingForTeam, $minSize, $maxSize, $avail, $collab, $projType]);
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Updated research preferences');
            flash('success', 'Research preferences updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_preferences: ' . $ex->getMessage());
            flash('error', 'Could not update research preferences.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PROFILE VISIBILITY (one row per profile)
    // -----------------------------------------------------------------
    case 'update_visibility': {
        $val   = $_POST['profile_visibility'] ?? '';
        $valid = ['Public', 'Students Only', 'Private'];
        if (!in_array($val, $valid, true)) {
            flash('error', 'Invalid visibility option.');
            break;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO profile_visibility (profile_id, profile_visibility) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE profile_visibility = VALUES(profile_visibility)'
            );
            $stmt->execute([$profileId, $val]);
            log_activity($pdo, $userId, 'profile_update', 'Updated profile visibility');
            flash('success', 'Profile visibility updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit update_visibility: ' . $ex->getMessage());
            flash('error', 'Could not update profile visibility.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // FILE UPLOADS: profile photo / cover photo / CV
    // -----------------------------------------------------------------
    case 'upload_photo': {
        if (!isset($_FILES['photo'])) {
            flash('error', 'No file selected.');
            break;
        }
        $result = validate_upload($_FILES['photo'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 3 * 1024 * 1024);
        if (!$result['ok']) {
            flash('error', $result['error']);
            break;
        }
        try {
            $dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = safe_filename($_FILES['photo']['name']);
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
                throw new RuntimeException('move_uploaded_file failed for photo');
            }
            $old = $studentProfile['profile_photo'] ?? null;
            $pdo->prepare('UPDATE student_profiles SET profile_photo = ? WHERE id = ?')->execute([$filename, $profileId]);
            if ($old && is_file($dir . $old)) {
                @unlink($dir . $old);
            }
            calculate_profile_completion($pdo, $userId);
            log_activity($pdo, $userId, 'profile_update', 'Updated profile photo');
            flash('success', 'Profile photo updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit upload_photo: ' . $ex->getMessage());
            flash('error', 'Could not upload profile photo.');
        }
        break;
    }

    case 'upload_cover': {
        if (!isset($_FILES['cover'])) {
            flash('error', 'No file selected.');
            break;
        }
        $result = validate_upload($_FILES['cover'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 3 * 1024 * 1024);
        if (!$result['ok']) {
            flash('error', $result['error']);
            break;
        }
        try {
            $dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = safe_filename($_FILES['cover']['name']);
            if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dir . $filename)) {
                throw new RuntimeException('move_uploaded_file failed for cover');
            }
            $old = $studentProfile['cover_photo'] ?? null;
            $pdo->prepare('UPDATE student_profiles SET cover_photo = ? WHERE id = ?')->execute([$filename, $profileId]);
            if ($old && is_file($dir . $old)) {
                @unlink($dir . $old);
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated cover photo');
            flash('success', 'Cover photo updated.');
        } catch (Throwable $ex) {
            error_log('profile-edit upload_cover: ' . $ex->getMessage());
            flash('error', 'Could not upload cover photo.');
        }
        break;
    }

    case 'upload_cv': {
        if (!isset($_FILES['cv'])) {
            flash('error', 'No file selected.');
            break;
        }
        $result = validate_upload($_FILES['cv'], ['pdf', 'doc', 'docx'], 5 * 1024 * 1024);
        if (!$result['ok']) {
            flash('error', $result['error']);
            break;
        }
        try {
            $dir = __DIR__ . '/../uploads/cv/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = safe_filename($_FILES['cv']['name']);
            if (!move_uploaded_file($_FILES['cv']['tmp_name'], $dir . $filename)) {
                throw new RuntimeException('move_uploaded_file failed for cv');
            }
            $old = $studentProfile['cv_path'] ?? null;
            $pdo->prepare('UPDATE student_profiles SET cv_path = ? WHERE id = ?')->execute([$filename, $profileId]);
            if ($old && is_file($dir . $old)) {
                @unlink($dir . $old);
            }
            log_activity($pdo, $userId, 'profile_update', 'Uploaded CV / resume');
            flash('success', 'CV uploaded.');
        } catch (Throwable $ex) {
            error_log('profile-edit upload_cv: ' . $ex->getMessage());
            flash('error', 'Could not upload CV.');
        }
        break;
    }

    default:
        flash('error', 'Unknown action.');
        break;
}

redirect('/student/profile.php');
