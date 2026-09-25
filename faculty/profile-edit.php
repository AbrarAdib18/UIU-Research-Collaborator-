<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/faculty/profile.php');
}

require_csrf('/faculty/profile.php');

$pdo    = db();
$userId = (int)$currentUser['id'];
$fpId   = (int)$facultyProfile['id'];
$action = $_POST['action'] ?? '';

/** Returns a Y-m-d date string only if it looks like a valid HTML date input value, else null. */
function fac_local_date(?string $v): ?string
{
    $v = trim((string)$v);
    if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    return $v;
}

/** Returns a http(s) URL only if well-formed, else null (rejects javascript:/data: etc.). */
function fac_local_url(?string $v): ?string
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
    // IDENTITY / CONTACT / ACADEMIC
    // -----------------------------------------------------------------
    case 'update_identity': {
        $bio            = nullable_trim($_POST['bio'] ?? '');
        $specialization = nullable_trim($_POST['specialization'] ?? '');
        try {
            $pdo->prepare('UPDATE faculty_profiles SET bio = ?, specialization = ? WHERE id = ?')
                ->execute([$bio, $specialization, $fpId]);
            log_activity($pdo, $userId, 'profile_update', 'Updated faculty identity information');
            flash('success', 'Profile updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_identity: ' . $ex->getMessage());
            flash('error', 'Could not update your profile. Please try again.');
        }
        break;
    }

    case 'update_contact': {
        $phone      = nullable_trim($_POST['phone'] ?? '');
        $office     = nullable_trim($_POST['office_location'] ?? '');
        $linkedin   = fac_local_url($_POST['linkedin_url'] ?? '');
        $scholar    = fac_local_url($_POST['google_scholar_url'] ?? '');
        $researchgate = fac_local_url($_POST['researchgate_url'] ?? '');
        $portfolio  = fac_local_url($_POST['portfolio_url'] ?? '');
        try {
            $pdo->prepare('UPDATE faculty_profiles SET phone = ?, office_location = ?, linkedin_url = ?, google_scholar_url = ?, researchgate_url = ?, portfolio_url = ? WHERE id = ?')
                ->execute([$phone, $office, $linkedin, $scholar, $researchgate, $portfolio, $fpId]);
            log_activity($pdo, $userId, 'profile_update', 'Updated contact information');
            flash('success', 'Contact information updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_contact: ' . $ex->getMessage());
            flash('error', 'Could not update contact information. Please try again.');
        }
        break;
    }

    case 'update_academic': {
        $department  = nullable_trim($_POST['department'] ?? '');
        $designation = nullable_trim($_POST['designation'] ?? '');
        try {
            $pdo->prepare('UPDATE faculty_profiles SET department = ?, designation = ? WHERE id = ?')
                ->execute([$department, $designation, $fpId]);
            log_activity($pdo, $userId, 'profile_update', 'Updated academic information');
            flash('success', 'Academic information updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_academic: ' . $ex->getMessage());
            flash('error', 'Could not update academic information. Please try again.');
        }
        break;
    }

    case 'update_research_statement': {
        $statement = nullable_trim($_POST['research_statement'] ?? '');
        try {
            $pdo->prepare('UPDATE faculty_profiles SET research_statement = ? WHERE id = ?')->execute([$statement, $fpId]);
            log_activity($pdo, $userId, 'profile_update', 'Updated research statement');
            flash('success', 'Research statement updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_research_statement: ' . $ex->getMessage());
            flash('error', 'Could not update research statement.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // DOMAINS
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
            $pdo->prepare('INSERT IGNORE INTO faculty_research_domains (faculty_profile_id, domain_id) VALUES (?, ?)')
                ->execute([$fpId, $domainId]);
            log_activity($pdo, $userId, 'profile_update', 'Added a research domain');
            flash('success', 'Research domain added.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit add_domain: ' . $ex->getMessage());
            flash('error', 'Could not add research domain.');
        }
        break;
    }

    case 'remove_domain': {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM faculty_research_domains WHERE faculty_profile_id = ? AND domain_id = ?')
                ->execute([$fpId, $domainId]);
            flash('success', 'Research domain removed.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit remove_domain: ' . $ex->getMessage());
            flash('error', 'Could not remove research domain.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // SKILLS / EXPERTISE
    // -----------------------------------------------------------------
    case 'add_skill': {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        if ($skillId <= 0) {
            flash('error', 'Please choose a skill.');
            break;
        }
        try {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE id = ?');
            $chk->execute([$skillId]);
            if (!$chk->fetchColumn()) {
                flash('error', 'Invalid skill.');
                break;
            }
            $pdo->prepare('INSERT IGNORE INTO faculty_skills (faculty_profile_id, skill_id) VALUES (?, ?)')
                ->execute([$fpId, $skillId]);
            log_activity($pdo, $userId, 'profile_update', 'Added an area of expertise');
            flash('success', 'Expertise added.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit add_skill: ' . $ex->getMessage());
            flash('error', 'Could not add expertise.');
        }
        break;
    }

    case 'remove_skill': {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM faculty_skills WHERE faculty_profile_id = ? AND skill_id = ?')->execute([$fpId, $skillId]);
            flash('success', 'Expertise removed.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit remove_skill: ' . $ex->getMessage());
            flash('error', 'Could not remove expertise.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // EDUCATION
    // -----------------------------------------------------------------
    case 'add_education':
    case 'update_education': {
        $institution   = trim($_POST['institution'] ?? '');
        $degree        = nullable_trim($_POST['degree'] ?? '');
        $field         = nullable_trim($_POST['field_of_study'] ?? '');
        $start         = fac_local_date($_POST['start_date'] ?? '');
        $end           = fac_local_date($_POST['end_date'] ?? '');
        $description   = nullable_trim($_POST['description'] ?? '');
        if ($institution === '') {
            flash('error', 'Institution is required.');
            break;
        }
        try {
            if ($action === 'add_education') {
                $pdo->prepare('INSERT INTO faculty_education (faculty_profile_id, institution, degree, field_of_study, start_date, end_date, description) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$fpId, $institution, $degree, $field, $start, $end, $description]);
                flash('success', 'Education added.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE faculty_education SET institution=?, degree=?, field_of_study=?, start_date=?, end_date=?, description=? WHERE id=? AND faculty_profile_id=?')
                    ->execute([$institution, $degree, $field, $start, $end, $description, $id, $fpId]);
                flash('success', 'Education updated.');
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated education history');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit ' . $action . ': ' . $ex->getMessage());
            flash('error', 'Could not save education entry.');
        }
        break;
    }

    case 'remove_education': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM faculty_education WHERE id = ? AND faculty_profile_id = ?')->execute([$id, $fpId]);
            flash('success', 'Education entry removed.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit remove_education: ' . $ex->getMessage());
            flash('error', 'Could not remove education entry.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PUBLICATIONS
    // -----------------------------------------------------------------
    case 'add_publication': {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Publication title is required.');
            break;
        }
        $statuses = ['Published', 'Accepted', 'Under Review', 'In Preparation'];
        $status   = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'Published';
        try {
            $pdo->prepare('INSERT INTO faculty_publications (faculty_profile_id, title, authors, venue, publication_type, publication_date, doi, url, abstract, status) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $fpId, $title,
                    nullable_trim($_POST['authors'] ?? ''),
                    nullable_trim($_POST['venue'] ?? ''),
                    nullable_trim($_POST['publication_type'] ?? ''),
                    fac_local_date($_POST['publication_date'] ?? ''),
                    nullable_trim($_POST['doi'] ?? ''),
                    fac_local_url($_POST['url'] ?? ''),
                    nullable_trim($_POST['abstract'] ?? ''),
                    $status,
                ]);
            log_activity($pdo, $userId, 'profile_update', 'Added a publication');
            flash('success', 'Publication added.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit add_publication: ' . $ex->getMessage());
            flash('error', 'Could not add publication.');
        }
        break;
    }

    case 'remove_publication': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM faculty_publications WHERE id = ? AND faculty_profile_id = ?')->execute([$id, $fpId]);
            flash('success', 'Publication removed.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit remove_publication: ' . $ex->getMessage());
            flash('error', 'Could not remove publication.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PROJECTS
    // -----------------------------------------------------------------
    case 'add_project':
    case 'update_project': {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Project title is required.');
            break;
        }
        $statuses = ['Ongoing', 'Completed', 'Planned'];
        $status   = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'Ongoing';
        $params = [
            nullable_trim($_POST['description'] ?? ''),
            nullable_trim($_POST['project_type'] ?? ''),
            nullable_trim($_POST['funding_source'] ?? ''),
            fac_local_date($_POST['start_date'] ?? ''),
            fac_local_date($_POST['end_date'] ?? ''),
            $status,
            fac_local_url($_POST['repository_url'] ?? ''),
        ];
        try {
            if ($action === 'add_project') {
                $pdo->prepare('INSERT INTO faculty_projects (faculty_profile_id, title, description, project_type, funding_source, start_date, end_date, status, repository_url) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute(array_merge([$fpId, $title], $params));
                flash('success', 'Project added.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE faculty_projects SET title=?, description=?, project_type=?, funding_source=?, start_date=?, end_date=?, status=?, repository_url=? WHERE id=? AND faculty_profile_id=?')
                    ->execute(array_merge([$title], $params, [$id, $fpId]));
                flash('success', 'Project updated.');
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated a research project');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit ' . $action . ': ' . $ex->getMessage());
            flash('error', 'Could not save project.');
        }
        break;
    }

    case 'remove_project': {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM faculty_projects WHERE id = ? AND faculty_profile_id = ?')->execute([$id, $fpId]);
            flash('success', 'Project removed.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit remove_project: ' . $ex->getMessage());
            flash('error', 'Could not remove project.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // AVAILABILITY (whole-week replace, mirrors profile_availability model)
    // -----------------------------------------------------------------
    case 'update_availability': {
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        try {
            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM faculty_availability WHERE faculty_profile_id = ?');
            $del->execute([$fpId]);

            $ins = $pdo->prepare('INSERT INTO faculty_availability (faculty_profile_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)');
            foreach ($days as $day) {
                $key = strtolower($day);
                if (empty($_POST["avail_$key"])) {
                    continue;
                }
                $start = trim($_POST["avail_{$key}_start"] ?? '');
                $end   = trim($_POST["avail_{$key}_end"] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                    continue;
                }
                $ins->execute([$fpId, $day, $start, $end]);
            }
            $pdo->commit();
            log_activity($pdo, $userId, 'profile_update', 'Updated availability');
            flash('success', 'Availability updated.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('faculty profile-edit update_availability: ' . $ex->getMessage());
            flash('error', 'Could not update availability.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // MENTORSHIP PREFERENCES / CAPACITY
    // -----------------------------------------------------------------
    case 'update_preferences': {
        $acceptingMentees = isset($_POST['accepting_mentees']) ? 1 : 0;
        $acceptingTeam    = isset($_POST['accepting_team_advisory']) ? 1 : 0;
        $acceptingPaper   = isset($_POST['accepting_paper_advisory']) ? 1 : 0;
        $maxMentees       = max(0, (int)($_POST['max_active_mentees'] ?? 5));
        $projectTypes     = nullable_trim($_POST['preferred_project_types'] ?? '');
        $domains          = nullable_trim($_POST['preferred_domains'] ?? '');
        $meetingOptions   = ['Online', 'In Person', 'Online + In Person'];
        $meeting          = in_array($_POST['meeting_preference'] ?? '', $meetingOptions, true) ? $_POST['meeting_preference'] : null;
        $note             = nullable_trim($_POST['availability_note'] ?? '');

        try {
            $exists = $pdo->prepare('SELECT id FROM faculty_preferences WHERE faculty_profile_id = ?');
            $exists->execute([$fpId]);
            if ($exists->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE faculty_preferences SET accepting_mentees=?, accepting_team_advisory=?, accepting_paper_advisory=?, max_active_mentees=?, preferred_project_types=?, preferred_domains=?, meeting_preference=?, availability_note=? WHERE faculty_profile_id=?'
                )->execute([$acceptingMentees, $acceptingTeam, $acceptingPaper, $maxMentees, $projectTypes, $domains, $meeting, $note, $fpId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO faculty_preferences (faculty_profile_id, accepting_mentees, accepting_team_advisory, accepting_paper_advisory, max_active_mentees, preferred_project_types, preferred_domains, meeting_preference, availability_note) VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$fpId, $acceptingMentees, $acceptingTeam, $acceptingPaper, $maxMentees, $projectTypes, $domains, $meeting, $note]);
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated mentorship preferences');
            flash('success', 'Mentorship preferences updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_preferences: ' . $ex->getMessage());
            flash('error', 'Could not update mentorship preferences.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // VISIBILITY
    // -----------------------------------------------------------------
    case 'update_visibility': {
        $validVis = ['Public', 'Students Only', 'Private'];
        $profileVis = in_array($_POST['profile_visibility'] ?? '', $validVis, true) ? $_POST['profile_visibility'] : 'Public';
        $contact    = isset($_POST['contact_visibility']) ? 1 : 0;
        $research   = isset($_POST['research_visibility']) ? 1 : 0;
        $project    = isset($_POST['project_visibility']) ? 1 : 0;
        $publication = isset($_POST['publication_visibility']) ? 1 : 0;

        try {
            $exists = $pdo->prepare('SELECT id FROM faculty_visibility WHERE faculty_profile_id = ?');
            $exists->execute([$fpId]);
            if ($exists->fetchColumn()) {
                $pdo->prepare('UPDATE faculty_visibility SET profile_visibility=?, contact_visibility=?, research_visibility=?, project_visibility=?, publication_visibility=? WHERE faculty_profile_id=?')
                    ->execute([$profileVis, $contact, $research, $project, $publication, $fpId]);
            } else {
                $pdo->prepare('INSERT INTO faculty_visibility (faculty_profile_id, profile_visibility, contact_visibility, research_visibility, project_visibility, publication_visibility) VALUES (?,?,?,?,?,?)')
                    ->execute([$fpId, $profileVis, $contact, $research, $project, $publication]);
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated profile visibility settings');
            flash('success', 'Visibility settings updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit update_visibility: ' . $ex->getMessage());
            flash('error', 'Could not update visibility settings.');
        }
        break;
    }

    // -----------------------------------------------------------------
    // PHOTO / COVER UPLOAD
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
            $old = $facultyProfile['profile_photo'] ?? null;
            $pdo->prepare('UPDATE faculty_profiles SET profile_photo = ? WHERE id = ?')->execute([$filename, $fpId]);
            if ($old && is_file($dir . $old)) {
                @unlink($dir . $old);
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated profile photo');
            flash('success', 'Profile photo updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit upload_photo: ' . $ex->getMessage());
            flash('error', 'Could not upload photo. Please try again.');
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
            $old = $facultyProfile['cover_photo'] ?? null;
            $pdo->prepare('UPDATE faculty_profiles SET cover_photo = ? WHERE id = ?')->execute([$filename, $fpId]);
            if ($old && is_file($dir . $old)) {
                @unlink($dir . $old);
            }
            log_activity($pdo, $userId, 'profile_update', 'Updated cover photo');
            flash('success', 'Cover photo updated.');
        } catch (Throwable $ex) {
            error_log('faculty profile-edit upload_cover: ' . $ex->getMessage());
            flash('error', 'Could not upload cover photo. Please try again.');
        }
        break;
    }

    default:
        flash('error', 'Unknown action.');
}

redirect('/faculty/profile.php');
