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
$message     = trim((string)($_POST['message'] ?? ''));

if ($otherUserId <= 0 || $otherUserId === $userId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid recipient.']);
    exit;
}
if ($message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}
if (!can_message($pdo, $userId, $otherUserId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You are not connected with this person.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $conversationId = get_or_create_direct_conversation($pdo, $userId, $otherUserId);

    $ins = $pdo->prepare('INSERT INTO direct_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)');
    $ins->execute([$conversationId, $userId, $message]);
    $messageId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE direct_conversations SET last_message_at = NOW() WHERE id = ?')->execute([$conversationId]);

    // Aggregate notifications: only notify if the recipient had no unread messages from us yet in this thread.
    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE conversation_id = ? AND sender_id = ? AND is_read = 0 AND id != ?');
    $unreadStmt->execute([$conversationId, $userId, $messageId]);
    if ((int)$unreadStmt->fetchColumn() === 0) {
        $senderName = current_user()['name'] ?? 'Someone';
        create_notification(
            $pdo, $otherUserId, 'direct_message', 'New Message',
            $senderName . ' sent you a message.',
            'direct_message', $userId
        );
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => $messageId,
            'sender_id' => $userId,
            'mine' => true,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'time_ago' => 'Just now',
        ],
    ]);
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('api/chat/send-message: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not send message.']);
}
