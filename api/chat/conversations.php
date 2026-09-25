<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$pdo    = db();
$userId = current_user_id();

try {
    $stmt = $pdo->prepare(
        "SELECT dc.id AS conversation_id, dc.last_message_at,
                u.id AS other_id, u.name AS other_name, u.role AS other_role,
                COALESCE(sp.profile_photo, fp.profile_photo) AS other_photo,
                (SELECT message FROM direct_messages m WHERE m.conversation_id = dc.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM direct_messages m WHERE m.conversation_id = dc.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
         FROM direct_conversations dc
         JOIN users u ON u.id = (CASE WHEN dc.user_one_id = ? THEN dc.user_two_id ELSE dc.user_one_id END)
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
         WHERE dc.user_one_id = ? OR dc.user_two_id = ?
         ORDER BY dc.last_message_at IS NULL, dc.last_message_at DESC"
    );
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $rows = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'conversations' => $rows]);
} catch (Throwable $ex) {
    error_log('api/chat/conversations: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load conversations.']);
}
