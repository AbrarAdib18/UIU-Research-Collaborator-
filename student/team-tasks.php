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

$activeMembersStmt = $pdo->prepare(
    "SELECT u.id, u.name FROM team_members tm JOIN users u ON u.id = tm.user_id
     WHERE tm.team_id = ? AND tm.status = 'Active' ORDER BY u.name"
);
$activeMembersStmt->execute([$teamId]);
$activeMembers = $activeMembersStmt->fetchAll();
$activeMemberIds = array_map(fn($m) => (int)$m['id'], $activeMembers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $backUrl = '/student/team-tasks.php?id=' . $teamId;

    if ($action === 'create') {
        require_csrf($backUrl);

        $title       = trim((string)($_POST['title'] ?? ''));
        $description = nullable_trim($_POST['description'] ?? '');
        $priority    = $_POST['priority'] ?? 'Medium';
        $dueDate     = nullable_trim($_POST['due_date'] ?? '');
        $assignedTo  = ($_POST['assigned_to'] ?? '') !== '' ? (int)$_POST['assigned_to'] : null;

        if (!in_array($priority, ['Low', 'Medium', 'High', 'Critical'], true)) {
            $priority = 'Medium';
        }
        if ($assignedTo !== null && !in_array($assignedTo, $activeMemberIds, true)) {
            $assignedTo = null;
        }
        if ($dueDate !== null && !strtotime($dueDate)) {
            $dueDate = null;
        }

        if ($title === '') {
            flash('error', 'Task title is required.');
            redirect($backUrl);
        }

        try {
            $ins = $pdo->prepare(
                "INSERT INTO team_tasks (team_id, assigned_to, created_by, title, description, priority, status, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, 'To Do', ?)"
            );
            $ins->execute([$teamId, $assignedTo, $userId, $title, $description, $priority, $dueDate]);

            log_activity($pdo, $userId, 'task_created', "Created task \"{$title}\" in \"{$team['name']}\".", 'team', $teamId);
            flash('success', 'Task created.');
        } catch (Throwable $ex) {
            error_log('team-tasks.php create: ' . $ex->getMessage());
            flash('error', 'Could not create the task. Please try again.');
        }
        redirect($backUrl);
    }

    if ($action === 'update') {
        require_csrf($backUrl);

        $taskId     = (int)($_POST['task_id'] ?? 0);
        $status     = $_POST['status'] ?? 'To Do';
        $priority   = $_POST['priority'] ?? 'Medium';
        $assignedTo = ($_POST['assigned_to'] ?? '') !== '' ? (int)$_POST['assigned_to'] : null;

        $taskChk = $pdo->prepare('SELECT * FROM team_tasks WHERE id = ? AND team_id = ?');
        $taskChk->execute([$taskId, $teamId]);
        $task = $taskChk->fetch();

        if (!$task) {
            flash('error', 'That task could not be found.');
            redirect($backUrl);
        }
        if (!in_array($status, ['To Do', 'In Progress', 'Completed', 'Blocked'], true)) {
            $status = $task['status'];
        }
        if (!in_array($priority, ['Low', 'Medium', 'High', 'Critical'], true)) {
            $priority = $task['priority'];
        }
        if ($assignedTo !== null && !in_array($assignedTo, $activeMemberIds, true)) {
            $assignedTo = $task['assigned_to'];
        }

        $upd = $pdo->prepare('UPDATE team_tasks SET status = ?, priority = ?, assigned_to = ? WHERE id = ?');
        $upd->execute([$status, $priority, $assignedTo, $taskId]);

        log_activity($pdo, $userId, 'task_updated', "Updated task \"{$task['title']}\" in \"{$team['name']}\".", 'team', $teamId);
        flash('success', 'Task updated.');
        redirect($backUrl);
    }

    if ($action === 'delete') {
        require_csrf($backUrl);

        $taskId = (int)($_POST['task_id'] ?? 0);
        $taskChk = $pdo->prepare('SELECT * FROM team_tasks WHERE id = ? AND team_id = ?');
        $taskChk->execute([$taskId, $teamId]);
        $task = $taskChk->fetch();

        if (!$task) {
            flash('error', 'That task could not be found.');
            redirect($backUrl);
        }
        if ((int)$task['created_by'] !== $userId && !$isLeader) {
            flash('error', 'Only the task creator or team leader can delete this task.');
            redirect($backUrl);
        }

        $del = $pdo->prepare('DELETE FROM team_tasks WHERE id = ?');
        $del->execute([$taskId]);

        log_activity($pdo, $userId, 'task_deleted', "Deleted task \"{$task['title']}\" in \"{$team['name']}\".", 'team', $teamId);
        flash('success', 'Task deleted.');
        redirect($backUrl);
    }

    flash('error', 'Unknown action.');
    redirect($backUrl);
}

$tasksStmt = $pdo->prepare(
    "SELECT tt.*, au.name AS assignee_name, cu.name AS creator_name
     FROM team_tasks tt
     LEFT JOIN users au ON au.id = tt.assigned_to
     JOIN users cu ON cu.id = tt.created_by
     WHERE tt.team_id = ?
     ORDER BY tt.due_date IS NULL, tt.due_date ASC, tt.created_at DESC"
);
$tasksStmt->execute([$teamId]);
$tasks = $tasksStmt->fetchAll();

function task_status_pill(string $status): string
{
    return match ($status) {
        'To Do'       => 'pill pill-gray',
        'In Progress' => 'pill pill-blue',
        'Completed'   => 'pill pill-green',
        'Blocked'     => 'pill pill-red',
        default       => 'pill pill-gray',
    };
}
function task_priority_pill(string $priority): string
{
    return match ($priority) {
        'Low'      => 'pill pill-gray',
        'Medium'   => 'pill pill-blue',
        'High'     => 'pill pill-orange',
        'Critical' => 'pill pill-red',
        default    => 'pill pill-gray',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - <?= e($team['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .pill-purple{background:#ece1fb;color:#5b21a6}
        .team-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px;padding-bottom:14px;border-bottom:1px solid var(--border-color)}
        .team-subnav a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
        .team-subnav a:hover{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .team-subnav a.active{background:var(--uiu-blue);color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .task-card{border:1px solid var(--border-color);border-radius:10px;padding:14px;margin-bottom:12px}
        .task-card .task-title{font-weight:700;font-size:14px;margin-bottom:4px}
        .task-card .task-meta{font-size:12px;color:var(--text-light);margin-bottom:8px}
        .task-inline-form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px}
        .task-inline-form select{font-size:12px;padding:3px 6px;border-radius:6px;border:1px solid var(--border-color)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2><?= e($team['name']) ?> — Tasks</h2>
                <p>Track work items and assignments for this team.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <nav class="team-subnav">
            <a href="<?= e(url('/student/team-details.php?id=' . $teamId)) ?>"><i class="bi bi-info-circle"></i> Overview</a>
            <a class="active" href="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>"><i class="bi bi-list-check"></i> Tasks</a>
            <a href="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>"><i class="bi bi-flag"></i> Milestones</a>
            <a href="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>"><i class="bi bi-folder"></i> Files</a>
            <a href="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>"><i class="bi bi-chat-dots"></i> Messages</a>
        </nav>

        <div class="app-panel">
            <h3>New Task</h3>
            <form action="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="title" maxlength="200" placeholder="Task title" required>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($activeMembers as $m): ?>
                                <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="due_date">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-uiu w-100"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
                <textarea class="form-control mt-2" name="description" rows="2" placeholder="Description (optional)"></textarea>
            </form>
        </div>

        <div class="app-panel">
            <h3>Tasks (<?= count($tasks) ?>)</h3>
            <?php if ($tasks): ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="task-title"><?= e($task['title']) ?></div>
                                <div class="task-meta">
                                    Assigned to <?= e($task['assignee_name'] ?: 'Unassigned') ?>
                                    &middot; Created by <?= e($task['creator_name']) ?>
                                    &middot; Due <?= format_date($task['due_date']) ?>
                                </div>
                                <?php if ($task['description']): ?><p class="small mb-0"><?= nl2br(e($task['description'])) ?></p><?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="<?= task_status_pill($task['status']) ?>"><?= e($task['status']) ?></span>
                                <span class="<?= task_priority_pill($task['priority']) ?>"><?= e($task['priority']) ?></span>
                            </div>
                        </div>

                        <form action="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>" method="post" class="task-inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                            <select name="status">
                                <?php foreach (['To Do', 'In Progress', 'Completed', 'Blocked'] as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="priority">
                                <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                                    <option value="<?= e($p) ?>" <?= $task['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($activeMembers as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)$task['assigned_to'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                        </form>

                        <?php if ((int)$task['created_by'] === $userId || $isLeader): ?>
                            <form action="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>" method="post" class="d-inline mt-2" onsubmit="return confirm('Delete this task?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-list-check"></i><p>No tasks yet. Add one above to get started.</p></div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
