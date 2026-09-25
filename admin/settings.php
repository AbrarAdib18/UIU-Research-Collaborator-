<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

$definitions = [
    'public_registration_enabled' => ['label' => 'Public Registration', 'type' => 'bool', 'help' => 'When off, signup.php blocks new student sign-ups with a clear message.'],
    'student_email_domain'        => ['label' => 'Student Email Domain', 'type' => 'text', 'help' => 'Domain suffix required at signup, e.g. bscse.uiu.ac.bd (no @).'],
    'default_profile_visibility'  => ['label' => 'Default Profile Visibility', 'type' => 'select', 'options' => ['Public', 'Students Only', 'Private'], 'help' => 'Applied to every new student profile at signup.'],
    'default_team_size_limit'     => ['label' => 'Default Team Size Limit', 'type' => 'number', 'help' => 'Pre-filled default on the Create Team form.'],
    'faculty_verification_required' => ['label' => 'Require Faculty Verification to Publish', 'type' => 'bool', 'help' => 'When on, an unverified faculty member can only save opportunities as Draft.'],
    'community_creation_policy'   => ['label' => 'Community Creation Policy', 'type' => 'select', 'options' => ['open', 'admin_approval'], 'help' => "'admin_approval' creates new communities as Inactive until an admin activates them."],
    'maintenance_notice'          => ['label' => 'Maintenance Notice Banner', 'type' => 'textarea', 'help' => 'Shown as a banner on every public page when non-empty. Leave blank to hide.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/settings.php');

    $changed = [];
    try {
        $pdo->beginTransaction();
        foreach ($definitions as $key => $def) {
            $raw = $_POST[$key] ?? null;
            if ($def['type'] === 'bool') {
                $value = isset($_POST[$key]) ? '1' : '0';
            } elseif ($def['type'] === 'number') {
                $value = (string)max(1, (int)$raw);
            } elseif ($def['type'] === 'select') {
                $value = in_array($raw, $def['options'], true) ? $raw : $def['options'][0];
            } else { // text / textarea
                $value = trim((string)$raw);
                $value = strip_tags($value);
            }

            $pdo->prepare(
                'INSERT INTO platform_settings (setting_key, setting_value, updated_by) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            )->execute([$key, $value, $adminId]);
            $changed[] = $key;
        }
        log_activity($pdo, $adminId, 'admin_settings_update', 'Updated platform settings: ' . implode(', ', $changed));
        $pdo->commit();
        flash('success', 'Platform settings saved.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('admin settings: ' . $ex->getMessage());
        flash('error', 'Could not save settings. Please try again.');
    }
    redirect('/admin/settings.php');
}

$current = get_all_platform_settings($pdo);
$defaults = [
    'public_registration_enabled' => '1',
    'student_email_domain' => 'bscse.uiu.ac.bd',
    'default_profile_visibility' => 'Students Only',
    'default_team_size_limit' => '6',
    'faculty_verification_required' => '0',
    'community_creation_policy' => 'open',
    'maintenance_notice' => '',
];

$pageTitle = 'Platform Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px;margin-bottom:18px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Platform Settings</h2>
        <p class="text-muted small">Every setting below has real, verified backend effect. Nothing here is decorative.</p>

        <div class="app-panel">
            <form method="post">
                <?= csrf_field() ?>
                <?php foreach ($definitions as $key => $def): ?>
                    <?php $val = $current[$key] ?? $defaults[$key]; ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <label class="form-label fw-semibold"><?= e($def['label']) ?></label>
                        <p class="text-muted small mb-2"><?= e($def['help']) ?></p>
                        <?php if ($def['type'] === 'bool'): ?>
                            <div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>></div>
                        <?php elseif ($def['type'] === 'select'): ?>
                            <select name="<?= e($key) ?>" class="form-select" style="max-width:300px;">
                                <?php foreach ($def['options'] as $opt): ?><option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?>
                            </select>
                        <?php elseif ($def['type'] === 'number'): ?>
                            <input type="number" min="1" max="20" name="<?= e($key) ?>" class="form-control" style="max-width:150px;" value="<?= e($val) ?>">
                        <?php elseif ($def['type'] === 'textarea'): ?>
                            <textarea name="<?= e($key) ?>" class="form-control" rows="2" maxlength="500"><?= e($val) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?= e($key) ?>" class="form-control" style="max-width:300px;" value="<?= e($val) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Save Settings</button>
            </form>
        </div>

        <div class="app-panel">
            <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;">For Reference (not configurable — set in code)</h3>
            <p class="text-muted small mb-1">Max upload sizes: profile/cover photos 3MB, CV 5MB, repository files 15MB, team files 10MB.</p>
            <p class="text-muted small mb-0">Supported file types: images (jpg/png/gif/webp), documents (pdf/doc/docx/ppt/pptx/xls/xlsx/csv), archives (zip).</p>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
