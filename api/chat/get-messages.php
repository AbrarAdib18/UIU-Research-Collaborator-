<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$pdo         = db();
$userId      = current_user_id();
$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$sinceId     = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

if ($otherUserId <= 0 || $otherUserId === $userId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation.']);
    exit;
}

if (!can_message($pdo, $userId, $otherUserId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You are not connected with this person.']);
    exit;
}

try {
    $one = min($userId, $otherUserId);
    $two = max($userId, $otherUserId);
    $convStmt = $pdo->prepare('SELECT id FROM direct_conversations WHERE user_one_id = ? AND user_two_id = ?');
    $convStmt->execute([$one, $two]);
    $conversationId = $convStmt->fetchColumn();

    if (!$conversationId) {
        echo json_encode(['ok' => true, 'conversation_id' => null, 'messages' => []]);
        exit;
    }

    if ($sinceId > 0) {
        $msgStmt = $pdo->prepare('SELECT * FROM direct_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 200');
        $msgStmt->execute([$conversationId, $sinceId]);
    } else {
        $msgStmt = $pdo->prepare('SELECT * FROM direct_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 50');
        $msgStmt->execute([$conversationId]);
    }
    $messages = $msgStmt->fetchAll();
    if ($sinceId === 0) {
        $messages = array_reverse($messages);
    }

    $out = array_map(function ($m) use ($userId) {
        return [
            'id'         => (int)$m['id'],
            'sender_id'  => (int)$m['sender_id'],
            'mine'       => (int)$m['sender_id'] === $userId,
            'message'    => $m['message'],
            'created_at' => $m['created_at'],
            'time_ago'   => time_ago($m['created_at']),
        ];
    }, $messages);

    echo json_encode(['ok' => true, 'conversation_id' => (int)$conversationId, 'messages' => $out]);
} catch (Throwable $ex) {
    error_log('api/chat/get-messages: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load messages.']);
}
