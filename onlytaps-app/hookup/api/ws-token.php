<?php
session_start();
require_once __DIR__ . '/../includes/ws_token.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

echo json_encode(['token' => WsToken::issue($_SESSION['user_id'])]);
