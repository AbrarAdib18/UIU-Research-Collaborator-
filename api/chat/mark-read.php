<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}
if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Please reload the page.']);
    exit;
}

$pdo         = db();
$userId      = current_user_id();
$otherUserId = (int)($_POST['user_id'] ?? 0);

try {
    $one = min($userId, $otherUserId);
    $two = max($userId, $otherUserId);
    $convStmt = $pdo->prepare('SELECT id FROM direct_conversations WHERE user_one_id = ? AND user_two_id = ?');
    $convStmt->execute([$one, $two]);
    $conversationId = $convStmt->fetchColumn();

    if ($conversationId) {
        $pdo->prepare('UPDATE direct_messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND is_read = 0')
            ->execute([$conversationId, $userId]);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $ex) {
    error_log('api/chat/mark-read: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not mark messages as read.']);
}
