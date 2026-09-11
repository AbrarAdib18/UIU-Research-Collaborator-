<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    flash('error', 'That team could not be found.');
    redirect('/student/teams.php');
}
if (!is_team_member($pdo, $teamId, $userId)) {
    flash('error', "You don't have access to this team's workspace.");
    redirect('/student/teams.php');
}
$isLeader = is_team_leader($pdo, $teamId, $userId);

$uploadsRoot = realpath(__DIR__ . '/../uploads');
if ($uploadsRoot === false) {
    $uploadsRoot = __DIR__ . '/../uploads';
}
$teamFilesDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'team-files' . DIRECTORY_SEPARATOR . $teamId;

const TEAM_FILE_MIME_MAP = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'zip'  => 'application/zip',
    'txt'  => 'text/plain',
];

function format_file_size(?int $bytes): string
{
    if (!$bytes || $bytes <= 0) {
        return '0 B';
    }
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

// -------------------------------------------------------------------------
// Download — re-verifies membership (already checked above) and that the
// file row actually belongs to this team before ever touching disk.
// -------------------------------------------------------------------------
if (isset($_GET['download'])) {
    $fileId = (int)$_GET['download'];

    $fileStmt = $pdo->prepare('SELECT * FROM team_files WHERE id = ? AND team_id = ?');
    $fileStmt->execute([$fileId, $teamId]);
    $file = $fileStmt->fetch();

    if (!$file) {
        flash('error', 'That file could not be found.');
        redirect('/student/team-files.php?id=' . $teamId);
    }

    $realPath = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['file_path']);
    // Defense in depth: the resolved path must stay inside the uploads root.
    $resolved = realpath($realPath);
    if ($resolved === false || strpos($resolved, $uploadsRoot) !== 0 || !is_file($resolved)) {
        flash('error', 'That file is no longer available.');
        redirect('/student/team-files.php?id=' . $teamId);
    }

    $ext      = strtolower((string)$file['file_type']);
    $mimeType = TEAM_FILE_MIME_MAP[$ext] ?? 'application/octet-stream';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file['file_name']) . '"');
    header('Content-Length: ' . filesize($resolved));
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    readfile($resolved);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $backUrl = '/student/team-files.php?id=' . $teamId;

    if ($action === 'upload') {
        require_csrf($backUrl);

        $result = validate_upload(
            $_FILES['file'] ?? [],
            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip', 'txt'],
            10 * 1024 * 1024
        );

        if (!$result['ok']) {
            flash('error', $result['error']);
            redirect($backUrl);
        }

        if (!is_dir($teamFilesDir)) {
            mkdir($teamFilesDir, 0755, true);
        }

        $originalName = $_FILES['file']['name'];
        $safeName     = safe_filename($originalName);
        $destination  = $teamFilesDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            flash('error', 'Could not save the uploaded file. Please try again.');
            redirect($backUrl);
        }

        $relativePath = 'team-files/' . $teamId . '/' . $safeName;

        try {
            $ins = $pdo->prepare(
                "INSERT INTO team_files (team_id, uploaded_by, file_name, file_path, file_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$teamId, $userId, $originalName, $relativePath, $result['ext'], (int)$_FILES['file']['size']]);

            log_activity($pdo, $userId, 'file_uploaded', "Uploaded \"{$originalName}\" to \"{$team['name']}\".", 'team', $teamId);
            flash('success', 'File uploaded.');
        } catch (Throwable $ex) {
            @unlink($destination);
            error_log('team-files.php upload: ' . $ex->getMessage());
            flash('error', 'Could not save the file record. Please try again.');
        }
        redirect($backUrl);
    }

    if ($action === 'delete') {
        require_csrf($backUrl);

        $fileId = (int)($_POST['file_id'] ?? 0);
        $fileStmt = $pdo->prepare('SELECT * FROM team_files WHERE id = ? AND team_id = ?');
        $fileStmt->execute([$fileId, $teamId]);
        $file = $fileStmt->fetch();

        if (!$file) {
            flash('error', 'That file could not be found.');
            redirect($backUrl);
        }
        if ((int)$file['uploaded_by'] !== $userId && !$isLeader) {
            flash('error', 'Only the uploader or team leader can delete this file.');
            redirect($backUrl);
        }

        $realPath = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['file_path']);
        $resolved = realpath($realPath);
        if ($resolved !== false && strpos($resolved, $uploadsRoot) === 0 && is_file($resolved)) {
            @unlink($resolved);
        }

        $del = $pdo->prepare('DELETE FROM team_files WHERE id = ?');
        $del->execute([$fileId]);

        log_activity($pdo, $userId, 'file_deleted', "Deleted \"{$file['file_name']}\" from \"{$team['name']}\".", 'team', $teamId);
        flash('success', 'File deleted.');
        redirect($backUrl);
    }

    flash('error', 'Unknown action.');
    redirect($backUrl);
}

$filesStmt = $pdo->prepare(
    "SELECT tf.*, u.name AS uploader_name
     FROM team_files tf
     JOIN users u ON u.id = tf.uploaded_by
     WHERE tf.team_id = ?
     ORDER BY tf.uploaded_at DESC"
);
$filesStmt->execute([$teamId]);
$files = $filesStmt->fetchAll();

const FILE_ICON_MAP = [
    'pdf'  => 'bi-file-earmark-pdf-fill text-danger',
    'doc'  => 'bi-file-earmark-word-fill text-primary',
    'docx' => 'bi-file-earmark-word-fill text-primary',
    'xls'  => 'bi-file-earmark-excel-fill text-success',
    'xlsx' => 'bi-file-earmark-excel-fill text-success',
    'ppt'  => 'bi-file-earmark-ppt-fill text-warning',
    'pptx' => 'bi-file-earmark-ppt-fill text-warning',
    'png'  => 'bi-file-earmark-image-fill text-info',
    'jpg'  => 'bi-file-earmark-image-fill text-info',
    'jpeg' => 'bi-file-earmark-image-fill text-info',
    'zip'  => 'bi-file-earmark-zip-fill text-secondary',
    'txt'  => 'bi-file-earmark-text-fill text-secondary',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files - <?= e($team['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .team-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px;padding-bottom:14px;border-bottom:1px solid var(--border-color)}
        .team-subnav a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
        .team-subnav a:hover{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .team-subnav a.active{background:var(--uiu-blue);color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .file-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border-light,#eee)}
        .file-row:last-child{border-bottom:none}
        .file-row i{font-size:26px;flex-shrink:0}
        .file-meta{font-size:12px;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2><?= e($team['name']) ?> — Files</h2>
                <p>Share and manage documents for this team.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <nav class="team-subnav">
            <a href="<?= e(url('/student/team-details.php?id=' . $teamId)) ?>"><i class="bi bi-info-circle"></i> Overview</a>
            <a href="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>"><i class="bi bi-list-check"></i> Tasks</a>
            <a href="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>"><i class="bi bi-flag"></i> Milestones</a>
            <a class="active" href="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>"><i class="bi bi-folder"></i> Files</a>
            <a href="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>"><i class="bi bi-chat-dots"></i> Messages</a>
        </nav>

        <div class="app-panel">
            <h3>Upload a File</h3>
            <form action="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>" method="post" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input type="file" class="form-control" name="file" style="max-width:360px" required>
                <button type="submit" class="btn btn-uiu"><i class="bi bi-upload"></i> Upload</button>
                <span class="align-self-center text-muted small">PDF, Office docs, images, zip, txt — up to 10 MB.</span>
            </form>
        </div>

        <div class="app-panel">
            <h3>Files (<?= count($files) ?>)</h3>
            <?php if ($files): ?>
                <?php foreach ($files as $file): ?>
                    <?php $iconClass = FILE_ICON_MAP[strtolower((string)$file['file_type'])] ?? 'bi-file-earmark-fill text-secondary'; ?>
                    <div class="file-row">
                        <i class="bi <?= e($iconClass) ?>"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($file['file_name']) ?></div>
                            <div class="file-meta">
                                <?= e(format_file_size((int)$file['file_size'])) ?> &middot;
                                Uploaded by <?= e($file['uploader_name']) ?> &middot;
                                <?= e(time_ago($file['uploaded_at'])) ?>
                            </div>
                        </div>
                        <a href="<?= e(url('/student/team-files.php?id=' . $teamId . '&download=' . $file['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a>
                        <?php if ((int)$file['uploaded_by'] === $userId || $isLeader): ?>
                            <form action="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>" method="post" onsubmit="return confirm('Delete this file?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="file_id" value="<?= (int)$file['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-folder2-open"></i><p>No files shared yet. Upload one above.</p></div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
