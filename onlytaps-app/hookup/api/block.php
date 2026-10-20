<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/CSRF.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$me = (int)$_SESSION['user_id'];
$target = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? 'block';
if (!$target || $target === $me || !in_array($action, ['block', 'unblock'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$db = (new Database())->getConnection();
if ($action === 'block') {
    $exists = $db->prepare('SELECT 1 FROM users WHERE id = ?');
    $exists->execute([$target]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    $db->prepare('INSERT IGNORE INTO blocked_users (blocker_id, blocked_id, reason) VALUES (?, ?, ?)')
       ->execute([$me, $target, 'Blocked from messages']);
} else {
    $db->prepare('DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?')->execute([$me, $target]);
}

echo json_encode(['ok' => true, 'blocked' => $action === 'block']);
