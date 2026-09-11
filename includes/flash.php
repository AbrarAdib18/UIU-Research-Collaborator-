<?php
/** One-request flash messages, stored in the session. */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flashes(): void
{
    $flashes = get_flashes();
    if (!$flashes) {
        return;
    }
    $map = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];
    echo '<div class="app-flash-container">';
    foreach ($flashes as $f) {
        $class = $map[$f['type']] ?? 'alert-info';
        echo '<div class="alert ' . $class . ' alert-dismissible fade show app-flash" role="alert">'
            . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
    echo '</div>';
}
