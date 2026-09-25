<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/users.php');
}

$pdo       = db();
$adminId   = (int)$currentUser['id'];
$targetId  = validate_id($_POST['user_id'] ?? null);
$action    = $_POST['action'] ?? '';
$backUrl   = '/admin/user-details.php?id=' . $targetId;

require_csrf($backUrl);

$validActions = [
    'activate'   => 'active',
    'deactivate' => 'inactive',
    'suspend'    => 'suspended',
    'restore'    => 'active',
];

if (!$targetId || !isset($validActions[$action])) {
    flash('error', 'Invalid request.');
    redirect('/admin/users.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    flash('error', 'User not found.');
    redirect('/admin/users.php');
}

$newStatus = $validActions[$action];
$isDeactivating = in_array($action, ['deactivate', 'suspend'], true);

if ($isDeactivating && $targetId === $adminId) {
    flash('error', 'You cannot deactivate or suspend your own account.');
    redirect($backUrl);
}

if ($isDeactivating && $target['role'] === 'admin') {
    $activeAdminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
    if ($activeAdminCount <= 1) {
        flash('error', 'You cannot deactivate or suspend the last remaining active administrator.');
        redirect($backUrl);
    }
}

if ($target['status'] === $newStatus) {
    flash('info', 'User is already ' . $newStatus . '.');
    redirect($backUrl);
}

try {
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetId]);
    log_activity($pdo, $adminId, 'admin_user_status_change', "Set {$target['name']}'s account status to {$newStatus}", 'user', $targetId);

    if ($newStatus !== $target['status']) {
        create_notification(
            $pdo, $targetId, 'account_status',
            'Account Status Updated',
            'Your account status was changed to "' . ucfirst($newStatus) . '" by an administrator.',
            'user', $targetId
        );
    }

    $pdo->commit();
    flash('success', "{$target['name']}'s account is now {$newStatus}.");
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('admin user-status: ' . $ex->getMessage());
    flash('error', 'Could not update user status. Please try again.');
}

redirect($backUrl);
